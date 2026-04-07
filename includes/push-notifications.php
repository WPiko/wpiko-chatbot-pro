<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Push Notification System using Web Push (VAPID)
 * 
 * Uses a lightweight implementation of Web Push without external library dependencies.
 * VAPID keys are generated using PHP's openssl extension.
 */

/**
 * Generate VAPID key pair if not already set
 */
function wpiko_chatbot_pro_generate_vapid_keys() {
    $public = get_option('wpiko_chatbot_vapid_public_key', '');
    $private_raw = get_option('wpiko_chatbot_vapid_private_key', '');
    $private = wpiko_chatbot_pro_decrypt($private_raw);

    if (!empty($public) && !empty($private)) {
        return array('public' => $public, 'private' => $private);
    }

    // Generate ECDSA key pair on P-256 curve
    $key = openssl_pkey_new(array(
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ));

    if (!$key) {
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('Failed to generate VAPID keys: ' . openssl_error_string(), 'error');
        }
        return false;
    }

    $details = openssl_pkey_get_details($key);

    // Extract the raw public key (uncompressed point: 04 + x + y)
    $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $public_key_raw = "\x04" . $x . $y;

    // Extract the raw private key (d)
    $private_key_raw = str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT);

    // Base64url encode
    $public_key_b64 = wpiko_chatbot_pro_base64url_encode($public_key_raw);
    $private_key_b64 = wpiko_chatbot_pro_base64url_encode($private_key_raw);

    update_option('wpiko_chatbot_vapid_public_key', $public_key_b64, false);

    $encrypted_private = wpiko_chatbot_pro_encrypt($private_key_b64);
    if ($encrypted_private === false) {
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('Failed to encrypt VAPID private key. The openssl extension may be missing.', 'error');
        }
        return false;
    }
    update_option('wpiko_chatbot_vapid_private_key', $encrypted_private, false);

    return array('public' => $public_key_b64, 'private' => $private_key_b64);
}

function wpiko_chatbot_pro_get_push_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'wpiko_chatbot_push_subscriptions';
}

function wpiko_chatbot_pro_count_push_subscriptions() {
    global $wpdb;

    return (int) $wpdb->get_var(
        'SELECT COUNT(*) FROM `' . wpiko_chatbot_pro_get_push_table_name() . '`'
    );
}

function wpiko_chatbot_pro_format_push_datetime($date_string) {
    $date_string = (string) $date_string;

    if ($date_string === '' || $date_string === '0000-00-00 00:00:00') {
        return '';
    }

    return mysql2date(
        get_option('date_format') . ' ' . get_option('time_format'),
        $date_string,
        true
    );
}

function wpiko_chatbot_pro_guess_push_platform($user_agent) {
    $user_agent = strtolower((string) $user_agent);

    if ($user_agent === '') {
        return '';
    }

    if (strpos($user_agent, 'iphone') !== false) {
        return 'iPhone';
    }

    if (strpos($user_agent, 'ipad') !== false) {
        return 'iPad';
    }

    if (strpos($user_agent, 'android') !== false) {
        return 'Android';
    }

    if (strpos($user_agent, 'macintosh') !== false || strpos($user_agent, 'mac os x') !== false) {
        return 'Mac';
    }

    if (strpos($user_agent, 'windows') !== false) {
        return 'Windows';
    }

    if (strpos($user_agent, 'linux') !== false) {
        return 'Linux';
    }

    return '';
}

function wpiko_chatbot_pro_guess_push_browser($user_agent) {
    $user_agent = strtolower((string) $user_agent);

    if ($user_agent === '') {
        return '';
    }

    if (strpos($user_agent, 'edg/') !== false || strpos($user_agent, 'edge/') !== false) {
        return 'Edge';
    }

    if (strpos($user_agent, 'firefox') !== false || strpos($user_agent, 'fxios') !== false) {
        return 'Firefox';
    }

    if (strpos($user_agent, 'chrome') !== false || strpos($user_agent, 'crios') !== false) {
        return 'Chrome';
    }

    if (strpos($user_agent, 'safari') !== false) {
        return 'Safari';
    }

    return 'Browser';
}

function wpiko_chatbot_pro_format_push_device_label($device_label, $user_agent = '') {
    $device_label = trim((string) $device_label);

    if ($device_label !== '') {
        return $device_label;
    }

    $parts = array_filter(array(
        wpiko_chatbot_pro_guess_push_platform($user_agent),
        wpiko_chatbot_pro_guess_push_browser($user_agent),
    ));

    if (!empty($parts)) {
        return implode(' / ', array_unique($parts));
    }

    return 'Unknown device';
}

function wpiko_chatbot_pro_format_push_delivery_label($status, $timestamp = '') {
    $status = (int) $status;
    $timestamp = wpiko_chatbot_pro_format_push_datetime($timestamp);

    if ($status === 0) {
        if ($timestamp !== '') {
            return 'No delivery response on ' . $timestamp;
        }

        return 'Not tested yet';
    }

    if ($status === 201 || $status === 202) {
        $label = 'Accepted by push service';
    } elseif (in_array($status, array(404, 410), true)) {
        $label = 'Expired subscription';
    } elseif ($status >= 400) {
        $label = 'Delivery failed';
    } else {
        $label = 'HTTP ' . $status;
    }

    $label .= ' (HTTP ' . $status . ')';

    if ($timestamp !== '') {
        $label .= ' on ' . $timestamp;
    }

    return $label;
}

function wpiko_chatbot_pro_get_push_subscriptions() {
    global $wpdb;

    $table_name = wpiko_chatbot_pro_get_push_table_name();
    $rows = $wpdb->get_results(
        "SELECT * FROM `{$table_name}` ORDER BY COALESCE(last_seen_at, created_at) DESC, created_at DESC, id DESC"
    );

    if (empty($rows)) {
        return array();
    }

    $user_cache = array();
    $subscriptions = array();

    foreach ($rows as $row) {
        $user_id = isset($row->user_id) ? (int) $row->user_id : 0;

        if (!array_key_exists($user_id, $user_cache)) {
            $user = $user_id ? get_userdata($user_id) : false;
            $user_cache[$user_id] = $user ? $user->display_name : 'Unknown user';
        }

        $last_seen_text = wpiko_chatbot_pro_format_push_datetime(isset($row->last_seen_at) ? $row->last_seen_at : '');
        $created_at_text = wpiko_chatbot_pro_format_push_datetime(isset($row->created_at) ? $row->created_at : '');

        $subscriptions[] = array(
            'id' => (int) $row->id,
            'user_id' => $user_id,
            'user_name' => $user_cache[$user_id],
            'device_label' => wpiko_chatbot_pro_format_push_device_label(
                isset($row->device_label) ? $row->device_label : '',
                isset($row->user_agent) ? $row->user_agent : ''
            ),
            'endpoint_host' => wp_parse_url($row->endpoint, PHP_URL_HOST) ?: 'unknown-host',
            'endpoint_reference' => substr(md5((string) $row->endpoint), 0, 8),
            'last_seen_text' => $last_seen_text !== '' ? $last_seen_text : 'Not confirmed yet',
            'last_delivery_text' => wpiko_chatbot_pro_format_push_delivery_label(
                isset($row->last_delivery_status) ? $row->last_delivery_status : 0,
                isset($row->last_delivery_at) ? $row->last_delivery_at : ''
            ),
            'created_at_text' => $created_at_text !== '' ? $created_at_text : 'Unknown',
        );
    }

    return $subscriptions;
}

function wpiko_chatbot_pro_update_push_delivery_meta($subscription_id, $status) {
    global $wpdb;

    $subscription_id = (int) $subscription_id;

    if ($subscription_id <= 0) {
        return;
    }

    $wpdb->update(
        wpiko_chatbot_pro_get_push_table_name(),
        array(
            'last_delivery_status' => (int) $status,
            'last_delivery_at' => current_time('mysql'),
        ),
        array('id' => $subscription_id),
        array('%d', '%s'),
        array('%d')
    );
}

function wpiko_chatbot_pro_delete_push_subscription_by_id($subscription_id) {
    global $wpdb;

    $deleted = $wpdb->delete(
        wpiko_chatbot_pro_get_push_table_name(),
        array('id' => (int) $subscription_id),
        array('%d')
    );

    return $deleted !== false && $deleted > 0;
}

/**
 * Send a push notification to all subscribed admins
 */
function wpiko_chatbot_pro_send_push_notification($title, $body, $data = array()) {
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        return array(
            'success' => false,
            'reason' => 'pwa_disabled',
            'subscription_count' => 0,
            'remaining_subscription_count' => 0,
            'success_count' => 0,
            'removed_count' => 0,
            'removed_subscription_ids' => array(),
            'results' => array(),
        );
    }

    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
        return array(
            'success' => false,
            'reason' => 'license_inactive',
            'subscription_count' => 0,
            'remaining_subscription_count' => 0,
            'success_count' => 0,
            'removed_count' => 0,
            'removed_subscription_ids' => array(),
            'results' => array(),
        );
    }

    if (!get_option('wpiko_chatbot_pwa_push_enabled', '1')) {
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('Push notification skipped because push notifications are disabled in PWA settings.', 'info');
        }
        return array(
            'success' => false,
            'reason' => 'disabled',
            'subscription_count' => 0,
            'remaining_subscription_count' => 0,
            'success_count' => 0,
            'removed_count' => 0,
            'removed_subscription_ids' => array(),
            'results' => array(),
        );
    }

    $keys = wpiko_chatbot_pro_generate_vapid_keys();
    if (!$keys) {
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('Push notification skipped because VAPID keys could not be generated.', 'error');
        }
        return array(
            'success' => false,
            'reason' => 'vapid_generation_failed',
            'subscription_count' => 0,
            'remaining_subscription_count' => 0,
            'success_count' => 0,
            'removed_count' => 0,
            'removed_subscription_ids' => array(),
            'results' => array(),
        );
    }

    global $wpdb;
    $table_name = wpiko_chatbot_pro_get_push_table_name();
    $subscriptions = $wpdb->get_results(
        "SELECT * FROM `{$table_name}`"
    );

    if (empty($subscriptions)) {
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('Push notification skipped because no PWA subscriptions were found.', 'info');
        }
        return array(
            'success' => false,
            'reason' => 'no_subscriptions',
            'subscription_count' => 0,
            'remaining_subscription_count' => 0,
            'success_count' => 0,
            'removed_count' => 0,
            'removed_subscription_ids' => array(),
            'results' => array(),
        );
    }

    if (function_exists('wpiko_chatbot_log')) {
        wpiko_chatbot_log(
            'Sending push notification to ' . count($subscriptions) . ' subscription(s). Title: ' . $title,
            'info'
        );
    }

    $payload = wp_json_encode(array(
        'title' => $title,
        'body' => $body,
        'data' => $data,
        'icon' => get_option('wpiko_chatbot_image', ''),
        'timestamp' => time(),
    ));

    $results = array();
    $success_count = 0;
    $removed_subscription_ids = array();

    foreach ($subscriptions as $sub) {
        $dec_p256dh = wpiko_chatbot_pro_decrypt($sub->p256dh_key);
        $dec_auth   = wpiko_chatbot_pro_decrypt($sub->auth_key);

        $result = wpiko_chatbot_pro_send_web_push(
            $sub->endpoint,
            $dec_p256dh,
            $dec_auth,
            $payload,
            $keys
        );

        if (function_exists('wpiko_chatbot_log')) {
            $endpoint_host = wp_parse_url($sub->endpoint, PHP_URL_HOST);
            $status = is_array($result) && isset($result['status']) ? (string) $result['status'] : 'unknown';
            $error = is_array($result) && !empty($result['error']) ? ' Error: ' . $result['error'] : '';
            wpiko_chatbot_log(
                'Push delivery result for subscription #' . $sub->id . ' (' . ($endpoint_host ?: 'unknown-host') . '): HTTP ' . $status . '.' . $error,
                ($status === '201' || $status === '202') ? 'info' : 'warning'
            );
        }

        $results[] = array(
            'subscription_id' => (int) $sub->id,
            'endpoint_host' => wp_parse_url($sub->endpoint, PHP_URL_HOST),
            'status' => is_array($result) && isset($result['status']) ? (int) $result['status'] : 0,
            'error' => is_array($result) && isset($result['error']) ? $result['error'] : '',
        );

        if (is_array($result) && isset($result['status']) && in_array((int) $result['status'], array(201, 202), true)) {
            $success_count++;
        }

        $status = is_array($result) && isset($result['status']) ? (int) $result['status'] : 0;

        if (in_array($status, array(404, 410), true)) {
            if (wpiko_chatbot_pro_delete_push_subscription_by_id((int) $sub->id)) {
                $removed_subscription_ids[] = (int) $sub->id;
            }

            continue;
        }

        wpiko_chatbot_pro_update_push_delivery_meta((int) $sub->id, $status);
    }

    return array(
        'success' => $success_count > 0,
        'reason' => $success_count > 0 ? 'delivered' : 'delivery_failed',
        'subscription_count' => count($subscriptions),
        'remaining_subscription_count' => max(0, count($subscriptions) - count($removed_subscription_ids)),
        'success_count' => $success_count,
        'removed_count' => count($removed_subscription_ids),
        'removed_subscription_ids' => $removed_subscription_ids,
        'results' => $results,
    );
}

/**
 * Send a single Web Push message
 * Implements RFC 8291 (Message Encryption) and RFC 8292 (VAPID)
 */
function wpiko_chatbot_pro_send_web_push($endpoint, $client_public_key_b64, $client_auth_b64, $payload, $vapid_keys) {
    // Decode client keys
    $client_public_key = wpiko_chatbot_pro_base64url_decode($client_public_key_b64);
    $client_auth = wpiko_chatbot_pro_base64url_decode($client_auth_b64);

    // Generate server ECDH keypair for encryption
    $server_key = openssl_pkey_new(array(
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ));

    if (!$server_key) {
        return array('status' => 0, 'error' => 'Failed to generate server key');
    }

    $server_details = openssl_pkey_get_details($server_key);
    $server_x = str_pad($server_details['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $server_y = str_pad($server_details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $server_public_key = "\x04" . $server_x . $server_y;

    // Compute shared secret via ECDH
    $shared_secret = wpiko_chatbot_pro_ecdh_compute($server_key, $client_public_key);
    if (!$shared_secret) {
        return array('status' => 0, 'error' => 'ECDH computation failed');
    }

    // Derive encryption key and nonce using HKDF
    // PRK = HKDF-Extract(auth, shared_secret)
    $prk_key = hash_hmac('sha256', $shared_secret, $client_auth, true);

    // Info for Content-Encryption-Key
    $cek_info = "WebPush: info\x00" . $client_public_key . $server_public_key;
    $ikm = wpiko_chatbot_pro_hkdf_expand($prk_key, $cek_info, 32);

    // Generate salt
    $salt = openssl_random_pseudo_bytes(16);

    // PRK for final keys
    $prk = hash_hmac('sha256', $ikm, $salt, true);

    // Content-Encryption-Key
    $cek_info_final = "Content-Encoding: aes128gcm\x00";
    $cek = wpiko_chatbot_pro_hkdf_expand($prk, $cek_info_final, 16);

    // Nonce
    $nonce_info = "Content-Encoding: nonce\x00";
    $nonce = wpiko_chatbot_pro_hkdf_expand($prk, $nonce_info, 12);

    // Encrypt payload with AES-128-GCM
    // Add padding: delimiter byte 0x02
    $padded_payload = $payload . "\x02";

    $encrypted = openssl_encrypt(
        $padded_payload,
        'aes-128-gcm',
        $cek,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        '',
        16
    );

    if ($encrypted === false) {
        return array('status' => 0, 'error' => 'Encryption failed');
    }

    // Build the aes128gcm encrypted content
    // Header: salt (16 bytes) + record size (4 bytes) + keyid length (1 byte) + keyid (server public key, 65 bytes)
    $record_size = pack('N', 4096);
    $keyid_length = chr(65);
    $body = $salt . $record_size . $keyid_length . $server_public_key . $encrypted . $tag;

    // Create VAPID Authorization header (JWT)
    $jwt = wpiko_chatbot_pro_create_vapid_jwt($endpoint, $vapid_keys);

    $headers = array(
        'Content-Type' => 'application/octet-stream',
        'Content-Encoding' => 'aes128gcm',
        'TTL' => '86400',
        'Urgency' => 'high',
        'Authorization' => 'vapid t=' . $jwt . ', k=' . $vapid_keys['public'],
    );

    $response = wp_remote_post($endpoint, array(
        'headers' => $headers,
        'body' => $body,
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        return array('status' => 0, 'error' => $response->get_error_message());
    }

    return array('status' => wp_remote_retrieve_response_code($response));
}

/**
 * Compute ECDH shared secret
 */
function wpiko_chatbot_pro_ecdh_compute($private_key_resource, $peer_public_key_raw) {
    // Use openssl_pkey_derive if available (PHP 7.3+)
    if (function_exists('openssl_pkey_derive')) {
        // Import the peer public key
        $peer_pem = wpiko_chatbot_pro_ec_raw_to_pem($peer_public_key_raw);
        if (!$peer_pem) {
            return false;
        }

        $peer_key = openssl_pkey_get_public($peer_pem);
        if (!$peer_key) {
            return false;
        }

        $shared_secret = openssl_pkey_derive($peer_key, $private_key_resource, 32);
        return $shared_secret !== false ? $shared_secret : false;
    }

    return false;
}

/**
 * Convert raw EC public key to PEM format
 */
function wpiko_chatbot_pro_ec_raw_to_pem($raw_key) {
    if (strlen($raw_key) !== 65 || $raw_key[0] !== "\x04") {
        return false;
    }

    // ASN.1 structure for P-256 public key
    $asn1_header = hex2bin(
        '3059301306072a8648ce3d020106082a8648ce3d030107034200'
    );

    $der = $asn1_header . $raw_key;
    $pem = "-----BEGIN PUBLIC KEY-----\n" .
           chunk_split(base64_encode($der), 64, "\n") .
           "-----END PUBLIC KEY-----\n";

    return $pem;
}

/**
 * HKDF-Expand
 */
function wpiko_chatbot_pro_hkdf_expand($prk, $info, $length) {
    $t = '';
    $last_block = '';
    $block_index = 1;

    while (strlen($t) < $length) {
        $last_block = hash_hmac('sha256', $last_block . $info . chr($block_index), $prk, true);
        $t .= $last_block;
        $block_index++;
    }

    return substr($t, 0, $length);
}

/**
 * Create VAPID JWT token
 */
function wpiko_chatbot_pro_create_vapid_jwt($endpoint, $vapid_keys) {
    $parsed = wp_parse_url($endpoint);
    $audience = $parsed['scheme'] . '://' . $parsed['host'];

    $header = array('typ' => 'JWT', 'alg' => 'ES256');
    $payload = array(
        'aud' => $audience,
        'exp' => time() + 86400,
        'sub' => 'mailto:' . get_option('admin_email'),
    );

    $header_b64 = wpiko_chatbot_pro_base64url_encode(wp_json_encode($header));
    $payload_b64 = wpiko_chatbot_pro_base64url_encode(wp_json_encode($payload));
    $signing_input = $header_b64 . '.' . $payload_b64;

    // Sign with VAPID private key
    $private_key_raw = wpiko_chatbot_pro_base64url_decode($vapid_keys['private']);

    // Build PEM from raw private key
    $pem = wpiko_chatbot_pro_ec_private_to_pem($private_key_raw, $vapid_keys['public']);
    if (!$pem) {
        return '';
    }

    $key = openssl_pkey_get_private($pem);
    if (!$key) {
        return '';
    }

    $signature = '';
    $result = openssl_sign($signing_input, $signature, $key, OPENSSL_ALGO_SHA256);
    if (!$result) {
        return '';
    }

    // Convert DER signature to raw r||s (64 bytes)
    $raw_sig = wpiko_chatbot_pro_der_to_raw($signature);

    return $signing_input . '.' . wpiko_chatbot_pro_base64url_encode($raw_sig);
}

/**
 * Convert raw EC private key to PEM
 */
function wpiko_chatbot_pro_ec_private_to_pem($private_key_raw, $public_key_b64) {
    $public_key_raw = wpiko_chatbot_pro_base64url_decode($public_key_b64);

    // Build ASN.1 structure for EC private key (SEC 1 format)
    // SEQUENCE {
    //   INTEGER 1 (version)
    //   OCTET STRING (private key)
    //   [0] OID prime256v1
    //   [1] BIT STRING (public key)
    // }

    $oid = hex2bin('06082a8648ce3d030107'); // OID for prime256v1
    $version = hex2bin('020101'); // INTEGER 1

    $private_octet = "\x04\x20" . $private_key_raw;
    $oid_tagged = "\xa0" . chr(strlen($oid)) . $oid;
    $public_bitstring = "\x03" . chr(strlen($public_key_raw) + 1) . "\x00" . $public_key_raw;
    $public_tagged = "\xa1" . chr(strlen($public_bitstring)) . $public_bitstring;

    $sequence_content = $version . $private_octet . $oid_tagged . $public_tagged;
    $sequence = "\x30" . wpiko_chatbot_pro_asn1_length(strlen($sequence_content)) . $sequence_content;

    $pem = "-----BEGIN EC PRIVATE KEY-----\n" .
           chunk_split(base64_encode($sequence), 64, "\n") .
           "-----END EC PRIVATE KEY-----\n";

    return $pem;
}

/**
 * ASN.1 length encoding
 */
function wpiko_chatbot_pro_asn1_length($length) {
    if ($length < 128) {
        return chr($length);
    }
    $temp = ltrim(pack('N', $length), "\x00");
    return chr(0x80 | strlen($temp)) . $temp;
}

/**
 * Convert DER-encoded ECDSA signature to raw r||s format
 */
function wpiko_chatbot_pro_der_to_raw($der) {
    $pos = 0;
    if (ord($der[$pos]) !== 0x30) return $der;
    $pos++;
    // Skip sequence length
    if (ord($der[$pos]) > 0x80) {
        $pos += (ord($der[$pos]) & 0x7f) + 1;
    } else {
        $pos++;
    }

    // Read r
    if (ord($der[$pos]) !== 0x02) return $der;
    $pos++;
    $r_len = ord($der[$pos]);
    $pos++;
    $r = substr($der, $pos, $r_len);
    $pos += $r_len;

    // Read s
    if (ord($der[$pos]) !== 0x02) return $der;
    $pos++;
    $s_len = ord($der[$pos]);
    $pos++;
    $s = substr($der, $pos, $s_len);

    // Pad/trim to 32 bytes each
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return substr($r, -32) . substr($s, -32);
}

/**
 * Base64url encode
 */
function wpiko_chatbot_pro_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64url decode
 */
function wpiko_chatbot_pro_base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/**
 * Hook: Send push notification when a new conversation starts
 */
function wpiko_chatbot_pro_notify_new_message($user_id, $thread_id, $role, $message, $user_email) {
    // Only notify on user messages (not AI responses or errors)
    if ($role !== 'user') {
        return;
    }

    if (!wpiko_chatbot_pro_is_license_active()) {
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('Push notification skipped because the Pro license is not active.', 'info');
        }
        return;
    }

    if (!get_option('wpiko_chatbot_enable_pwa', '0')) {
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('Push notification skipped because the PWA feature is disabled.', 'info');
        }
        return;
    }

    if (function_exists('wpiko_chatbot_log')) {
        wpiko_chatbot_log('Push trigger received for conversation ' . $thread_id . '.', 'info');
    }

    // Get user display name
    $user_name = 'Guest';
    if ($user_id) {
        $user_data = get_userdata($user_id);
        $user_name = $user_data ? $user_data->display_name : 'User';
    } elseif (!empty($_POST['user_name'])) {
        $user_name = sanitize_text_field(wp_unslash($_POST['user_name']));
    }

    // Truncate message for notification body
    $body = mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '...' : $message;

    wpiko_chatbot_pro_send_push_notification(
        $user_name . ' sent a message',
        $body,
        array('session_id' => $thread_id)
    );
}
add_action('wpiko_chatbot_message_saved', 'wpiko_chatbot_pro_notify_new_message', 10, 5);

/**
 * AJAX: Send a test push notification from the admin settings.
 */
function wpiko_chatbot_pro_send_test_push_ajax() {
    check_ajax_referer('wpiko_chatbot_send_test_push', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'), 403);
    }

    if (!wpiko_chatbot_pro_is_license_active()) {
        wp_send_json_error(array('message' => 'An active Pro license is required.'), 400);
    }

    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        wp_send_json_error(array('message' => 'Enable the Mobile App (PWA) first.'), 400);
    }

    if (get_option('wpiko_chatbot_pwa_push_enabled', '1') !== '1') {
        wp_send_json_error(array('message' => 'Enable push notifications in the PWA admin settings first.'), 400);
    }

    $result = wpiko_chatbot_pro_send_push_notification(
        'WPiko test notification',
        'This is a test push notification from your WordPress admin.',
        array('type' => 'test')
    );

    if (empty($result['subscription_count'])) {
        wp_send_json_error(array(
            'message' => 'No PWA subscriptions were found. Open the PWA on your phone, enable Push Notifications there, then try again.',
            'details' => $result,
        ), 400);
    }

    if (empty($result['success_count'])) {
        $message = 'The test push request was sent, but the push service did not accept any subscriptions.';

        if (!empty($result['removed_count'])) {
            $message .= ' Removed ' . $result['removed_count'] . ' expired subscription(s).';
        } else {
            $message .= ' Check the debug log for the returned HTTP status.';
        }

        wp_send_json_error(array(
            'message' => $message,
            'details' => $result,
        ), 400);
    }

    $message = 'Test notification accepted by the push service for ' . $result['success_count'] . ' subscription(s).';

    if (!empty($result['removed_count'])) {
        $message .= ' Removed ' . $result['removed_count'] . ' expired subscription(s).';
    }

    wp_send_json_success(array(
        'message' => $message,
        'subscription_count' => isset($result['remaining_subscription_count']) ? (int) $result['remaining_subscription_count'] : 0,
        'details' => $result,
    ));
}
add_action('wp_ajax_wpiko_chatbot_send_test_push', 'wpiko_chatbot_pro_send_test_push_ajax');

function wpiko_chatbot_pro_remove_push_subscription_ajax() {
    check_ajax_referer('wpiko_chatbot_manage_push_subscriptions', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'), 403);
    }

    $subscription_id = isset($_POST['subscription_id']) ? absint(wp_unslash($_POST['subscription_id'])) : 0;

    if ($subscription_id <= 0) {
        wp_send_json_error(array('message' => 'A valid device is required.'), 400);
    }

    if (!wpiko_chatbot_pro_delete_push_subscription_by_id($subscription_id)) {
        wp_send_json_error(array('message' => 'The device could not be removed or no longer exists.'), 404);
    }

    wp_send_json_success(array(
        'message' => 'Registered device removed.',
        'subscription_id' => $subscription_id,
        'subscription_count' => wpiko_chatbot_pro_count_push_subscriptions(),
    ));
}
add_action('wp_ajax_wpiko_chatbot_remove_push_subscription', 'wpiko_chatbot_pro_remove_push_subscription_ajax');
