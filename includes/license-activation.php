<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Connection diagnostic test for license server
function wpiko_chatbot_pro_test_connection() {
    check_ajax_referer('wpiko_chatbot_test_connection', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $diagnostics = array(
        'success' => true,
        'tests' => array(),
        'server_info' => array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
            'curl_available' => function_exists('curl_version'),
            'openssl_available' => extension_loaded('openssl'),
        )
    );

    // Test 1: DNS Resolution
    $host = 'wpiko.com';
    $dns_start = microtime(true);
    $dns_result = gethostbyname($host);
    $dns_time = round((microtime(true) - $dns_start) * 1000, 2);
    
    $diagnostics['tests']['dns'] = array(
        'name' => 'DNS Resolution',
        'success' => $dns_result !== $host,
        'message' => $dns_result !== $host ? 'Resolved to ' . $dns_result : 'Failed to resolve wpiko.com',
        'time_ms' => $dns_time
    );
    if ($dns_result === $host) {
        $diagnostics['success'] = false;
    }

    // Test 2: HTTP Connection (without SSL first to test basic connectivity)
    $http_start = microtime(true);
    $http_response = wp_remote_get('https://wpiko.com', array(
        'timeout' => 15,
        'sslverify' => true
    ));
    $http_time = round((microtime(true) - $http_start) * 1000, 2);

    if (is_wp_error($http_response)) {
        $error_code = $http_response->get_error_code();
        $error_msg = $http_response->get_error_message();
        $diagnostics['tests']['http'] = array(
            'name' => 'HTTPS Connection',
            'success' => false,
            'message' => 'Error [' . $error_code . ']: ' . $error_msg,
            'time_ms' => $http_time
        );
        $diagnostics['success'] = false;
    } else {
        $http_code = wp_remote_retrieve_response_code($http_response);
        $diagnostics['tests']['http'] = array(
            'name' => 'HTTPS Connection',
            'success' => $http_code >= 200 && $http_code < 400,
            'message' => 'HTTP Status: ' . $http_code,
            'time_ms' => $http_time
        );
        if ($http_code >= 400) {
            $diagnostics['success'] = false;
        }
    }

    // Test 3: REST API Endpoint
    $api_start = microtime(true);
    $api_response = wp_remote_post('https://wpiko.com/wp-json/wpiko-keymaster/v1/verify-license', array(
        'body' => array(
            'license_key' => 'CONNECTION_TEST',
            'domain' => home_url(),
            'verify_only' => true
        ),
        'timeout' => 15,
        'sslverify' => true
    ));
    $api_time = round((microtime(true) - $api_start) * 1000, 2);

    if (is_wp_error($api_response)) {
        $error_code = $api_response->get_error_code();
        $error_msg = $api_response->get_error_message();
        $diagnostics['tests']['api'] = array(
            'name' => 'License API Endpoint',
            'success' => false,
            'message' => 'Error [' . $error_code . ']: ' . $error_msg,
            'time_ms' => $api_time
        );
        $diagnostics['success'] = false;
    } else {
        $api_code = wp_remote_retrieve_response_code($api_response);
        $api_body = wp_remote_retrieve_body($api_response);
        $api_data = json_decode($api_body, true);
        
        // Check if we got a valid JSON response (even if license is invalid, API is working)
        $is_valid_response = $api_data !== null && json_last_error() === JSON_ERROR_NONE;
        
        // Check for HTML response (WAF/firewall block)
        $is_html = preg_match('/<html|<!DOCTYPE/i', $api_body);
        
        if ($is_html) {
            $diagnostics['tests']['api'] = array(
                'name' => 'License API Endpoint',
                'success' => false,
                'message' => 'Received HTML instead of JSON - likely blocked by firewall/WAF',
                'time_ms' => $api_time,
                'blocked' => true
            );
            $diagnostics['success'] = false;
        } elseif (empty($api_body)) {
            $diagnostics['tests']['api'] = array(
                'name' => 'License API Endpoint',
                'success' => false,
                'message' => 'Empty response received - likely blocked by firewall/WAF',
                'time_ms' => $api_time,
                'blocked' => true
            );
            $diagnostics['success'] = false;
        } elseif ($is_valid_response) {
            $diagnostics['tests']['api'] = array(
                'name' => 'License API Endpoint',
                'success' => true,
                'message' => 'API responding correctly (HTTP ' . $api_code . ')',
                'time_ms' => $api_time
            );
        } else {
            $diagnostics['tests']['api'] = array(
                'name' => 'License API Endpoint',
                'success' => false,
                'message' => 'Invalid JSON response (HTTP ' . $api_code . ')',
                'time_ms' => $api_time
            );
            $diagnostics['success'] = false;
        }
    }

    // Generate summary
    if ($diagnostics['success']) {
        $diagnostics['summary'] = 'All connection tests passed. Your server can communicate with our licensing server.';
    } else {
        $blocked_test = null;
        foreach ($diagnostics['tests'] as $test) {
            if (isset($test['blocked']) && $test['blocked']) {
                $blocked_test = $test['name'];
                break;
            }
        }
        
        if ($blocked_test) {
            $diagnostics['summary'] = 'Connection is being blocked, likely by your server\'s firewall (ModSecurity, WAF, or similar). Please contact your hosting provider and ask them to whitelist requests to wpiko.com.';
        } elseif (!$diagnostics['tests']['dns']['success']) {
            $diagnostics['summary'] = 'DNS resolution failed. Your server cannot resolve wpiko.com. Please check your server\'s DNS configuration.';
        } elseif (!$diagnostics['tests']['http']['success']) {
            $diagnostics['summary'] = 'HTTPS connection failed. Your server cannot establish a secure connection to wpiko.com. This may be due to SSL/certificate issues or firewall blocking.';
        } else {
            $diagnostics['summary'] = 'Some connection tests failed. Please review the details above and contact your hosting provider if needed.';
        }
    }

    wp_send_json_success($diagnostics);
}
add_action('wp_ajax_wpiko_chatbot_test_connection', 'wpiko_chatbot_pro_test_connection');

// Function to encrypt sensitive data (license key, expiration date, status, or lifetime flag)
function wpiko_chatbot_pro_encrypt_data($data) {
    if (empty($data)) return '';
    
    $encryption_key = wp_salt('auth');
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $encryption_key, 0, $iv);
    
    return base64_encode($encrypted . '::' . $iv);
}

// Function to decrypt sensitive data (license key, expiration date, status, or lifetime flag)
function wpiko_chatbot_pro_decrypt_data($encrypted_data) {
    if (empty($encrypted_data)) return '';
    
    $encryption_key = wp_salt('auth');
    $parts = explode('::', base64_decode($encrypted_data), 2);
    
    // Check if we have both parts (encrypted data and IV)
    if (count($parts) !== 2) {
        return ''; // Return empty string if the data is not in the expected format
    }
    
    list($encrypted_data, $iv) = $parts;
    
    $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', $encryption_key, 0, $iv);
    
    return $decrypted !== false ? $decrypted : '';
}

// Alias functions for backward compatibility
function wpiko_chatbot_pro_encrypt_license_key($license_key) {
    return wpiko_chatbot_pro_encrypt_data($license_key);
}

function wpiko_chatbot_pro_decrypt_license_key($encrypted_license_key) {
    return wpiko_chatbot_pro_decrypt_data($encrypted_license_key);
}

function wpiko_chatbot_pro_is_license_active() {
    $encrypted_status = get_option('wpiko_chatbot_license_status', '');
    $status = wpiko_chatbot_pro_decrypt_data($encrypted_status);
    return $status === 'active';
}

// Function to activate license via AJAX
function wpiko_chatbot_pro_activate_license() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpiko_chatbot_activate_license')) {
        wp_send_json_error('Invalid nonce');
    }

    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
    $current_domain = home_url();

    if (empty($license_key)) {
        wp_send_json_error('License key is required');
    }

    // Use verify-license endpoint (same as form-based activation)
    $activation_url = 'https://wpiko.com/wp-json/wpiko-keymaster/v1/verify-license';
    $response = wp_remote_post($activation_url, array(
        'body' => array(
            'license_key' => $license_key,
            'domain' => $current_domain,
            'product_type' => 'chatbot'
        ),
        'timeout' => 30,
        'sslverify' => true
    ));

    // Handle connection errors with detailed messages
    if (is_wp_error($response)) {
        $error_code = $response->get_error_code();
        $error_message = $response->get_error_message();
        wpiko_chatbot_log('License activation network error [' . $error_code . ']: ' . $error_message, 'error');
        
        // Provide user-friendly error messages based on error type
        if (strpos($error_code, 'ssl') !== false || strpos($error_code, 'certificate') !== false) {
            wp_send_json_error('SSL/Certificate error: Your server cannot establish a secure connection to our licensing server. Please contact your hosting provider.');
        } elseif (strpos($error_code, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
            wp_send_json_error('Connection timeout: Your server took too long to reach our licensing server. This may be a temporary issue or a firewall blocking the connection.');
        } elseif (strpos($error_code, 'resolve') !== false || strpos($error_message, 'resolve') !== false) {
            wp_send_json_error('DNS resolution failed: Your server cannot find our licensing server. Please check your server\'s DNS configuration.');
        } else {
            wp_send_json_error('Connection error: ' . $error_message . '. Your server may be blocking outbound connections to wpiko.com.');
        }
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    // Log the raw response for debugging
    wpiko_chatbot_log('License activation response code: ' . $response_code, 'info');
    
    // Check for empty response (often indicates WAF/firewall blocking)
    if (empty($body)) {
        wpiko_chatbot_log('License activation failed: Empty response received', 'error');
        wp_send_json_error('Empty response received from licensing server. This usually means your server\'s firewall (ModSecurity, WAF) is blocking the connection. Please contact your hosting provider.');
        return;
    }
    
    // Attempt to decode JSON
    $data = json_decode($body, true);
    
    // Check for HTML response (indicates server error page or WAF block)
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        wpiko_chatbot_log('License activation failed: Invalid JSON response. Body: ' . substr($body, 0, 500), 'error');
        
        // Check if response looks like HTML (firewall block page)
        if (preg_match('/<html|<!DOCTYPE/i', $body)) {
            wp_send_json_error('Server returned an error page instead of valid response. Your hosting\'s security rules may be blocking the connection. Please contact your hosting provider.');
        } else {
            wp_send_json_error('Invalid response from licensing server. Please try again or contact support.');
        }
        return;
    }

    // Handle HTTP error codes
    if ($response_code !== 200) {
        wpiko_chatbot_log('License activation failed with HTTP code ' . $response_code . ': ' . wp_json_encode($data), 'error');
        $error_msg = isset($data['message']) ? $data['message'] : 'Server returned error code ' . $response_code;
        wp_send_json_error($error_msg);
        return;
    }

    // Process successful response
    if (isset($data['valid']) && $data['valid']) {
        // Check product type
        if (isset($data['product_type']) && $data['product_type'] !== 'chatbot') {
            wp_send_json_error('This license key is not valid for the WPiko Chatbot plugin.');
            return;
        }
        
        if (isset($data['activated']) && $data['activated']) {
            // Encrypt and store license information
            update_option('wpiko_chatbot_license_key', wpiko_chatbot_pro_encrypt_data($license_key));
            update_option('wpiko_chatbot_license_status', wpiko_chatbot_pro_encrypt_data('active'));
            update_option('wpiko_chatbot_license_domain', $current_domain);
            
            if (isset($data['source_domain'])) {
                update_option('wpiko_chatbot_license_source_domain', $data['source_domain']);
            }
            
            wpiko_chatbot_pro_store_license_state(
                !empty($data['is_lifetime']),
                isset($data['expiration_date']) ? $data['expiration_date'] : null
            );
            
            if (isset($data['product_type'])) {
                update_option('wpiko_chatbot_license_product_type', $data['product_type']);
            }

            wpiko_chatbot_log('License activated successfully for domain: ' . $current_domain, 'info');
            wp_send_json_success($data);
        } else {
            // License valid but cannot activate (max URLs reached)
            $message = isset($data['message']) ? $data['message'] : 'License is valid but has reached the maximum number of allowed activations.';
            wpiko_chatbot_log('License activation failed: ' . $message, 'warning');
            wp_send_json_error($message);
        }
    } else {
        // License not valid
        wpiko_chatbot_log('License validation failed. Response: ' . wp_json_encode($data), 'error');
        wp_send_json_error('Invalid, expired, or already activated license key.');
    }
}
add_action('wp_ajax_wpiko_chatbot_activate_license', 'wpiko_chatbot_pro_activate_license');

// Function to check if the license is lifetime
function wpiko_chatbot_pro_is_lifetime_license() {
    $encrypted_lifetime = get_option('wpiko_chatbot_license_is_lifetime', '');
    return wpiko_chatbot_pro_decrypt_data($encrypted_lifetime) === '1';
}

// Function to deactivate license
function wpiko_chatbot_pro_deactivate_license() {
    $encrypted_license_key = get_option('wpiko_chatbot_license_key');
    $license_key = wpiko_chatbot_pro_decrypt_data($encrypted_license_key);
    $domain = get_option('wpiko_chatbot_license_domain');
    $deactivate_url = 'https://wpiko.com/wp-json/wpiko-keymaster/v1/deactivate-license';
    $response = wp_remote_post($deactivate_url, array(
        'body' => array(
            'license_key' => $license_key,
            'domain' => $domain
        )
    ));
}

// Function to delete license
function wpiko_chatbot_pro_delete_license() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    check_ajax_referer('wpiko_chatbot_delete_license', 'nonce');

    // Attempt to deactivate the license
    wpiko_chatbot_pro_deactivate_license();

    // Regardless of the deactivation result, delete all local license data
    delete_option('wpiko_chatbot_license_key');
    delete_option('wpiko_chatbot_license_status');
    delete_option('wpiko_chatbot_license_domain');
    delete_option('wpiko_chatbot_license_source_domain');
    delete_option('wpiko_chatbot_license_expiration');
    delete_option('wpiko_chatbot_license_is_lifetime');
    delete_option('wpiko_chatbot_license_product_type');

    wp_send_json_success('License deleted successfully');
}
add_action('wp_ajax_wpiko_chatbot_delete_license', 'wpiko_chatbot_pro_delete_license');

// Revoke license
function wpiko_chatbot_pro_register_revoke_endpoint() {
    register_rest_route('wpiko-chatbot/v1', '/revoke-license', array(
        'methods' => 'POST',
        'callback' => 'wpiko_chatbot_pro_handle_revoke_license',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'wpiko_chatbot_pro_register_revoke_endpoint');

// Function to revoke license
function wpiko_chatbot_pro_handle_revoke_license($request) {
    $license_key = (string) $request->get_param('license_key');

    // Only the license server can revoke: it signs the request with its private key.
    $verified = wpiko_chatbot_pro_verify_license_server_request($request, 'revoke');
    if (is_wp_error($verified)) {
        wpiko_chatbot_log('Rejected license revoke request: ' . $verified->get_error_code(), 'warning');
        return $verified;
    }

    if (wpiko_chatbot_pro_license_key_matches($license_key)) {
        // Deactivate the license
        delete_option('wpiko_chatbot_license_key');
        delete_option('wpiko_chatbot_license_status');
        delete_option('wpiko_chatbot_license_expiration');
        delete_option('wpiko_chatbot_license_is_lifetime');
        delete_option('wpiko_chatbot_license_domain');
        delete_option('wpiko_chatbot_license_source_domain');
        delete_option('wpiko_chatbot_license_product_type');
        return new WP_REST_Response('License revoked successfully', 200);
    } else {
        return new WP_REST_Response('License key not found or not active', 404);
    }
}

// Update date expiration
function wpiko_chatbot_pro_register_update_expiration_endpoint() {
    register_rest_route('wpiko-chatbot/v1', '/update-expiration', array(
        'methods' => 'POST',
        'callback' => 'wpiko_chatbot_pro_handle_update_expiration',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'wpiko_chatbot_pro_register_update_expiration_endpoint');

// Function to update date expiration
function wpiko_chatbot_pro_handle_update_expiration($request) {
    $license_key = (string) $request->get_param('license_key');
    $expiration_date = $request->get_param('expiration_date');

    // Only the license server can change the expiration: it signs the request with its private key.
    $verified = wpiko_chatbot_pro_verify_license_server_request($request, 'update-expiration');
    if (is_wp_error($verified)) {
        wpiko_chatbot_log('Rejected license expiration update: ' . $verified->get_error_code(), 'warning');
        return $verified;
    }

    if ($expiration_date !== null && $expiration_date !== '' && strtotime((string) $expiration_date) === false) {
        return new WP_Error('invalid_date', 'Invalid expiration date', array('status' => 400));
    }

    if (wpiko_chatbot_pro_license_key_matches($license_key)) {
        // No expiration date means a lifetime license. The status (active/expired) follows the new date.
        wpiko_chatbot_pro_store_license_state($expiration_date === null || $expiration_date === '', $expiration_date);

        return new WP_REST_Response('License updated successfully', 200);
    } else {
        return new WP_REST_Response('License key not found or not active', 404);
    }
}

// Schedule our daily check
function wpiko_chatbot_pro_schedule_license_check() {
    // The option is stored encrypted, so it must be decrypted (an encrypted "0" is not empty).
    $is_lifetime = wpiko_chatbot_pro_is_lifetime_license();
    $encrypted_expiration = get_option('wpiko_chatbot_license_expiration', '');
    $license_expiration = wpiko_chatbot_pro_decrypt_data($encrypted_expiration);

    if (!$is_lifetime && !empty($license_expiration)) {
        if (!wp_next_scheduled('wpiko_chatbot_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'wpiko_chatbot_daily_license_check');
        }
    } else {
        // If the license is lifetime or doesn't have an expiration date, clear the scheduled check
        wp_clear_scheduled_hook('wpiko_chatbot_daily_license_check');
    }
}
add_action('wp', 'wpiko_chatbot_pro_schedule_license_check');

// Function to perform the daily check
function wpiko_chatbot_pro_check_license_expiration($ask_server = true) {
    // Ask the license server first, so a website that missed an update (for example
    // a license changed to lifetime while this site was unreachable) catches up.
    if ($ask_server) {
        wpiko_chatbot_pro_refresh_license_from_server();
    }

    $encrypted_status = get_option('wpiko_chatbot_license_status', '');
    $license_status = wpiko_chatbot_pro_decrypt_data($encrypted_status);
    $encrypted_expiration = get_option('wpiko_chatbot_license_expiration', '');
    $license_expiration = wpiko_chatbot_pro_decrypt_data($encrypted_expiration);
    $is_lifetime = wpiko_chatbot_pro_is_lifetime_license();

    // If it's a lifetime license, always return active
    if ($is_lifetime) {
        $encrypted_active = wpiko_chatbot_pro_encrypt_data('active');
        update_option('wpiko_chatbot_license_status', $encrypted_active);
        return 'active';
    }

    // Check expiration only if we have an expiration date
    if (!empty($license_expiration)) {
        $current_date = new DateTime();
        $expiration_date = new DateTime($license_expiration);

        if ($current_date > $expiration_date) {
            $encrypted_expired = wpiko_chatbot_pro_encrypt_data('expired');
            update_option('wpiko_chatbot_license_status', $encrypted_expired);
            // Reset the dismissed state when license expires
            delete_option('wpiko_chatbot_expired_notice_dismissed');
            return 'expired';
        } else {
            // If the license was previously expired but is now valid, update to active
            if ($license_status === 'expired') {
                $encrypted_active = wpiko_chatbot_pro_encrypt_data('active');
                update_option('wpiko_chatbot_license_status', $encrypted_active);
            }
            return 'active';
        }
    }

    // If we don't have an expiration date, return the current status
    return $license_status;
}
add_action('wpiko_chatbot_daily_license_check', 'wpiko_chatbot_pro_check_license_expiration');

// Clear the scheduled event when the plugin is deactivated
function wpiko_chatbot_pro_license_deactivation() {
    wp_clear_scheduled_hook('wpiko_chatbot_daily_license_check');
}
register_deactivation_hook(WPIKO_CHATBOT_PRO_FILE, 'wpiko_chatbot_pro_license_deactivation');

// Function to handle manual license check
function wpiko_chatbot_pro_manual_license_check() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $server = wpiko_chatbot_pro_refresh_license_from_server();
    $result = wpiko_chatbot_pro_check_license_expiration(false);

    if ($server === 'not_activated' && $result === 'active') {
        wp_send_json_success(array('message' => 'License is active here, but the license server does not list this website for the key. If Pro features stop working, delete the license and activate it again.', 'status' => 'active'));
    } elseif ($result === 'active') {
        $encrypted_active = wpiko_chatbot_pro_encrypt_data('active');
        update_option('wpiko_chatbot_license_status', $encrypted_active);
        $message = wpiko_chatbot_pro_is_lifetime_license() ? 'License is valid and active (lifetime).' : 'License is valid and active.';
        if ($server === 'unreachable') {
            $message .= ' (Could not reach the license server, checked locally.)';
        }
        wp_send_json_success(array('message' => $message, 'status' => 'active'));
    } elseif ($result === 'expired') {
        $encrypted_expired = wpiko_chatbot_pro_encrypt_data('expired');
        update_option('wpiko_chatbot_license_status', $encrypted_expired);
        wp_send_json_error(array('message' => 'Your license has expired. Get a lifetime license to continue using Pro features.', 'status' => 'expired'));
    } else {
        $encrypted_inactive = wpiko_chatbot_pro_encrypt_data('inactive');
        update_option('wpiko_chatbot_license_status', $encrypted_inactive);
        wp_send_json_error(array('message' => 'License is inactive or invalid.', 'status' => 'inactive'));
    }
}
add_action('wp_ajax_wpiko_chatbot_manual_license_check', 'wpiko_chatbot_pro_manual_license_check');

// Add license has expired notice to the Wordpress dashboard
function wpiko_chatbot_pro_admin_expired_notice() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if notice has been dismissed
    if (get_option('wpiko_chatbot_expired_notice_dismissed')) {
        return;
    }

    // Check if license is expired
    $encrypted_status = get_option('wpiko_chatbot_license_status', '');
    $license_status = wpiko_chatbot_pro_decrypt_data($encrypted_status);

    if ($license_status === 'expired') {
        ?>
        <div class="notice notice-error is-dismissible wpiko-chatbot-expired-notice">
            <p>
                <strong>WPiko Chatbot License Expired!</strong> 
                Your premium features are now disabled. 
                <a href="https://wpiko.com/chatbot-pricing/" target="_blank" rel="noopener">
                    Get a lifetime license
                </a> 
                to continue using all features. Already converted to lifetime?
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-chatbot&tab=license_activation')); ?>">Refresh your license</a>.
            </p>
        </div>
        <?php
        // Add JavaScript for handling the dismiss action
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(document).on('click', '.wpiko-chatbot-expired-notice .notice-dismiss', function() {
                    $.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'wpiko_chatbot_dismiss_expired_notice',
                            security: '<?php echo esc_js(wp_create_nonce('wpiko_chatbot_dismiss_notice')); ?>'
                        }
                    });
                });
            });
        </script>
        <?php
    }
}
add_action('admin_notices', 'wpiko_chatbot_pro_admin_expired_notice');

// Handle the AJAX dismiss action
function wpiko_chatbot_pro_dismiss_expired_notice() {
    check_ajax_referer('wpiko_chatbot_dismiss_notice', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    update_option('wpiko_chatbot_expired_notice_dismissed', true);
    wp_send_json_success();
}
add_action('wp_ajax_wpiko_chatbot_dismiss_expired_notice', 'wpiko_chatbot_pro_dismiss_expired_notice');

// Reset the dismissed state when the plugin is activated
function wpiko_chatbot_pro_reset_notice_on_activation() {
    delete_option('wpiko_chatbot_expired_notice_dismissed');
}
register_activation_hook(WPIKO_CHATBOT_PRO_FILE, 'wpiko_chatbot_pro_reset_notice_on_activation');

/**
 * Save the license expiration / lifetime flag and set the matching status.
 *
 * @param bool        $is_lifetime     Lifetime license.
 * @param string|null $expiration_date Expiration date (ignored for lifetime).
 * @return string New status: 'active' or 'expired'.
 */
function wpiko_chatbot_pro_store_license_state($is_lifetime, $expiration_date = null) {
    if ($is_lifetime) {
        update_option('wpiko_chatbot_license_is_lifetime', wpiko_chatbot_pro_encrypt_data('1'));
        update_option('wpiko_chatbot_license_expiration', '');
        $status = 'active';
    } else {
        update_option('wpiko_chatbot_license_is_lifetime', wpiko_chatbot_pro_encrypt_data('0'));
        update_option('wpiko_chatbot_license_expiration', $expiration_date ? wpiko_chatbot_pro_encrypt_data((string) $expiration_date) : '');
        $timestamp = $expiration_date ? strtotime((string) $expiration_date) : false;
        $status = ($timestamp !== false && $timestamp < time()) ? 'expired' : 'active';
    }

    update_option('wpiko_chatbot_license_status', wpiko_chatbot_pro_encrypt_data($status));

    if ($status === 'expired') {
        delete_option('wpiko_chatbot_expired_notice_dismissed');
    }

    // Lifetime licenses need no daily expiration check; dated ones do.
    if ($status === 'active' && $is_lifetime) {
        wp_clear_scheduled_hook('wpiko_chatbot_daily_license_check');
    } elseif (!wp_next_scheduled('wpiko_chatbot_daily_license_check')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'wpiko_chatbot_daily_license_check');
    }

    return $status;
}

/**
 * Ask the license server for this website's current license state and save it.
 *
 * Read-only on the server side: nothing is activated there. If the server cannot
 * be reached, or does not know this website, the local license is left as it is.
 *
 * @return string 'active', 'expired', 'not_activated', 'not_found', 'unreachable' or 'no_key'.
 */
function wpiko_chatbot_pro_refresh_license_from_server() {
    $license_key = wpiko_chatbot_pro_decrypt_data(get_option('wpiko_chatbot_license_key', ''));
    if ($license_key === '') {
        return 'no_key';
    }

    $domain = get_option('wpiko_chatbot_license_domain', '');
    if ($domain === '') {
        $domain = home_url();
    }

    $response = wp_remote_post('https://' . wpiko_chatbot_pro_license_server_host() . '/wp-json/wpiko-keymaster/v1/license-status', array(
        'body' => array(
            'license_key' => $license_key,
            'domain' => $domain,
            'product_type' => 'chatbot',
        ),
        'timeout' => 15,
        'sslverify' => true,
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('License refresh: could not reach the license server.', 'warning');
        }
        return 'unreachable';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['status'])) {
        return 'unreachable';
    }

    update_option('wpiko_chatbot_license_last_server_check', time(), false);

    if ($data['status'] === 'active' || $data['status'] === 'expired') {
        if (isset($data['product_type']) && $data['product_type'] !== 'chatbot') {
            return 'not_found';
        }
        $status = wpiko_chatbot_pro_store_license_state(
            !empty($data['is_lifetime']),
            isset($data['expiration_date']) ? $data['expiration_date'] : null
        );
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('License refreshed from server: ' . $status . (!empty($data['is_lifetime']) ? ' (lifetime)' : ''), 'info');
        }
        return $status;
    }

    return in_array($data['status'], array('not_activated', 'not_found'), true) ? $data['status'] : 'unreachable';
}

// Provide backward compatibility functions for the main plugin
if (!function_exists('wpiko_chatbot_encrypt_data')) {
    function wpiko_chatbot_encrypt_data($data) {
        return wpiko_chatbot_pro_encrypt_data($data);
    }
}

if (!function_exists('wpiko_chatbot_decrypt_data')) {
    function wpiko_chatbot_decrypt_data($encrypted_data) {
        return wpiko_chatbot_pro_decrypt_data($encrypted_data);
    }
}

if (!function_exists('wpiko_chatbot_encrypt_license_key')) {
    function wpiko_chatbot_encrypt_license_key($license_key) {
        return wpiko_chatbot_pro_encrypt_license_key($license_key);
    }
}

if (!function_exists('wpiko_chatbot_decrypt_license_key')) {
    function wpiko_chatbot_decrypt_license_key($encrypted_license_key) {
        return wpiko_chatbot_pro_decrypt_license_key($encrypted_license_key);
    }
}

if (!function_exists('wpiko_chatbot_is_license_active')) {
    function wpiko_chatbot_is_license_active() {
        return wpiko_chatbot_pro_is_license_active();
    }
}

if (!function_exists('wpiko_chatbot_is_lifetime_license')) {
    function wpiko_chatbot_is_lifetime_license() {
        return wpiko_chatbot_pro_is_lifetime_license();
    }
}

if (!function_exists('wpiko_chatbot_deactivate_license')) {
    function wpiko_chatbot_deactivate_license() {
        return wpiko_chatbot_pro_deactivate_license();
    }
}

if (!function_exists('wpiko_chatbot_check_license_expiration')) {
    function wpiko_chatbot_check_license_expiration() {
        return wpiko_chatbot_pro_check_license_expiration();
    }
}
