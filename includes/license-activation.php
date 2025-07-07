<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

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

// Function to activate license
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

    $activation_url = 'https://wpiko.com/wp-json/wpiko-keymaster/v1/activate-license';
    $response = wp_remote_post($activation_url, array(
        'body' => array(
            'license_key' => $license_key,
            'domain' => $current_domain
        ),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        wpiko_chatbot_log('License activation failed: ' . $response->get_error_message(), 'error');
        wp_send_json_error('Network error: ' . $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($response_code === 200 && $data && $data['success']) {
        // Encrypt and store license information
        update_option('wpiko_chatbot_license_key', wpiko_chatbot_pro_encrypt_data($license_key));
        update_option('wpiko_chatbot_license_status', wpiko_chatbot_pro_encrypt_data('active'));
        update_option('wpiko_chatbot_license_domain', $current_domain);
        update_option('wpiko_chatbot_license_source_domain', $data['data']['source_domain']);
        
        if (isset($data['data']['expiration_date'])) {
            update_option('wpiko_chatbot_license_expiration', wpiko_chatbot_pro_encrypt_data($data['data']['expiration_date']));
        }
        
        if (isset($data['data']['is_lifetime'])) {
            update_option('wpiko_chatbot_license_is_lifetime', wpiko_chatbot_pro_encrypt_data($data['data']['is_lifetime'] ? '1' : '0'));
        }
        
        if (isset($data['data']['product_type'])) {
            update_option('wpiko_chatbot_license_product_type', $data['data']['product_type']);
        }

        wpiko_chatbot_log('License activated successfully for domain: ' . $current_domain, 'info');
        wp_send_json_success($data['data']);
    } else {
        $error_message = isset($data['data']) ? $data['data'] : 'Unknown error occurred';
        wpiko_chatbot_log('License activation failed: ' . $error_message, 'error');
        wp_send_json_error($error_message);
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
    $license_key = $request->get_param('license_key');
    $source_domain = $request->get_param('source_domain');

    // Get the domain where the license was purchased
    $license_domain = get_option('wpiko_chatbot_license_source_domain');

    // Verify the source domain
    if ($source_domain !== $license_domain) {
        return new WP_Error('invalid_source', 'Invalid source domain', array('status' => 403));
    }

    // Check if this is the active license
    $encrypted_current_license = get_option('wpiko_chatbot_license_key');
    $decrypted_current_license = wpiko_chatbot_pro_decrypt_data($encrypted_current_license);
    
    if ($decrypted_current_license === $license_key) {
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
    $license_key = $request->get_param('license_key');
    $expiration_date = $request->get_param('expiration_date');
    $source_domain = $request->get_param('source_domain');

    // Get the domain where the license was purchased
    $license_domain = get_option('wpiko_chatbot_license_source_domain');

    // Verify the source domain
    if ($source_domain !== $license_domain) {
        return new WP_Error('invalid_source', 'Invalid source domain', array('status' => 403));
    }

    // Check if this is the active license
    $encrypted_current_license = get_option('wpiko_chatbot_license_key');
    $decrypted_current_license = wpiko_chatbot_pro_decrypt_data($encrypted_current_license);
    
    if ($decrypted_current_license === $license_key) {
        // If expiration_date is null, it's a lifetime license
        if ($expiration_date === null) {
            $encrypted_lifetime = wpiko_chatbot_pro_encrypt_data('1');
            update_option('wpiko_chatbot_license_is_lifetime', $encrypted_lifetime);
            update_option('wpiko_chatbot_license_expiration', '');
        } else {
            // Regular license update
            $encrypted_expiration = wpiko_chatbot_pro_encrypt_data($expiration_date);
            update_option('wpiko_chatbot_license_expiration', $encrypted_expiration);
            // Reset lifetime flag
            $encrypted_lifetime = wpiko_chatbot_pro_encrypt_data('0');
            update_option('wpiko_chatbot_license_is_lifetime', $encrypted_lifetime);
        }
        
        return new WP_REST_Response('License updated successfully', 200);
    } else {
        return new WP_REST_Response('License key not found or not active', 404);
    }
}

// Schedule our daily check
function wpiko_chatbot_pro_schedule_license_check() {
    $is_lifetime = get_option('wpiko_chatbot_license_is_lifetime', false);
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
function wpiko_chatbot_pro_check_license_expiration() {
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

    $result = wpiko_chatbot_pro_check_license_expiration();

    if ($result === 'active') {
        $encrypted_active = wpiko_chatbot_pro_encrypt_data('active');
        update_option('wpiko_chatbot_license_status', $encrypted_active);
        wp_send_json_success(array('message' => 'License is valid and active.', 'status' => 'active'));
    } elseif ($result === 'expired') {
        $encrypted_expired = wpiko_chatbot_pro_encrypt_data('expired');
        update_option('wpiko_chatbot_license_status', $encrypted_expired);
        wp_send_json_error(array('message' => 'Your license has expired. Please renew to continue using the plugin.', 'status' => 'expired'));
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-chatbot&tab=license_activation')); ?>">
                    Renew your license
                </a> 
                to continue using all features.
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
