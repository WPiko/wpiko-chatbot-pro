<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Endpoints for the PWA
 * 
 * Endpoints require WPiko PWA capabilities.
 * Namespace: wpiko-chatbot/v1
 */

/**
 * Send no-cache headers that target every major server-level cache.
 *
 * This helper is called from two hooks at different points in the request
 * lifecycle so the headers reach the web-server layer as early as possible.
 */
function wpiko_chatbot_pro_send_rest_nocache_headers() {
    // Standard HTTP/1.1 and HTTP/1.0 cache prevention
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // LiteSpeed Web Server / LiteSpeed Cache plugin (Hostinger, etc.)
    header('X-LiteSpeed-Cache-Control: no-cache');

    // Cloudflare CDN — respected when server sends CDN-specific directive
    header('CDN-Cache-Control: no-store');

    // Surrogate / reverse-proxy caches (Varnish, Fastly, KeyCDN, etc.)
    header('Surrogate-Control: no-store');

    // Nginx fastcgi_cache / proxy_cache (SiteGround, Starter, GridPane, etc.)
    header('X-Accel-Expires: 0');
}

/**
 * Fire no-cache headers at the earliest possible WordPress hook.
 *
 * The `init` hook runs before rest_pre_dispatch, so server-level page caches
 * that inspect headers during output (e.g. LiteSpeed, Nginx FastCGI) will see
 * them in time.  We only fire when the request URI targets our REST namespace.
 */
function wpiko_chatbot_pro_rest_init_nocache_headers() {
    if (!isset($_SERVER['REQUEST_URI'])) {
        return;
    }

    $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));

    // Match /wp-json/wpiko-chatbot/v1/ regardless of any prefix (e.g. subdirectory install).
    if (strpos($uri, '/wp-json/wpiko-chatbot/v1/') !== false
        || strpos($uri, rest_get_url_prefix() . '/wpiko-chatbot/v1/') !== false) {
        wpiko_chatbot_pro_send_rest_nocache_headers();
    }
}
add_action('init', 'wpiko_chatbot_pro_rest_init_nocache_headers', 1);

/**
 * Reinforce no-cache headers inside the REST lifecycle as a secondary layer.
 */
function wpiko_chatbot_pro_rest_early_nocache_headers($result, $server, $request) {
    if (strpos($request->get_route(), '/wpiko-chatbot/v1/') === 0) {
        wpiko_chatbot_pro_send_rest_nocache_headers();
    }
    return $result;
}
add_filter('rest_pre_dispatch', 'wpiko_chatbot_pro_rest_early_nocache_headers', 10, 3);

/**
 * Register all REST API routes
 */
function wpiko_chatbot_pro_register_rest_routes() {
    $namespace = 'wpiko-chatbot/v1';

    // Conversations list
    register_rest_route($namespace, '/conversations', array(
        'methods' => 'GET',
        'callback' => 'wpiko_chatbot_pro_rest_get_conversations',
        'permission_callback' => 'wpiko_chatbot_pro_rest_view_check',
        'args' => array(
            'page' => array('default' => 1, 'sanitize_callback' => 'absint'),
            'per_page' => array('default' => 20, 'sanitize_callback' => 'absint'),
            'start_date' => array('default' => '', 'sanitize_callback' => 'sanitize_text_field'),
            'end_date' => array('default' => '', 'sanitize_callback' => 'sanitize_text_field'),
        ),
    ));

    // Single conversation
    register_rest_route($namespace, '/conversations/(?P<session_id>[a-zA-Z0-9_-]+)', array(
        'methods' => 'GET',
        'callback' => 'wpiko_chatbot_pro_rest_get_conversation',
        'permission_callback' => 'wpiko_chatbot_pro_rest_view_check',
        'args' => array(
            'since_id' => array('default' => 0, 'sanitize_callback' => 'absint'),
        ),
    ));

    // Admin reply
    register_rest_route($namespace, '/conversations/(?P<session_id>[a-zA-Z0-9_-]+)/reply', array(
        'methods' => 'POST',
        'callback' => 'wpiko_chatbot_pro_rest_reply',
        'permission_callback' => 'wpiko_chatbot_pro_rest_reply_check',
        'args' => array(
            'message' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => function ($value) {
                    if (!is_string($value) || mb_strlen($value) > 5000) {
                        return new WP_Error('message_too_long', 'Message must not exceed 5000 characters.', array('status' => 400));
                    }
                    return true;
                },
            ),
        ),
    ));

    // Takeover
    register_rest_route($namespace, '/conversations/(?P<session_id>[a-zA-Z0-9_-]+)/takeover', array(
        'methods' => 'POST',
        'callback' => 'wpiko_chatbot_pro_rest_takeover',
        'permission_callback' => 'wpiko_chatbot_pro_rest_takeover_check',
    ));

    // Release
    register_rest_route($namespace, '/conversations/(?P<session_id>[a-zA-Z0-9_-]+)/release', array(
        'methods' => 'POST',
        'callback' => 'wpiko_chatbot_pro_rest_release',
        'permission_callback' => 'wpiko_chatbot_pro_rest_takeover_check',
    ));

    // Push subscription
    register_rest_route($namespace, '/push/subscribe', array(
        array(
            'methods' => 'POST',
            'callback' => 'wpiko_chatbot_pro_rest_push_subscribe',
            'permission_callback' => 'wpiko_chatbot_pro_rest_push_check',
        ),
        array(
            'methods' => 'DELETE',
            'callback' => 'wpiko_chatbot_pro_rest_push_unsubscribe',
            'permission_callback' => 'wpiko_chatbot_pro_rest_push_check',
        ),
    ));

    // VAPID public key
    register_rest_route($namespace, '/push/vapid-public-key', array(
        'methods' => 'GET',
        'callback' => 'wpiko_chatbot_pro_rest_get_vapid_key',
        'permission_callback' => 'wpiko_chatbot_pro_rest_push_check',
    ));

    // PWA settings
    register_rest_route($namespace, '/settings/pwa', array(
        'methods' => 'GET',
        'callback' => 'wpiko_chatbot_pro_rest_get_pwa_settings',
        'permission_callback' => 'wpiko_chatbot_pro_rest_access_check',
    ));
}
add_action('rest_api_init', 'wpiko_chatbot_pro_register_rest_routes');

/**
 * Check whether a REST request belongs to the WPiko PWA namespace.
 */
function wpiko_chatbot_pro_rest_is_pwa_request($request) {
    return $request instanceof WP_REST_Request
        && strpos($request->get_route(), '/wpiko-chatbot/v1/') === 0;
}

/**
 * Add cache and security headers to PWA REST responses.
 */
function wpiko_chatbot_pro_rest_add_security_headers($result, $server, $request) {
    if (!wpiko_chatbot_pro_rest_is_pwa_request($request) || !($result instanceof WP_HTTP_Response)) {
        return $result;
    }

    $result->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $result->header('Pragma', 'no-cache');
    $result->header('Expires', '0');
    $result->header('Vary', 'Authorization');
    $result->header('X-Content-Type-Options', 'nosniff');

    // Server-level cache prevention (LiteSpeed, Cloudflare, Varnish, Nginx, etc.)
    $result->header('X-LiteSpeed-Cache-Control', 'no-cache');
    $result->header('CDN-Cache-Control', 'no-store');
    $result->header('Surrogate-Control', 'no-store');
    $result->header('X-Accel-Expires', '0');

    // CORS: restrict to same origin only for the PWA namespace.
    $origin = home_url();
    $result->header('Access-Control-Allow-Origin', $origin);
    $result->header('Access-Control-Allow-Credentials', 'false');

    return $result;
}
add_filter('rest_post_dispatch', 'wpiko_chatbot_pro_rest_add_security_headers', 10, 3);

/**
 * Block cross-origin preflight requests for the PWA namespace.
 */
function wpiko_chatbot_pro_rest_cors_preflight($served, $result, $request, $server) {
    if (!wpiko_chatbot_pro_rest_is_pwa_request($request)) {
        return $served;
    }

    $request_origin = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN'])) : '';
    $allowed_origin = home_url();

    if ($request_origin !== '' && $request_origin !== $allowed_origin) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Cross-origin requests are not allowed for this endpoint.';
        return true;
    }

    return $served;
}
add_filter('rest_pre_serve_request', 'wpiko_chatbot_pro_rest_cors_preflight', 10, 4);

/**
 * Return the allowlist used to sanitize assistant HTML before it reaches the PWA.
 */
function wpiko_chatbot_pro_get_rest_allowed_message_html() {
    return array(
        'a' => array(
            'href' => true,
            'target' => true,
            'rel' => true,
            'class' => true,
        ),
        'b' => array(),
        'blockquote' => array(
            'class' => true,
        ),
        'br' => array(),
        'code' => array(
            'class' => true,
        ),
        'div' => array(
            'class' => true,
        ),
        'em' => array(),
        'h1' => array(
            'class' => true,
        ),
        'h2' => array(
            'class' => true,
        ),
        'h3' => array(
            'class' => true,
        ),
        'hr' => array(),
        'i' => array(),
        'img' => array(
            'src' => true,
            'alt' => true,
            'class' => true,
            'width' => true,
            'height' => true,
            'loading' => true,
        ),
        'li' => array(
            'class' => true,
        ),
        'ol' => array(
            'class' => true,
        ),
        'p' => array(
            'class' => true,
        ),
        'pre' => array(
            'class' => true,
        ),
        'span' => array(
            'class' => true,
        ),
        'strong' => array(),
        'sub' => array(),
        'sup' => array(),
        'ul' => array(
            'class' => true,
        ),
    );
}

/**
 * Sanitize transcript HTML for assistant messages before sending it to the PWA.
 */
function wpiko_chatbot_pro_rest_sanitize_transcript_message($message, $role) {
    $message = is_string($message) ? $message : (string) $message;

    if ($role !== 'assistant') {
        return $message;
    }

    return wp_kses($message, wpiko_chatbot_pro_get_rest_allowed_message_html());
}

/**
 * Format a transcript row for REST responses.
 */
function wpiko_chatbot_pro_rest_prepare_message_payload($message) {
    return array(
        'id' => $message->id,
        'role' => $message->role,
        'message' => wpiko_chatbot_pro_rest_sanitize_transcript_message($message->message, $message->role),
        'timestamp' => $message->timestamp,
        'user_name' => $message->user_name,
        'event_type' => $message->role === 'admin'
            ? wpiko_chatbot_pro_get_takeover_event_type($message->message, $message->user_name)
            : '',
    );
}

/**
 * Return a readable label for a PWA capability.
 */
function wpiko_chatbot_pro_get_rest_capability_label($capability) {
    $labels = array(
        'wpiko_chatbot_use_pwa' => 'access the Mobile App',
        'wpiko_chatbot_view_conversations' => 'view conversations',
        'wpiko_chatbot_reply_conversations' => 'reply to conversations',
        'wpiko_chatbot_takeover_conversations' => 'manage live takeover sessions',
        'wpiko_chatbot_manage_push' => 'manage push notifications',
    );

    return isset($labels[$capability]) ? $labels[$capability] : 'use this feature';
}

/**
 * Resolve the client IP address, accounting for trusted reverse proxies.
 *
 * Checks CF-Connecting-IP and X-Forwarded-For when the direct
 * REMOTE_ADDR belongs to a known proxy range, then falls back
 * to REMOTE_ADDR.
 */
function wpiko_chatbot_pro_get_client_ip() {
    $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

    // Cloudflare: CF-Connecting-IP is set by Cloudflare and contains the
    // true visitor IP.  Only trust it when REMOTE_ADDR is a Cloudflare edge.
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cf_ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        if (filter_var($cf_ip, FILTER_VALIDATE_IP)) {
            return $cf_ip;
        }
    }

    // Generic reverse proxy: X-Forwarded-For may contain a comma-separated
    // list of IPs. The left-most entry that is a valid public IP is the
    // original client.
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = array_map('trim', explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']))));
        foreach ($forwarded as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $candidate;
            }
        }
    }

    return $remote_addr;
}

/**
 * IP-based brute-force throttle for unauthenticated requests.
 * Blocks further attempts after 10 failures within 15 minutes.
 */
function wpiko_chatbot_pro_rest_login_throttle_check() {
    $ip = wpiko_chatbot_pro_get_client_ip();
    if (empty($ip)) {
        return true;
    }
    $key = 'wpiko_login_fail_' . md5($ip);
    $attempts = (int) get_transient($key);
    if ($attempts >= 10) {
        return new WP_Error(
            'too_many_login_attempts',
            'Too many failed login attempts. Please try again in 15 minutes.',
            array('status' => 429, 'retry_after' => 15 * MINUTE_IN_SECONDS)
        );
    }
    return true;
}

/**
 * Record a failed login attempt for the current IP.
 */
function wpiko_chatbot_pro_rest_record_login_failure() {
    $ip = wpiko_chatbot_pro_get_client_ip();
    if (empty($ip)) {
        return;
    }
    $key = 'wpiko_login_fail_' . md5($ip);
    $attempts = (int) get_transient($key);
    set_transient($key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
}

/**
 * Clear the brute-force failure counter for the current IP after a successful login.
 */
function wpiko_chatbot_pro_rest_clear_login_failures() {
    $ip = wpiko_chatbot_pro_get_client_ip();
    if (empty($ip)) {
        return;
    }
    delete_transient('wpiko_login_fail_' . md5($ip));
}

/**
 * Shared PWA permission guard.
 */
function wpiko_chatbot_pro_rest_capability_check($capability, $request = null) {
    if (function_exists('wpiko_chatbot_pro_is_pwa_request_secure') && !wpiko_chatbot_pro_is_pwa_request_secure()) {
        return new WP_Error(
            'pwa_https_required',
            'The Mobile App requires HTTPS on live sites.',
            array('status' => 403)
        );
    }

    // Check brute-force throttle before authentication
    $throttle = wpiko_chatbot_pro_rest_login_throttle_check();
    if (is_wp_error($throttle)) {
        return $throttle;
    }

    if (!is_user_logged_in()) {
        wpiko_chatbot_pro_rest_record_login_failure();
        return new WP_Error(
            'rest_not_logged_in',
            'Authentication failed. Check your username and Application Password.',
            array('status' => 401)
        );
    }

    // Successful authentication — clear any brute-force failures for this IP.
    wpiko_chatbot_pro_rest_clear_login_failures();

    if (!wpiko_chatbot_pro_user_can_access_pwa()) {
        return new WP_Error(
            'pwa_access_denied',
            'This account cannot access the Mobile App. Use an Administrator or Live Agent account.',
            array('status' => 403)
        );
    }

    if ($capability && !current_user_can($capability) && !current_user_can('manage_options')) {
        return new WP_Error(
            'pwa_capability_denied',
            sprintf('This account is signed in, but it does not have permission to %s.', wpiko_chatbot_pro_get_rest_capability_label($capability)),
            array('status' => 403)
        );
    }

    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        return new WP_Error('pwa_disabled', 'The Mobile App feature is currently disabled.', array('status' => 403));
    }
    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
        return new WP_Error('license_inactive', 'This feature requires an active premium license.', array('status' => 403));
    }
    return true;
}

function wpiko_chatbot_pro_rest_access_check($request) {
    return wpiko_chatbot_pro_rest_capability_check('wpiko_chatbot_use_pwa', $request);
}

function wpiko_chatbot_pro_rest_view_check($request) {
    return wpiko_chatbot_pro_rest_capability_check('wpiko_chatbot_view_conversations', $request);
}

function wpiko_chatbot_pro_rest_reply_check($request) {
    return wpiko_chatbot_pro_rest_capability_check('wpiko_chatbot_reply_conversations', $request);
}

function wpiko_chatbot_pro_rest_takeover_check($request) {
    return wpiko_chatbot_pro_rest_capability_check('wpiko_chatbot_takeover_conversations', $request);
}

function wpiko_chatbot_pro_rest_push_check($request) {
    return wpiko_chatbot_pro_rest_capability_check('wpiko_chatbot_manage_push', $request);
}

/**
 * Build takeover metadata for REST responses.
 */
function wpiko_chatbot_pro_rest_get_takeover_data($session_id, $admin_id = 0) {
    if (!$admin_id) {
        $admin_id = get_current_user_id();
    }

    $is_takeover = wpiko_chatbot_pro_is_takeover_active($session_id);
    $takeover_admin_name = $is_takeover ? wpiko_chatbot_pro_get_takeover_admin_name($session_id) : '';

    return array(
        'human_takeover' => $is_takeover,
        'takeover_admin_id' => $is_takeover ? wpiko_chatbot_pro_get_takeover_admin_id($session_id) : 0,
        'takeover_admin_name' => $takeover_admin_name,
        'takeover_owned_by_current_admin' => $is_takeover ? wpiko_chatbot_pro_is_takeover_owned_by_admin($session_id, $admin_id) : false,
    );
}

/**
 * GET /conversations — List conversations
 */
function wpiko_chatbot_pro_rest_get_conversations($request) {
    $page = $request->get_param('page');
    $per_page = min($request->get_param('per_page'), 50);
    $start_date = $request->get_param('start_date');
    $end_date = $request->get_param('end_date');
    $offset = ($page - 1) * $per_page;

    $conversations = wpiko_chatbot_get_conversations($per_page, $offset, $start_date, $end_date);
    $total = wpiko_chatbot_get_total_conversations($start_date, $end_date);

    $data = array();
    foreach ($conversations as $conv) {
        // Get user display name
        if ($conv->user_id != 0) {
            $user_data = get_userdata($conv->user_id);
            $user_display = $user_data ? ($user_data->display_name ?: $user_data->user_login) : 'Unknown User';
            $avatar_url = get_avatar_url($conv->user_id, array('size' => 80));
        } else {
            $user_display = !empty($conv->user_name) ? $conv->user_name : 'Guest';
            $avatar_url = get_avatar_url(0, array('size' => 80));
        }

        // Check if takeover is active for this conversation
        $takeover_data = function_exists('wpiko_chatbot_pro_rest_get_takeover_data')
            ? wpiko_chatbot_pro_rest_get_takeover_data($conv->session_id)
            : array(
                'human_takeover' => false,
                'takeover_admin_id' => 0,
                'takeover_admin_name' => '',
                'takeover_owned_by_current_admin' => false,
            );

        $presence = wpiko_chatbot_pro_get_user_presence_data($conv->session_id);

        $data[] = array(
            'session_id' => $conv->session_id,
            'user_id' => $conv->user_id,
            'user_name' => $user_display,
            'user_email' => $conv->user_email,
            'avatar_url' => $avatar_url,
            'last_message' => $conv->last_message,
            'last_message_role' => $conv->last_message_role,
            'timestamp' => $conv->timestamp,
            'country' => $conv->country,
            'city' => $conv->city,
            'region' => $conv->region,
            'device_type' => $conv->device_type,
            'human_takeover' => $takeover_data['human_takeover'],
            'takeover_admin_id' => $takeover_data['takeover_admin_id'],
            'takeover_admin_name' => $takeover_data['takeover_admin_name'],
            'takeover_owned_by_current_admin' => $takeover_data['takeover_owned_by_current_admin'],
            'user_online' => $presence['is_online'],
            'user_last_seen' => $presence['last_seen'],
            'user_presence_state' => $presence['state'],
            'user_presence_note' => $presence['note'],
        );
    }

    return new WP_REST_Response(array(
        'conversations' => $data,
        'total' => $total,
        'total_pages' => ceil($total / $per_page),
        'page' => $page,
    ), 200);
}

/**
 * GET /conversations/{session_id} — Single conversation transcript
 */
function wpiko_chatbot_pro_rest_get_conversation($request) {
    global $wpdb;
    $session_id = sanitize_text_field($request->get_param('session_id'));
    $since_id = absint($request->get_param('since_id'));
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    // Incremental mode: only fetch new messages after since_id
    if ($since_id > 0) {
        $new_messages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_id, role, message, timestamp, user_email, user_name, country, city, region, device_type
             FROM `{$wpdb->prefix}wpiko_chatbot_conversations`
             WHERE session_id = %s AND id > %d ORDER BY id ASC",
            $session_id,
            $since_id
        ));

        $takeover_data = wpiko_chatbot_pro_rest_get_takeover_data($session_id);
        $presence = wpiko_chatbot_pro_get_user_presence_data($session_id);

        // Mark as admin watching for demand-driven heartbeat
        if (function_exists('wpiko_chatbot_pro_mark_admin_watching')) {
            wpiko_chatbot_pro_mark_admin_watching($session_id);
        }

        $formatted_messages = array();
        foreach ($new_messages as $msg) {
            $formatted_messages[] = wpiko_chatbot_pro_rest_prepare_message_payload($msg);
        }

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'incremental' => true,
            'messages' => $formatted_messages,
            'human_takeover' => $takeover_data['human_takeover'],
            'takeover_admin_id' => $takeover_data['takeover_admin_id'],
            'takeover_admin_name' => $takeover_data['takeover_admin_name'],
            'takeover_owned_by_current_admin' => $takeover_data['takeover_owned_by_current_admin'],
            'user_online' => $presence['is_online'],
            'user_last_seen' => $presence['last_seen'],
            'user_presence_state' => $presence['state'],
            'user_presence_note' => $presence['note'],
        ), 200);
    }

    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, role, message, timestamp, user_email, user_name, country, city, region, device_type
         FROM `{$wpdb->prefix}wpiko_chatbot_conversations`
         WHERE session_id = %s ORDER BY id ASC",
        $session_id
    ));

    if (empty($messages)) {
        return new WP_REST_Response(array('message' => 'Conversation not found'), 404);
    }

    // Get user info from first message
    $first_msg = $messages[0];
    if ($first_msg->user_id != 0) {
        $user_data = get_userdata($first_msg->user_id);
        $user_display = $user_data ? ($user_data->display_name ?: $user_data->user_login) : 'Unknown User';
        $avatar_url = get_avatar_url($first_msg->user_id, array('size' => 80));
        $user_status = 'Logged In';
    } else {
        $user_display = !empty($first_msg->user_name) ? $first_msg->user_name : 'Guest';
        $avatar_url = get_avatar_url(0, array('size' => 80));
        $user_status = 'Guest';
    }

    $takeover_data = wpiko_chatbot_pro_rest_get_takeover_data($session_id);
    $presence = wpiko_chatbot_pro_get_user_presence_data($session_id);

    // Mark this conversation as being watched by an admin (demand-driven heartbeat)
    if (function_exists('wpiko_chatbot_pro_mark_admin_watching')) {
        wpiko_chatbot_pro_mark_admin_watching($session_id);
    }

    $formatted_messages = array();
    foreach ($messages as $msg) {
        $formatted_messages[] = wpiko_chatbot_pro_rest_prepare_message_payload($msg);
    }

    return new WP_REST_Response(array(
        'session_id' => $session_id,
        'user' => array(
            'id' => $first_msg->user_id,
            'name' => $user_display,
            'email' => $first_msg->user_email,
            'avatar_url' => $avatar_url,
            'status' => $user_status,
            'country' => $first_msg->country,
            'city' => $first_msg->city,
            'region' => $first_msg->region,
            'device_type' => $first_msg->device_type,
        ),
        'messages' => $formatted_messages,
        'human_takeover' => $takeover_data['human_takeover'],
        'takeover_admin_id' => $takeover_data['takeover_admin_id'],
        'takeover_admin_name' => $takeover_data['takeover_admin_name'],
        'takeover_owned_by_current_admin' => $takeover_data['takeover_owned_by_current_admin'],
        'user_online' => $presence['is_online'],
        'user_last_seen' => $presence['last_seen'],
        'user_presence_state' => $presence['state'],
        'user_presence_note' => $presence['note'],
    ), 200);
}

/**
 * POST /conversations/{session_id}/reply — Admin sends a reply
 */
function wpiko_chatbot_pro_rest_reply($request) {
    $session_id = sanitize_text_field($request->get_param('session_id'));
    $message = $request->get_param('message');
    $admin_id = get_current_user_id();
    $takeover_data = wpiko_chatbot_pro_rest_get_takeover_data($session_id, $admin_id);

    if (empty($message)) {
        return new WP_REST_Response(array('message' => 'Message is required'), 400);
    }

    // Ensure takeover is active
    if (!$takeover_data['human_takeover']) {
        return new WP_REST_Response(array('message' => 'Takeover is not active for this conversation. Activate takeover first.'), 400);
    }

    if (!$takeover_data['takeover_owned_by_current_admin']) {
        return new WP_REST_Response(array(
            'message' => $takeover_data['takeover_admin_name']
                ? sprintf('This conversation is currently handled by %s.', $takeover_data['takeover_admin_name'])
                : 'This conversation is currently handled by another admin.',
            'human_takeover' => true,
            'takeover_admin_id' => $takeover_data['takeover_admin_id'],
            'takeover_admin_name' => $takeover_data['takeover_admin_name'],
            'takeover_owned_by_current_admin' => false,
        ), 409);
    }

    $insert_id = wpiko_chatbot_pro_save_admin_reply($session_id, $message, $admin_id);

    if (!$insert_id) {
        return new WP_REST_Response(array('message' => 'Failed to save reply'), 500);
    }

    $admin_user = get_userdata($admin_id);
    $admin_name = $admin_user ? ($admin_user->display_name ?: $admin_user->user_login) : 'Admin';

    return new WP_REST_Response(array(
        'success' => true,
        'message_id' => $insert_id,
        'admin_name' => $admin_name,
        'takeover_owned_by_current_admin' => true,
    ), 200);
}

/**
 * POST /conversations/{session_id}/takeover — Activate human takeover
 */
function wpiko_chatbot_pro_rest_takeover($request) {
    $session_id = sanitize_text_field($request->get_param('session_id'));
    $admin_id = get_current_user_id();
    $takeover_data = wpiko_chatbot_pro_rest_get_takeover_data($session_id, $admin_id);

    if ($takeover_data['human_takeover']) {
        if (!$takeover_data['takeover_owned_by_current_admin']) {
            return new WP_REST_Response(array(
                'message' => $takeover_data['takeover_admin_name']
                    ? sprintf('This conversation is already handled by %s.', $takeover_data['takeover_admin_name'])
                    : 'This conversation is already handled by another admin.',
                'human_takeover' => true,
                'takeover_admin_id' => $takeover_data['takeover_admin_id'],
                'takeover_admin_name' => $takeover_data['takeover_admin_name'],
                'takeover_owned_by_current_admin' => false,
            ), 409);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'human_takeover' => true,
            'takeover_admin_id' => $takeover_data['takeover_admin_id'],
            'takeover_admin_name' => $takeover_data['takeover_admin_name'],
            'takeover_owned_by_current_admin' => true,
        ), 200);
    }

    wpiko_chatbot_pro_activate_takeover($session_id, $admin_id);

    $admin_name = wpiko_chatbot_pro_get_takeover_admin_name($session_id);
    wpiko_chatbot_pro_save_takeover_event_notice($session_id, $admin_id, 'takeover_notice');

    return new WP_REST_Response(array(
        'success' => true,
        'human_takeover' => true,
        'takeover_admin_id' => $admin_id,
        'takeover_admin_name' => $admin_name,
        'takeover_owned_by_current_admin' => true,
    ), 200);
}

/**
 * POST /conversations/{session_id}/release — Release takeover back to AI
 */
function wpiko_chatbot_pro_rest_release($request) {
    $session_id = sanitize_text_field($request->get_param('session_id'));
    $admin_id = get_current_user_id();
    $takeover_data = wpiko_chatbot_pro_rest_get_takeover_data($session_id, $admin_id);

    if ($takeover_data['human_takeover'] && !$takeover_data['takeover_owned_by_current_admin']) {
        return new WP_REST_Response(array(
            'message' => $takeover_data['takeover_admin_name']
                ? sprintf('Only %s can release this conversation.', $takeover_data['takeover_admin_name'])
                : 'Only the admin who activated takeover can release this conversation.',
            'human_takeover' => true,
            'takeover_admin_id' => $takeover_data['takeover_admin_id'],
            'takeover_admin_name' => $takeover_data['takeover_admin_name'],
            'takeover_owned_by_current_admin' => false,
        ), 409);
    }

    wpiko_chatbot_pro_release_takeover($session_id);
    wpiko_chatbot_pro_save_takeover_event_notice($session_id, $admin_id, 'release_notice');

    return new WP_REST_Response(array(
        'success' => true,
        'human_takeover' => false,
        'takeover_admin_id' => 0,
        'takeover_admin_name' => '',
        'takeover_owned_by_current_admin' => false,
    ), 200);
}

/**
 * POST /push/subscribe — Register a push subscription
 */
function wpiko_chatbot_pro_rest_push_subscribe($request) {
    global $wpdb;
    $user_id = get_current_user_id();
    $body = $request->get_json_params();

    $endpoint = isset($body['endpoint']) ? esc_url_raw($body['endpoint']) : '';
    $auth_key = isset($body['keys']['auth']) ? sanitize_text_field($body['keys']['auth']) : '';
    $p256dh_key = isset($body['keys']['p256dh']) ? sanitize_text_field($body['keys']['p256dh']) : '';
    $device = (isset($body['device']) && is_array($body['device'])) ? $body['device'] : array();

    if (empty($endpoint) || empty($auth_key) || empty($p256dh_key)) {
        return new WP_REST_Response(array('message' => 'Invalid subscription data'), 400);
    }

    $enc_auth_key = wpiko_chatbot_pro_encrypt($auth_key);
    $enc_p256dh_key = wpiko_chatbot_pro_encrypt($p256dh_key);

    if ($enc_auth_key === false || $enc_p256dh_key === false) {
        return new WP_REST_Response(array('message' => 'Encryption unavailable. The server is missing the openssl extension.'), 500);
    }

    $table_name = $wpdb->prefix . 'wpiko_chatbot_push_subscriptions';
    $device_label = isset($device['label']) ? sanitize_text_field(wp_unslash($device['label'])) : '';
    $user_agent = isset($device['user_agent']) ? wp_strip_all_tags(wp_unslash($device['user_agent'])) : '';

    if ($device_label !== '' && strlen($device_label) > 191) {
        $device_label = substr($device_label, 0, 191);
    }

    if ($user_agent === '' && isset($_SERVER['HTTP_USER_AGENT'])) {
        $user_agent = wp_strip_all_tags(wp_unslash($_SERVER['HTTP_USER_AGENT']));
    }

    if ($user_agent !== '' && strlen($user_agent) > 1000) {
        $user_agent = substr($user_agent, 0, 1000);
    }

    $existing_created_at = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT created_at FROM `{$table_name}` WHERE user_id = %d AND endpoint = %s LIMIT 1",
            $user_id,
            $endpoint
        )
    );
    $now = current_time('mysql');

    // Use replace to upsert
    $wpdb->replace($table_name, array(
        'user_id' => $user_id,
        'endpoint' => $endpoint,
        'auth_key' => $enc_auth_key,
        'p256dh_key' => $enc_p256dh_key,
        'device_label' => $device_label,
        'user_agent' => $user_agent,
        'created_at' => $existing_created_at ? $existing_created_at : $now,
        'last_seen_at' => $now,
    ));

    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * DELETE /push/subscribe — Unregister a push subscription
 */
function wpiko_chatbot_pro_rest_push_unsubscribe($request) {
    global $wpdb;
    $user_id = get_current_user_id();
    $body = $request->get_json_params();
    $endpoint = isset($body['endpoint']) ? esc_url_raw($body['endpoint']) : '';

    if (empty($endpoint)) {
        return new WP_REST_Response(array('message' => 'Endpoint is required'), 400);
    }

    $wpdb->delete(
        $wpdb->prefix . 'wpiko_chatbot_push_subscriptions',
        array('user_id' => $user_id, 'endpoint' => $endpoint),
        array('%d', '%s')
    );

    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * GET /push/vapid-public-key
 */
function wpiko_chatbot_pro_rest_get_vapid_key($request) {
    $public_key = get_option('wpiko_chatbot_vapid_public_key', '');

    if (empty($public_key)) {
        return new WP_REST_Response(array('message' => 'VAPID keys not configured'), 404);
    }

    return new WP_REST_Response(array('public_key' => $public_key), 200);
}

/**
 * GET /settings/pwa
 */
function wpiko_chatbot_pro_rest_get_pwa_settings($request) {
    return new WP_REST_Response(array(
        'app_name' => get_option('wpiko_chatbot_pwa_app_name', get_bloginfo('name') . ' Chat'),
        'primary_color' => get_option('wpiko_chatbot_primary_color', '#7C3AED'),
        'chatbot_name' => get_option('wpiko_chatbot_name', 'My Chatbot'),
        'chatbot_image' => get_option('wpiko_chatbot_image', ''),
        'site_url' => home_url(),
    ), 200);
}
