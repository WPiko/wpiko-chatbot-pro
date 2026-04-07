<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Takeover System
 * 
 * Allows admins to take over conversations from AI and reply directly
 * to users via the PWA or WordPress admin.
 */

/**
 * Create the conversation_meta table for storing takeover state
 */
function wpiko_chatbot_pro_create_meta_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(255) NOT NULL,
        meta_key VARCHAR(255) NOT NULL,
        meta_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY session_meta (session_id, meta_key)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create the push subscriptions table
 */
function wpiko_chatbot_pro_create_push_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_push_subscriptions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        endpoint TEXT NOT NULL,
        auth_key TEXT NOT NULL,
        p256dh_key TEXT NOT NULL,
        device_label VARCHAR(191) NOT NULL DEFAULT '',
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        last_seen_at DATETIME NULL DEFAULT NULL,
        last_delivery_status SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
        last_delivery_at DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_endpoint (user_id, endpoint(191))
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Run database migrations for PWA tables
 */
function wpiko_chatbot_pro_pwa_db_migration() {
    global $wpdb;

    $db_version = get_option('wpiko_chatbot_pro_pwa_db_version', '0');
    $meta_table_name = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';
    $push_table_name = $wpdb->prefix . 'wpiko_chatbot_push_subscriptions';
    $meta_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $meta_table_name));
    $push_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $push_table_name));
    
    if (
        version_compare($db_version, '1.2', '<') ||
        $meta_table_exists !== $meta_table_name ||
        $push_table_exists !== $push_table_name
    ) {
        wpiko_chatbot_pro_create_meta_table();
        wpiko_chatbot_pro_create_push_table();
        update_option('wpiko_chatbot_pro_pwa_db_version', '1.2');
    }
}

/**
 * Check if a conversation has human takeover active
 */
function wpiko_chatbot_pro_is_takeover_active($session_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';
    
    // Check if the meta table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if (!$table_exists) {
        return false;
    }
    
    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM `{$wpdb->prefix}wpiko_chatbot_conversation_meta` WHERE session_id = %s AND meta_key = 'human_takeover' LIMIT 1",
        $session_id
    ));
    
    return $value === '1';
}

/**
 * Get the admin ID who currently owns takeover for a conversation
 */
function wpiko_chatbot_pro_get_takeover_admin_id($session_id) {
    return absint(wpiko_chatbot_pro_get_conversation_meta($session_id, 'takeover_admin_id'));
}

/**
 * Check whether the current takeover is owned by a specific admin
 */
function wpiko_chatbot_pro_is_takeover_owned_by_admin($session_id, $admin_id = 0) {
    if (!$admin_id) {
        $admin_id = get_current_user_id();
    }

    if (!$admin_id || !wpiko_chatbot_pro_is_takeover_active($session_id)) {
        return false;
    }

    return wpiko_chatbot_pro_get_takeover_admin_id($session_id) === (int) $admin_id;
}

/**
 * Activate human takeover for a conversation
 */
function wpiko_chatbot_pro_activate_takeover($session_id, $admin_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';
    
    // Set human_takeover flag
    $wpdb->replace($table_name, array(
        'session_id' => $session_id,
        'meta_key' => 'human_takeover',
        'meta_value' => '1',
        'updated_at' => current_time('mysql')
    ));
    
    // Store which admin took over
    $wpdb->replace($table_name, array(
        'session_id' => $session_id,
        'meta_key' => 'takeover_admin_id',
        'meta_value' => (string) $admin_id,
        'updated_at' => current_time('mysql')
    ));
    
    // Store when takeover started
    $wpdb->replace($table_name, array(
        'session_id' => $session_id,
        'meta_key' => 'takeover_started_at',
        'meta_value' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ));
    
    return true;
}

/**
 * Release takeover and restore AI for a conversation
 */
function wpiko_chatbot_pro_release_takeover($session_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';
    
    $wpdb->replace($table_name, array(
        'session_id' => $session_id,
        'meta_key' => 'human_takeover',
        'meta_value' => '0',
        'updated_at' => current_time('mysql')
    ));

    $wpdb->delete($table_name, array(
        'session_id' => $session_id,
        'meta_key' => 'takeover_admin_id',
    ));

    $wpdb->delete($table_name, array(
        'session_id' => $session_id,
        'meta_key' => 'takeover_started_at',
    ));
    
    return true;
}

/**
 * Get the display name of the admin who activated takeover
 */
function wpiko_chatbot_pro_get_takeover_admin_name($session_id) {
    $admin_id = wpiko_chatbot_pro_get_takeover_admin_id($session_id);
    if (!$admin_id) {
        return '';
    }
    $user = get_userdata($admin_id);
    return $user ? ($user->display_name ?: $user->user_login) : '';
}

/**
 * Get the avatar URL of the admin who activated takeover
 */
function wpiko_chatbot_pro_get_admin_avatar_data($admin_id, $size = 64) {
    $admin_id = absint($admin_id);
    $size = max(1, absint($size));

    if (!$admin_id || !function_exists('get_avatar_data')) {
        return array(
            'url' => '',
            'found_avatar' => false,
        );
    }

    $avatar_data = get_avatar_data($admin_id, array(
        'size' => $size,
        'default' => '404',
    ));

    $avatar_url = !empty($avatar_data['url']) ? (string) $avatar_data['url'] : '';
    $found_avatar = !empty($avatar_data['found_avatar']) && $avatar_url !== '';

    if ($found_avatar && strpos($avatar_url, 'd=404') !== false) {
        $found_avatar = false;
        $avatar_url = '';
    }

    return array(
        'url' => $avatar_url,
        'found_avatar' => $found_avatar,
    );
}

function wpiko_chatbot_pro_get_admin_avatar_url($admin_id, $size = 64) {
    $avatar_data = wpiko_chatbot_pro_get_admin_avatar_data($admin_id, $size);

    return $avatar_data['found_avatar'] ? $avatar_data['url'] : '';
}

function wpiko_chatbot_pro_get_takeover_admin_avatar_url($session_id, $size = 64) {
    $admin_id = wpiko_chatbot_pro_get_takeover_admin_id($session_id);
    if (!$admin_id) {
        return '';
    }

    return wpiko_chatbot_pro_get_admin_avatar_url($admin_id, $size);
}

/**
 * Build the avatar markup used for admin takeover replies in WP admin.
 */
function wpiko_chatbot_pro_get_admin_reply_avatar_html($admin_id, $admin_name, $size = 40) {
    $admin_id = absint($admin_id);
    $size = max(1, absint($size));
    $admin_name = trim((string) $admin_name);

    if ($admin_name === '' && $admin_id) {
        $admin_user = get_userdata($admin_id);
        if ($admin_user) {
            $admin_name = $admin_user->display_name ?: $admin_user->user_login;
        }
    }

    $initial = function_exists('mb_substr')
        ? mb_substr($admin_name !== '' ? $admin_name : 'A', 0, 1)
        : substr($admin_name !== '' ? $admin_name : 'A', 0, 1);

    $fallback_html = sprintf(
        '<span class="avatar-initial" aria-label="%s">%s</span>',
        esc_attr($admin_name !== '' ? $admin_name : 'Admin'),
        esc_html($initial)
    );

    $avatar_data = wpiko_chatbot_pro_get_admin_avatar_data($admin_id, $size);

    if ($avatar_data['found_avatar']) {
        $onerror = sprintf(
            'this.onerror=null;this.outerHTML=%s;',
            wp_json_encode($fallback_html)
        );

        return sprintf(
            '<img src="%s" class="avatar" width="%d" height="%d" alt="%s" onerror="%s">',
            esc_url($avatar_data['url']),
            $size,
            $size,
            esc_attr($admin_name !== '' ? $admin_name : 'Admin'),
            esc_attr($onerror)
        );
    }

    return $fallback_html;
}

/**
 * Replace the default bot avatar with the takeover admin avatar in WP admin transcripts.
 */
function wpiko_chatbot_pro_filter_admin_message_avatar_html($avatar_html, $conversation, $session_id) {
    if (!is_object($conversation) || !isset($conversation->role) || $conversation->role !== 'admin') {
        return $avatar_html;
    }

    $admin_id = isset($conversation->user_id) ? absint($conversation->user_id) : 0;
    $admin_name = isset($conversation->user_name) ? sanitize_text_field($conversation->user_name) : '';

    if (!$admin_id && !empty($session_id) && function_exists('wpiko_chatbot_pro_get_takeover_admin_id')) {
        $admin_id = wpiko_chatbot_pro_get_takeover_admin_id($session_id);
    }

    return wpiko_chatbot_pro_get_admin_reply_avatar_html($admin_id, $admin_name, 40);
}
add_filter('wpiko_chatbot_admin_message_avatar_html', 'wpiko_chatbot_pro_filter_admin_message_avatar_html', 10, 3);

/**
 * Get the customizable suffix for takeover notice messages.
 */
function wpiko_chatbot_pro_get_takeover_notice_suffix($event_type) {
    if ($event_type === 'release_notice') {
        return get_option('wpiko_chatbot_takeover_release_text', 'left the conversation. AI assistant is back.');
    }

    return get_option('wpiko_chatbot_takeover_join_text', 'joined the conversation.');
}

/**
 * Build a takeover notice for a named admin using the saved suffix.
 */
function wpiko_chatbot_pro_build_takeover_notice($admin_name, $event_type) {
    $admin_name = trim((string) $admin_name);
    $suffix = trim((string) wpiko_chatbot_pro_get_takeover_notice_suffix($event_type));

    if ($admin_name === '') {
        return $event_type === 'release_notice'
            ? 'AI assistant is back.'
            : 'A live agent joined the conversation.';
    }

    if ($suffix === '') {
        return $admin_name;
    }

    return sprintf('%s %s', $admin_name, $suffix);
}

/**
 * Build the message shown when an admin joins a conversation.
 */
function wpiko_chatbot_pro_get_takeover_join_notice($admin_name = '') {
    return wpiko_chatbot_pro_build_takeover_notice($admin_name, 'takeover_notice');
}

/**
 * Build the message shown when an admin leaves a conversation.
 */
function wpiko_chatbot_pro_get_takeover_release_notice($admin_name = '') {
    return wpiko_chatbot_pro_build_takeover_notice($admin_name, 'release_notice');
}

/**
 * Detect the event type for takeover-related transcript messages.
 */
function wpiko_chatbot_pro_get_takeover_event_type($message, $admin_name = '') {
    $message = trim((string) $message);
    $admin_name = trim((string) $admin_name);

    if ($message === '') {
        return 'admin_message';
    }

    $join_notices = array(
        wpiko_chatbot_pro_get_takeover_join_notice($admin_name),
        $admin_name !== '' ? sprintf('%s joined the conversation.', $admin_name) : 'A live agent joined the conversation.',
    );

    if (in_array($message, $join_notices, true)) {
        return 'takeover_notice';
    }

    $release_notices = array(
        wpiko_chatbot_pro_get_takeover_release_notice($admin_name),
        $admin_name !== '' ? sprintf('%s left the conversation. AI assistant is back.', $admin_name) : 'AI assistant is back.',
    );

    if (in_array($message, $release_notices, true)) {
        return 'release_notice';
    }

    return 'admin_message';
}

/**
 * Persist a takeover event notice in the conversation transcript.
 */
function wpiko_chatbot_pro_save_takeover_event_notice($session_id, $admin_id, $event_type) {
    global $wpdb;

    $admin_id = absint($admin_id);
    $admin_user = $admin_id ? get_userdata($admin_id) : null;
    $admin_name = $admin_user ? ($admin_user->display_name ?: $admin_user->user_login) : 'Admin';

    if ($event_type === 'release_notice') {
        $message = wpiko_chatbot_pro_get_takeover_release_notice($admin_name);
    } else {
        $message = wpiko_chatbot_pro_get_takeover_join_notice($admin_name);
    }

    $wpdb->insert($wpdb->prefix . 'wpiko_chatbot_conversations', array(
        'user_id' => $admin_id,
        'session_id' => $session_id,
        'role' => 'admin',
        'message' => $message,
        'timestamp' => current_time('mysql'),
        'user_email' => $admin_user ? $admin_user->user_email : '',
        'user_name' => $admin_name,
        'country' => '',
        'city' => '',
        'region' => '',
        'device_type' => '',
    ));

    return $wpdb->insert_id;
}

/**
 * Get conversation meta value
 */
function wpiko_chatbot_pro_get_conversation_meta($session_id, $key) {
    global $wpdb;
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM `{$wpdb->prefix}wpiko_chatbot_conversation_meta` WHERE session_id = %s AND meta_key = %s LIMIT 1",
        $session_id,
        $key
    ));
}

/**
 * Filter hook: Skip AI response when takeover is active
 * This hooks into the free plugin's filter
 */
function wpiko_chatbot_pro_filter_skip_ai($skip, $session_id) {
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        return $skip;
    }
    if (!wpiko_chatbot_pro_is_license_active()) {
        return $skip;
    }
    
    return wpiko_chatbot_pro_is_takeover_active($session_id);
}
add_filter('wpiko_chatbot_skip_ai_response', 'wpiko_chatbot_pro_filter_skip_ai', 10, 2);

/**
 * Save an admin reply to a conversation
 */
function wpiko_chatbot_pro_save_admin_reply($session_id, $message, $admin_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    if (!wpiko_chatbot_pro_is_takeover_active($session_id) || !wpiko_chatbot_pro_is_takeover_owned_by_admin($session_id, $admin_id)) {
        return false;
    }
    
    $admin_user = get_userdata($admin_id);
    $admin_name = $admin_user ? $admin_user->display_name : 'Admin';
    
    $wpdb->insert($table_name, array(
        'user_id' => $admin_id,
        'session_id' => $session_id,
        'role' => 'admin',
        'message' => $message,
        'timestamp' => current_time('mysql'),
        'user_email' => $admin_user ? $admin_user->user_email : '',
        'user_name' => $admin_name,
        'country' => '',
        'city' => '',
        'region' => '',
        'device_type' => ''
    ));
    
    return $wpdb->insert_id;
}

/**
 * Get new messages for a conversation since a given message ID
 * Used by the frontend polling mechanism
 */
function wpiko_chatbot_pro_get_new_messages($session_id, $last_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT id, role, message, timestamp, user_name FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE session_id = %s AND id > %d ORDER BY id ASC",
        $session_id,
        intval($last_id)
    ));
}

/**
 * AJAX: Check takeover status for a conversation (WP admin)
 */
function wpiko_chatbot_pro_ajax_check_takeover() {
    check_ajax_referer('wpiko_chatbot_takeover_nonce', '_wpnonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        wp_send_json_error(array('message' => 'Mobile App is disabled'));
    }
    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
        wp_send_json_error(array('message' => 'License inactive'));
    }
    $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
    $is_takeover = wpiko_chatbot_pro_is_takeover_active($session_id);
    $takeover_admin_name = $is_takeover ? wpiko_chatbot_pro_get_takeover_admin_name($session_id) : '';
    wp_send_json_success(array(
        'human_takeover' => $is_takeover,
        'takeover_admin_name' => $takeover_admin_name,
        'takeover_owned_by_current_admin' => $is_takeover ? wpiko_chatbot_pro_is_takeover_owned_by_admin($session_id, get_current_user_id()) : false,
    ));
}
add_action('wp_ajax_wpiko_chatbot_check_takeover', 'wpiko_chatbot_pro_ajax_check_takeover');

/**
 * AJAX: Activate or release takeover (WP admin)
 */
function wpiko_chatbot_pro_ajax_admin_takeover() {
    check_ajax_referer('wpiko_chatbot_takeover_nonce', '_wpnonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        wp_send_json_error(array('message' => 'Mobile App is disabled'));
    }
    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
        wp_send_json_error(array('message' => 'License inactive'));
    }
    $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
    $takeover_action = isset($_POST['takeover_action']) ? sanitize_text_field(wp_unslash($_POST['takeover_action'])) : '';
    $current_admin_id = get_current_user_id();
    $is_takeover = wpiko_chatbot_pro_is_takeover_active($session_id);
    $takeover_admin_name = $is_takeover ? wpiko_chatbot_pro_get_takeover_admin_name($session_id) : '';

    if ($takeover_action === 'takeover') {
        if ($is_takeover && !wpiko_chatbot_pro_is_takeover_owned_by_admin($session_id, $current_admin_id)) {
            wp_send_json_error(array(
                'message' => $takeover_admin_name ? sprintf('This conversation is already handled by %s.', $takeover_admin_name) : 'This conversation is already handled by another admin.',
                'human_takeover' => true,
                'takeover_admin_name' => $takeover_admin_name,
                'takeover_owned_by_current_admin' => false,
            ));
        }

        wpiko_chatbot_pro_activate_takeover($session_id, $current_admin_id);
        wpiko_chatbot_pro_save_takeover_event_notice($session_id, $current_admin_id, 'takeover_notice');

        wp_send_json_success(array(
            'human_takeover' => true,
            'takeover_admin_name' => wpiko_chatbot_pro_get_takeover_admin_name($session_id),
            'takeover_owned_by_current_admin' => true,
        ));
    } elseif ($takeover_action === 'release') {
        if ($is_takeover && !wpiko_chatbot_pro_is_takeover_owned_by_admin($session_id, $current_admin_id)) {
            wp_send_json_error(array(
                'message' => $takeover_admin_name ? sprintf('Only %s can release this conversation.', $takeover_admin_name) : 'Only the admin who activated takeover can release this conversation.',
                'human_takeover' => true,
                'takeover_admin_name' => $takeover_admin_name,
                'takeover_owned_by_current_admin' => false,
            ));
        }

        wpiko_chatbot_pro_release_takeover($session_id);
        wpiko_chatbot_pro_save_takeover_event_notice($session_id, $current_admin_id, 'release_notice');

        wp_send_json_success(array(
            'human_takeover' => false,
            'takeover_admin_name' => '',
            'takeover_owned_by_current_admin' => false,
        ));
    } else {
        wp_send_json_error(array('message' => 'Invalid action'));
    }
}
add_action('wp_ajax_wpiko_chatbot_admin_takeover', 'wpiko_chatbot_pro_ajax_admin_takeover');

/**
 * Handle user heartbeat — update last_seen timestamp and status in conversation_meta
 * Uses INSERT ... ON DUPLICATE KEY UPDATE for efficient upserts (avoids DELETE+INSERT of REPLACE)
 * Only writes user_status when it actually changes to reduce DB writes
 */
function wpiko_chatbot_pro_handle_user_heartbeat($session_id, $status = 'online') {
    // Skip if PWA is disabled or license is inactive
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        return;
    }
    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';

    // Check if the meta table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if (!$table_exists) {
        return;
    }

    $now = current_time('mysql');
    if ($status === 'chat_cleared' || $status === 'cleared') {
        $new_status = 'cleared';
    } elseif ($status === 'page_left' || $status === 'offline') {
        $new_status = 'page_left';
    } else {
        $new_status = 'online';
    }

    // Always update last_seen — use INSERT ON DUPLICATE KEY UPDATE to avoid DELETE+INSERT overhead
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $wpdb->query($wpdb->prepare(
        "INSERT INTO `{$table_name}` (session_id, meta_key, meta_value, updated_at)
         VALUES (%s, 'user_last_seen', %s, %s)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)",
        $session_id, $now, $now
    ));

    // Only write user_status when it actually changes (saves a DB write on every heartbeat)
    $current_status = wpiko_chatbot_pro_get_conversation_meta($session_id, 'user_status');
    if ($current_status !== $new_status) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query($wpdb->prepare(
            "INSERT INTO `{$table_name}` (session_id, meta_key, meta_value, updated_at)
             VALUES (%s, 'user_status', %s, %s)
             ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)",
            $session_id, $new_status, $now
        ));
    }
}
add_action('wpiko_chatbot_user_heartbeat', 'wpiko_chatbot_pro_handle_user_heartbeat', 10, 2);

/**
 * Build a normalized user presence snapshot for admin and PWA UIs.
 *
 * States:
 * - online: active heartbeat received recently
 * - chat_cleared: the visitor cleared the chat and this thread is no longer visible to them
 * - offline: the visitor is disconnected and live replies may not be seen
 *
 * @param string $session_id        The conversation session ID.
 * @param int    $offline_threshold Seconds before considering the visitor offline.
 * @param int    $page_leave_grace  Retained for backward compatibility.
 * @return array{
 *     state: string,
 *     is_online: bool,
 *     last_seen: string|null,
 *     note: string
 * }
 */
function wpiko_chatbot_pro_get_user_presence_data($session_id, $offline_threshold = 60, $page_leave_grace = 12) {
    $offline_threshold = max(1, absint($offline_threshold));

    $user_status = wpiko_chatbot_pro_get_conversation_meta($session_id, 'user_status');
    $last_seen = wpiko_chatbot_pro_get_conversation_meta($session_id, 'user_last_seen');
    $offline_note = 'The visitor left the site. If they come back later, they will start a new chat, so live replies sent here will not appear in this thread.';

    $presence = array(
        'state' => 'offline',
        'is_online' => false,
        'last_seen' => $last_seen ?: null,
        'note' => '',
    );

    $seconds_since_last_seen = null;
    if (!empty($last_seen)) {
        $last_seen_time = strtotime($last_seen);
        $current_time = strtotime(current_time('mysql'));

        if ($last_seen_time && $current_time) {
            $seconds_since_last_seen = max(0, $current_time - $last_seen_time);
        }
    }

    if ($user_status === 'cleared') {
        $presence['state'] = 'chat_cleared';
        $presence['note'] = 'The visitor cleared the chat. This thread is closed on their side, so live replies sent here will not appear in the new chat they start.';
        return $presence;
    }

    if ($user_status === 'page_left') {
        $presence['note'] = $offline_note;
        return $presence;
    }

    if (empty($last_seen)) {
        return $presence;
    }

    if ($seconds_since_last_seen !== null && $seconds_since_last_seen < $offline_threshold) {
        $presence['state'] = 'online';
        $presence['is_online'] = true;
        return $presence;
    }

    $presence['note'] = $offline_note;

    return $presence;
}

/**
 * Check if a user is currently online based on their heartbeat state.
 *
 * @param string $session_id The conversation session ID.
 * @param int    $threshold  Seconds before considering user offline (default 60).
 * @return bool  True if the user is online.
 */
function wpiko_chatbot_pro_is_user_online($session_id, $threshold = 60) {
    $presence = wpiko_chatbot_pro_get_user_presence_data($session_id, $threshold);

    return !empty($presence['is_online']);
}

/**
 * Get user last seen timestamp for a conversation
 *
 * @param string $session_id The conversation session ID
 * @return string|null The last seen datetime or null
 */
function wpiko_chatbot_pro_get_user_last_seen($session_id) {
    return wpiko_chatbot_pro_get_conversation_meta($session_id, 'user_last_seen');
}

/**
 * Enrich the admin conversation fetch response with user presence data
 * Also marks the conversation as "admin watching" for demand-driven heartbeat
 */
function wpiko_chatbot_pro_enrich_conversation_data($data, $session_id) {
    $presence = wpiko_chatbot_pro_get_user_presence_data($session_id);

    $data['user_online'] = $presence['is_online'];
    $data['user_last_seen'] = $presence['last_seen'];
    $data['user_presence_state'] = $presence['state'];
    $data['user_presence_note'] = $presence['note'];

    // Mark this conversation as being watched by an admin
    wpiko_chatbot_pro_mark_admin_watching($session_id);

    return $data;
}
add_filter('wpiko_chatbot_fetch_conversation_data', 'wpiko_chatbot_pro_enrich_conversation_data', 10, 2);

/**
 * Render the user presence field in the admin conversation details panel
 */
function wpiko_chatbot_pro_render_presence_field() {
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        return;
    }
    echo '<div class="info-item">';
    echo '<span class="info-label">User presence:</span>';
    echo '<span class="user-presence info-value">N/A</span>';
    echo '</div>';
}
add_action('wpiko_chatbot_conversation_additional_info', 'wpiko_chatbot_pro_render_presence_field');

/**
 * Filter: check if user is online (used by the base plugin's check_new_messages AJAX)
 */
function wpiko_chatbot_pro_filter_is_user_online($is_online, $session_id) {
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        return $is_online;
    }
    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
        return $is_online;
    }
    return wpiko_chatbot_pro_is_user_online($session_id);
}
add_filter('wpiko_chatbot_is_user_online', 'wpiko_chatbot_pro_filter_is_user_online', 10, 2);

/**
 * Clean up conversation meta when a single conversation is deleted
 */
function wpiko_chatbot_pro_cleanup_conversation_meta($session_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';

    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if (!$table_exists) {
        return;
    }

    $wpdb->delete($table_name, array('session_id' => $session_id));
}
add_action('wpiko_chatbot_conversation_deleted', 'wpiko_chatbot_pro_cleanup_conversation_meta');

/**
 * Mark a conversation as being watched by an admin.
 * Stores a timestamp so the frontend can determine if heartbeat is needed.
 */
function wpiko_chatbot_pro_mark_admin_watching($session_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';

    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if (!$table_exists) {
        return;
    }

    $now = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $wpdb->query($wpdb->prepare(
        "INSERT INTO `{$table_name}` (session_id, meta_key, meta_value, updated_at)
         VALUES (%s, 'admin_watching', %s, %s)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)",
        $session_id, $now, $now
    ));
}

/**
 * Check if an admin has been watching a conversation recently
 *
 * @param string $session_id The conversation session ID
 * @param int    $threshold  Seconds before considering admin no longer watching (default 300 = 5min)
 * @return bool
 */
function wpiko_chatbot_pro_is_admin_watching($session_id, $threshold = 300) {
    $admin_watching = wpiko_chatbot_pro_get_conversation_meta($session_id, 'admin_watching');
    if (empty($admin_watching)) {
        return false;
    }
    $watched_time = strtotime($admin_watching);
    $current_time = strtotime(current_time('mysql'));
    return ($current_time - $watched_time) < $threshold;
}

/**
 * Add heartbeat_needed flag to check_new_messages response.
 * Heartbeat is needed when an admin is actively watching OR takeover is active.
 */
function wpiko_chatbot_pro_add_heartbeat_needed($response_data, $thread_id, $messages, $last_id) {
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        return $response_data;
    }
    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
        return $response_data;
    }

    $is_takeover = !empty($response_data['human_takeover']);
    $admin_watching = wpiko_chatbot_pro_is_admin_watching($thread_id);
    $response_data['heartbeat_needed'] = $is_takeover || $admin_watching;

    return $response_data;
}
add_filter('wpiko_chatbot_check_new_messages_response', 'wpiko_chatbot_pro_add_heartbeat_needed', 5, 4);
