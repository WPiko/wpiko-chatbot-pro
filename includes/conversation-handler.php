<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Hook to handle license status changes
function wpiko_chatbot_pro_handle_license_change($new_status) {
    if ($new_status !== 'active') {
        // Disable auto-delete when license becomes inactive or expires
        update_option('wpiko_chatbot_enable_auto_delete', false);
        wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
        
        // Log this action for debugging purposes
        wpiko_chatbot_log('Auto-delete disabled due to license status change to: ' . $new_status, 'info');
    }
}
add_action('wpiko_chatbot_license_status_changed', 'wpiko_chatbot_pro_handle_license_change');

// Function to check and update auto-delete status regularly
function wpiko_chatbot_pro_check_auto_delete_status() {
    if (!wpiko_chatbot_is_license_active() && get_option('wpiko_chatbot_enable_auto_delete', false)) {
        // If license is not active but auto-delete is enabled, disable it
        update_option('wpiko_chatbot_enable_auto_delete', false);
        wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
        wpiko_chatbot_log('Auto-delete disabled during regular check - license inactive', 'info');
    }
}
add_action('admin_init', 'wpiko_chatbot_pro_check_auto_delete_status');

// Function to Auto delete old conversations
function wpiko_chatbot_pro_delete_old_conversations() {
    // Force check license status before running
    if (!wpiko_chatbot_is_license_active()) {
        // Ensure auto-delete is disabled if license is not active
        if (get_option('wpiko_chatbot_enable_auto_delete', false)) {
            update_option('wpiko_chatbot_enable_auto_delete', false);
            wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
            wpiko_chatbot_log('Auto-delete disabled during scheduled task - license inactive', 'info');
        }
        return;
    }
    
    // Get current license status (double check)
    $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
    
    // Check if license is active (not expired or invalid)
    if ($license_status !== 'active' || !get_option('wpiko_chatbot_enable_auto_delete', false)) {
        // If license is not active, disable auto-delete
        if ($license_status !== 'active') {
            update_option('wpiko_chatbot_enable_auto_delete', false);
            wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
            wpiko_chatbot_log('Auto-delete disabled during scheduled task - license status: ' . $license_status, 'info');
        }
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    $days = intval(get_option('wpiko_chatbot_auto_delete_days', 90));
    
    $result = $wpdb->query($wpdb->prepare(
        "DELETE FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $days
    ));
    
    if ($result !== false) {
        wpiko_chatbot_log('Successfully deleted ' . $result . ' old conversations', 'info');

        // Clean up orphaned meta rows for deleted conversations
        $meta_table = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';
        $meta_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $meta_table));
        if ($meta_exists) {
            $conv_table = $wpdb->prefix . 'wpiko_chatbot_conversations';
            $orphaned = $wpdb->query(
                "DELETE m FROM `{$meta_table}` m LEFT JOIN `{$conv_table}` c ON m.session_id = c.session_id WHERE c.session_id IS NULL"
            );
            if ($orphaned > 0) {
                wpiko_chatbot_log('Cleaned up ' . $orphaned . ' orphaned meta rows', 'info');
            }
        }
    } else {
        wpiko_chatbot_log('Error deleting old conversations', 'error');
    }
}
add_action('wpiko_chatbot_auto_delete_conversations', 'wpiko_chatbot_pro_delete_old_conversations');

/**
 * Get the latest assistant row that has already been synced to OpenAI.
 *
 * @param string $session_id The session/conversation ID.
 * @return object|null The latest synced assistant row or null.
 */
function wpiko_chatbot_pro_get_last_synced_assistant_message($session_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT id, timestamp, openai_response_id
         FROM `$table_name`
         WHERE session_id = %s
         AND role = 'assistant'
         AND openai_response_id IS NOT NULL
         AND openai_response_id != ''
         ORDER BY id DESC
         LIMIT 1",
        $session_id
    ));
}

/**
 * Normalize a takeover transcript row into compact plain text.
 *
 * @param string $message The original message text.
 * @return string Normalized text.
 */
function wpiko_chatbot_pro_normalize_takeover_message($message) {
    $message = wp_strip_all_tags((string) $message);
    $message = trim(preg_replace('/\s+/', ' ', $message));

    return $message;
}

/**
 * Clip a message for handoff payloads while preserving readability.
 *
 * @param string $message   The normalized message.
 * @param int    $max_chars Maximum characters allowed.
 * @return string
 */
function wpiko_chatbot_pro_clip_takeover_message($message, $max_chars = 280) {
    $message = (string) $message;
    $max_chars = max(40, (int) $max_chars);

    if (strlen($message) <= $max_chars) {
        return $message;
    }

    return rtrim(substr($message, 0, $max_chars - 3)) . '...';
}

/**
 * Add a bullet line if it has not already been added.
 *
 * @param array  $target Existing lines.
 * @param string $line   Candidate line.
 * @return array
 */
function wpiko_chatbot_pro_append_unique_handoff_line($target, $line) {
    $line = trim((string) $line);
    if ($line === '' || in_array($line, $target, true)) {
        return $target;
    }

    $target[] = $line;

    return $target;
}

/**
 * Pick representative messages from a role-specific subset.
 *
 * @param array  $messages   Normalized transcript rows.
 * @param string $role       user|admin
 * @param int    $max_items  Maximum number of lines to keep.
 * @param int    $clip_chars Maximum characters per line.
 * @return array
 */
function wpiko_chatbot_pro_select_takeover_role_highlights($messages, $role, $max_items = 3, $clip_chars = 220) {
    $filtered = array_values(array_filter($messages, function ($message_row) use ($role) {
        return isset($message_row['role']) && $message_row['role'] === $role;
    }));

    if (empty($filtered)) {
        return array();
    }

    $selected = array();
    $candidate_indexes = array(0);

    if (count($filtered) > 2) {
        $candidate_indexes[] = (int) floor((count($filtered) - 1) / 2);
    }

    if (count($filtered) > 1) {
        $candidate_indexes[] = count($filtered) - 1;
    }

    foreach ($candidate_indexes as $index) {
        if (!isset($filtered[$index]['message'])) {
            continue;
        }

        $selected = wpiko_chatbot_pro_append_unique_handoff_line(
            $selected,
            wpiko_chatbot_pro_clip_takeover_message($filtered[$index]['message'], $clip_chars)
        );

        if (count($selected) >= $max_items) {
            return array_slice($selected, 0, $max_items);
        }
    }

    for ($index = count($filtered) - 1; $index >= 0; $index--) {
        if (!isset($filtered[$index]['message'])) {
            continue;
        }

        $selected = wpiko_chatbot_pro_append_unique_handoff_line(
            $selected,
            wpiko_chatbot_pro_clip_takeover_message($filtered[$index]['message'], $clip_chars)
        );

        if (count($selected) >= $max_items) {
            break;
        }
    }

    return array_slice($selected, 0, $max_items);
}

/**
 * Capture the most recent open questions from the user.
 *
 * @param array $messages Normalized transcript rows.
 * @return array
 */
function wpiko_chatbot_pro_select_takeover_open_questions($messages) {
    $questions = array();

    for ($index = count($messages) - 1; $index >= 0; $index--) {
        $message_row = $messages[$index];
        if (!isset($message_row['role'], $message_row['message'])) {
            continue;
        }

        if ($message_row['role'] !== 'user' || strpos($message_row['message'], '?') === false) {
            continue;
        }

        $questions = wpiko_chatbot_pro_append_unique_handoff_line(
            $questions,
            wpiko_chatbot_pro_clip_takeover_message($message_row['message'], 220)
        );

        if (count($questions) >= 2) {
            break;
        }
    }

    return array_reverse($questions);
}

/**
 * Build a short recent excerpt from the end of the takeover transcript.
 *
 * @param array $messages    Normalized transcript rows.
 * @param int   $line_count  Number of lines to keep.
 * @param int   $clip_chars  Maximum characters per line.
 * @return array
 */
function wpiko_chatbot_pro_select_takeover_recent_excerpt($messages, $line_count = 4, $clip_chars = 180) {
    $excerpt = array();
    $recent_messages = array_slice($messages, -1 * max(1, (int) $line_count));

    foreach ($recent_messages as $message_row) {
        $label = $message_row['role'] === 'admin'
            ? (!empty($message_row['user_name']) ? 'Admin (' . $message_row['user_name'] . ')' : 'Admin')
            : 'User';

        $excerpt[] = $label . ': ' . wpiko_chatbot_pro_clip_takeover_message($message_row['message'], $clip_chars);
    }

    return $excerpt;
}

/**
 * Keep only the newest raw takeover lines within a character budget.
 *
 * @param array  $lines     Raw handoff lines.
 * @param string $intro     Introductory text.
 * @param int    $max_chars Maximum characters allowed.
 * @return string
 */
function wpiko_chatbot_pro_build_raw_takeover_handoff($lines, $intro, $max_chars) {
    $kept_lines = array();
    $current_length = strlen($intro);

    for ($index = count($lines) - 1; $index >= 0; $index--) {
        $line = $lines[$index];
        $line_length = strlen($line) + 1;

        if (!empty($kept_lines) && ($current_length + $line_length) > $max_chars) {
            break;
        }

        array_unshift($kept_lines, $line);
        $current_length += $line_length;
    }

    if (empty($kept_lines) && !empty($lines)) {
        $kept_lines[] = end($lines);
    }

    $omitted_count = count($lines) - count($kept_lines);
    if ($omitted_count > 0) {
        $intro .= 'Older live-chat messages were omitted for brevity.' . "\n";
    }

    return $intro . implode("\n", $kept_lines);
}

/**
 * Build a structured takeover summary for long live-chat sessions.
 *
 * @param array $messages   Normalized takeover transcript rows.
 * @param int   $max_chars  Maximum characters allowed.
 * @return string
 */
function wpiko_chatbot_pro_build_takeover_summary_handoff($messages, $max_chars) {
    $sections = array();
    $intro = "Live admin takeover context happened after your last assistant turn. Use this summary as conversation memory and continue naturally. Do not mention this internal handoff note unless the user asks.";

    $user_highlights = wpiko_chatbot_pro_select_takeover_role_highlights($messages, 'user', 3, 220);
    $admin_highlights = wpiko_chatbot_pro_select_takeover_role_highlights($messages, 'admin', 3, 220);
    $open_questions = wpiko_chatbot_pro_select_takeover_open_questions($messages);
    $recent_excerpt = wpiko_chatbot_pro_select_takeover_recent_excerpt($messages, 4, 180);

    $sections[] = $intro;
    $sections[] = 'Takeover summary:';
    $sections[] = '- User updates (' . count(array_values(array_filter($messages, function ($message_row) {
        return isset($message_row['role']) && $message_row['role'] === 'user';
    }))) . ' messages during takeover)';

    foreach ($user_highlights as $line) {
        $sections[] = '  * ' . $line;
    }

    if (!empty($admin_highlights)) {
        $sections[] = '- Admin responses / promises';
        foreach ($admin_highlights as $line) {
            $sections[] = '  * ' . $line;
        }
    }

    if (!empty($open_questions)) {
        $sections[] = '- Open questions to keep in mind';
        foreach ($open_questions as $line) {
            $sections[] = '  * ' . $line;
        }
    }

    if (!empty($recent_excerpt)) {
        $sections[] = '- Most recent live-chat context';
        foreach ($recent_excerpt as $line) {
            $sections[] = '  * ' . $line;
        }
    }

    $summary = implode("\n", $sections);

    if (strlen($summary) <= $max_chars) {
        return $summary;
    }

    $sections = array(
        $intro,
        'Takeover summary:',
    );

    foreach (array_slice($user_highlights, 0, 2) as $line) {
        $sections[] = '- User: ' . $line;
    }

    foreach (array_slice($admin_highlights, 0, 2) as $line) {
        $sections[] = '- Admin: ' . $line;
    }

    foreach (array_slice($open_questions, 0, 1) as $line) {
        $sections[] = '- Open question: ' . $line;
    }

    foreach (array_slice($recent_excerpt, -2) as $line) {
        $sections[] = '- Recent: ' . $line;
    }

    $summary = implode("\n", $sections);

    if (strlen($summary) <= $max_chars) {
        return $summary;
    }

    return wpiko_chatbot_pro_clip_takeover_message($summary, $max_chars);
}

/**
 * Build a compact handoff note from messages exchanged while AI was paused.
 *
 * @param string $session_id The session/conversation ID.
 * @param int    $max_chars  Maximum characters to return.
 * @param int    $max_rows   Maximum transcript rows to inspect.
 * @return string Handoff context or an empty string.
 */
function wpiko_chatbot_build_takeover_handoff_context($session_id, $max_chars = 5000, $max_rows = 300) {
    global $wpdb;

    if (empty($session_id)) {
        return '';
    }

    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    $last_synced_message = wpiko_chatbot_pro_get_last_synced_assistant_message($session_id);
    $last_synced_id = $last_synced_message ? (int) $last_synced_message->id : 0;

    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT id, role, message, timestamp, user_name
         FROM `$table_name`
         WHERE session_id = %s
         AND id > %d
         AND role IN ('user', 'admin')
         ORDER BY id ASC
         LIMIT %d",
        $session_id,
        $last_synced_id,
        max(1, (int) $max_rows)
    ));

    if (empty($messages)) {
        return '';
    }

    $takeover_detected = false;
    $lines = array();
    $normalized_messages = array();

    foreach ($messages as $message_row) {
        $role = isset($message_row->role) ? (string) $message_row->role : '';
        $user_name = isset($message_row->user_name) ? sanitize_text_field($message_row->user_name) : '';

        if ($role === 'admin') {
            $takeover_detected = true;

            if (function_exists('wpiko_chatbot_pro_get_takeover_event_type')) {
                $event_type = wpiko_chatbot_pro_get_takeover_event_type($message_row->message, $user_name);
                if (in_array($event_type, array('takeover_notice', 'release_notice'), true)) {
                    continue;
                }
            }
        }

        $clean_message = wpiko_chatbot_pro_normalize_takeover_message($message_row->message);
        if ($clean_message === '') {
            continue;
        }

        if ($role === 'admin') {
            $label = $user_name !== '' ? 'Admin (' . $user_name . ')' : 'Admin';
        } else {
            $label = 'User';
        }

        $clipped_line = $label . ': ' . wpiko_chatbot_pro_clip_takeover_message($clean_message, 220);
        $lines[] = $clipped_line;
        $normalized_messages[] = array(
            'role' => $role,
            'user_name' => $user_name,
            'message' => $clean_message,
        );
    }

    if (!$takeover_detected || empty($lines)) {
        return '';
    }

    $intro = "Live admin takeover context happened after your last assistant turn. Use it as conversation memory and continue naturally. Do not mention this internal handoff note unless the user asks.\n";
    $raw_replay_threshold = 6;
    $raw_replay_chars = 900;
    $total_raw_chars = strlen(implode("\n", $lines));

    if (count($normalized_messages) <= $raw_replay_threshold && $total_raw_chars <= $raw_replay_chars) {
        wpiko_chatbot_log('Using raw takeover handoff replay for session ' . $session_id, 'info');
        return wpiko_chatbot_pro_build_raw_takeover_handoff($lines, $intro, $max_chars);
    }

    wpiko_chatbot_log('Using summarized takeover handoff for session ' . $session_id, 'info');

    return wpiko_chatbot_pro_build_takeover_summary_handoff($normalized_messages, $max_chars);
}

// Handle plugin activation/deactivation
function wpiko_chatbot_pro_schedule_auto_delete() {
    if (wpiko_chatbot_is_license_active() && get_option('wpiko_chatbot_enable_auto_delete', false)) {
        if (!wp_next_scheduled('wpiko_chatbot_auto_delete_conversations')) {
            wp_schedule_event(time(), 'daily', 'wpiko_chatbot_auto_delete_conversations');
        }
    }
}
add_action('wp', 'wpiko_chatbot_pro_schedule_auto_delete');

// Clear scheduled event on plugin deactivation
function wpiko_chatbot_pro_clear_auto_delete_schedule() {
    wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
}
register_deactivation_hook(__FILE__, 'wpiko_chatbot_pro_clear_auto_delete_schedule');