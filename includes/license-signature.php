<?php
/**
 * Verify signed messages from the WPiko license server.
 *
 * The license server (WPiko Keymaster on wpiko.com) signs "revoke" and
 * "update expiration" messages with an Ed25519 private key. This file checks
 * the signature against the server's public key, so only the license server
 * can change the license state of this site.
 *
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Host of the license server.
 *
 * @return string
 */
function wpiko_chatbot_pro_license_server_host()
{
    $host = get_option('wpiko_chatbot_license_source_domain', '');
    return $host ? $host : 'wpiko.com';
}

/**
 * Get the license server's public key (raw binary), fetched over HTTPS and cached.
 *
 * @param bool $refresh Fetch again even if a key is cached.
 * @return string Raw key, or '' when unavailable.
 */
function wpiko_chatbot_pro_get_license_server_public_key($refresh = false)
{
    $cached = get_option('wpiko_chatbot_license_server_public_key', '');
    if ($cached && !$refresh) {
        $raw = base64_decode($cached, true);
        if ($raw !== false && strlen($raw) === 32) {
            return $raw;
        }
    }

    // Never refetch more than once an hour.
    if ($refresh && get_transient('wpiko_chatbot_license_key_refetched')) {
        return $cached ? (string) base64_decode($cached, true) : '';
    }
    set_transient('wpiko_chatbot_license_key_refetched', 1, HOUR_IN_SECONDS);

    $response = wp_remote_get('https://' . wpiko_chatbot_pro_license_server_host() . '/wp-json/wpiko-keymaster/v1/signing-key', array(
        'timeout' => 15,
        'sslverify' => true,
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $cached ? (string) base64_decode($cached, true) : '';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $key = isset($data['public_key']) ? base64_decode((string) $data['public_key'], true) : false;
    if ($key === false || strlen($key) !== 32) {
        return $cached ? (string) base64_decode($cached, true) : '';
    }

    update_option('wpiko_chatbot_license_server_public_key', base64_encode($key), false);
    return $key;
}

/**
 * Normalize a site address for comparison ("https://Example.com/" → "example.com").
 *
 * @param string $url URL or host.
 * @return string
 */
function wpiko_chatbot_pro_normalize_site($url)
{
    $url = strtolower(trim((string) $url));
    $url = preg_replace('#^https?://#', '', $url);
    return untrailingslashit($url);
}

/**
 * Verify that a revoke/update-expiration request was signed by the license server.
 *
 * @param WP_REST_Request $request Request.
 * @param string          $action  'revoke' or 'update-expiration'.
 * @return true|WP_Error
 */
function wpiko_chatbot_pro_verify_license_server_request($request, $action)
{
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        return new WP_Error('signature_unavailable', 'Signature verification is not available', array('status' => 500));
    }

    $license_key = (string) $request->get_param('license_key');
    $domain = (string) $request->get_param('domain');
    $timestamp = (int) $request->get_param('timestamp');
    $nonce = (string) $request->get_param('nonce');
    $signature = base64_decode((string) $request->get_param('signature'), true);
    $expiration = $request->get_param('expiration_date');
    $expiration = $expiration === null ? '' : (string) $expiration;

    if ($license_key === '' || $domain === '' || !$timestamp || !preg_match('/^[a-f0-9]{32}$/', $nonce) || $signature === false || strlen($signature) !== 64) {
        return new WP_Error('signature_required', 'A valid signature from the license server is required', array('status' => 401));
    }

    // Reject old or future-dated messages (10 minutes either way).
    if (abs(time() - $timestamp) > 10 * MINUTE_IN_SECONDS) {
        return new WP_Error('signature_expired', 'The request has expired', array('status' => 401));
    }

    // The message must be meant for this site.
    $this_site = wpiko_chatbot_pro_normalize_site(home_url());
    $licensed_site = wpiko_chatbot_pro_normalize_site(get_option('wpiko_chatbot_license_domain', ''));
    $target = wpiko_chatbot_pro_normalize_site($domain);
    if ($target !== $this_site && $target !== $licensed_site) {
        return new WP_Error('wrong_site', 'The request is for a different site', array('status' => 403));
    }

    $message = implode('|', array(
        'wpiko-license',
        'v1',
        $action,
        $domain,
        hash('sha256', $license_key),
        $expiration,
        (string) $timestamp,
        $nonce,
    ));

    $public_key = wpiko_chatbot_pro_get_license_server_public_key();
    $valid = $public_key !== '' && sodium_crypto_sign_verify_detached($signature, $message, $public_key);

    if (!$valid) {
        // The server may have a new key: fetch it once and try again.
        $fresh_key = wpiko_chatbot_pro_get_license_server_public_key(true);
        $valid = $fresh_key !== '' && $fresh_key !== $public_key && sodium_crypto_sign_verify_detached($signature, $message, $fresh_key);
    }

    if (!$valid) {
        return new WP_Error('invalid_signature', 'Invalid signature', array('status' => 401));
    }

    // Each message can only be used once.
    $nonce_key = 'wpiko_chatbot_lsig_' . $nonce;
    if (get_transient($nonce_key)) {
        return new WP_Error('replayed', 'This request was already processed', array('status' => 409));
    }
    set_transient($nonce_key, 1, 30 * MINUTE_IN_SECONDS);

    return true;
}

/**
 * Whether a license key matches the one stored on this site.
 *
 * @param string $license_key Key from the request.
 * @return bool
 */
function wpiko_chatbot_pro_license_key_matches($license_key)
{
    $stored = wpiko_chatbot_pro_decrypt_data(get_option('wpiko_chatbot_license_key'));
    return is_string($stored) && $stored !== '' && hash_equals($stored, (string) $license_key);
}
