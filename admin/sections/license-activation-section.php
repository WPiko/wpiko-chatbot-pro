<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include GitHub status widget
require_once WPIKO_CHATBOT_PRO_PATH . 'admin/templates/github-status-widget.php';

function wpiko_chatbot_license_activation_page() {
    $message = '';
    $message_type = 'info'; // 'info', 'success', 'error'
    
    if (isset($_POST['wpiko_chatbot_license_key'])) {
        // Verify nonce before processing any POST data
        if (!isset($_POST['wpiko_chatbot_license_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_license_nonce'])), 'wpiko_chatbot_license_action')) {
            // If nonce verification fails, display an error message
            $message = 'Security check failed. Please try again.';
            $message_type = 'error';
        } else {
            // Only process the form data if nonce verification passes
            $license_key = sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_license_key']));
            $domain = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    
            $verify_url = 'https://wpiko.com/wp-json/wpiko-keymaster/v1/verify-license';
            $response = wp_remote_post($verify_url, array(
                'body' => array(
                    'license_key' => $license_key,
                    'domain' => $domain,
                    'product_type' => 'chatbot',
                    'verify_only' => true
                ),
                'timeout' => 30,
                'sslverify' => true
            ));

            if (is_wp_error($response)) {
                $error_code = $response->get_error_code();
                $error_msg = $response->get_error_message();
                
                // Provide specific error messages based on error type
                if (strpos($error_code, 'ssl') !== false || strpos($error_code, 'certificate') !== false) {
                    $message = 'SSL/Certificate error: Your server cannot establish a secure connection to our licensing server. Please contact your hosting provider.';
                } elseif (strpos($error_code, 'timeout') !== false || strpos($error_msg, 'timed out') !== false) {
                    $message = 'Connection timeout: Your server took too long to reach our licensing server. This may be a temporary issue or a firewall blocking the connection.';
                } elseif (strpos($error_code, 'resolve') !== false || strpos($error_msg, 'resolve') !== false) {
                    $message = 'DNS resolution failed: Your server cannot find our licensing server. Please check your server\'s DNS configuration.';
                } else {
                    $message = 'Connection error: ' . esc_html($error_msg) . '. Your server may be blocking outbound connections to wpiko.com. Please use the Connection Diagnostics tool below for more details.';
                }
                $message_type = 'error';
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                // Check for empty response (often indicates WAF/firewall blocking)
                if (empty($body)) {
                    $message = 'Empty response received from licensing server. This usually means your server\'s firewall (ModSecurity, WAF) is blocking the connection. Please use the Connection Diagnostics tool below and contact your hosting provider.';
                    $message_type = 'error';
                } else {
                    $data = json_decode($body, true);
                    
                    // Check for HTML response (indicates server error page or WAF block)
                    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                        if (preg_match('/<html|<!DOCTYPE/i', $body)) {
                            $message = 'Server returned an error page instead of valid response. Your hosting\'s security rules may be blocking the connection. Please use the Connection Diagnostics tool below and contact your hosting provider.';
                        } else {
                            $message = 'Invalid response from licensing server. Please try again or contact support.';
                        }
                        $message_type = 'error';
                    } elseif (isset($data['valid']) && $data['valid']) {
                        if (isset($data['product_type']) && $data['product_type'] === 'chatbot') {
                            // If verification is successful, proceed with activation
                            $activate_response = wp_remote_post($verify_url, array(
                                'body' => array(
                                    'license_key' => $license_key,
                                    'domain' => $domain,
                                    'product_type' => 'chatbot'
                                ),
                                'timeout' => 30,
                                'sslverify' => true
                            ));

                            if (!is_wp_error($activate_response)) {
                                $activate_body = wp_remote_retrieve_body($activate_response);
                                $activate_data = json_decode($activate_body, true);
                                
                                if ($activate_data && isset($activate_data['activated']) && $activate_data['activated']) {
                                    // Proceed with saving license data
                                    $encrypted_license_key = wpiko_chatbot_pro_encrypt_data($license_key);
                                    update_option('wpiko_chatbot_license_key', $encrypted_license_key);
                                    $encrypted_active = wpiko_chatbot_pro_encrypt_data('active');
                                    update_option('wpiko_chatbot_license_status', $encrypted_active);
                                    update_option('wpiko_chatbot_license_domain', $domain);
                                    update_option('wpiko_chatbot_license_product_type', 'chatbot');
                                    update_option('wpiko_chatbot_license_source_domain', wp_parse_url($verify_url, PHP_URL_HOST));

                                    wpiko_chatbot_pro_store_license_state(
                                        !empty($activate_data['is_lifetime']),
                                        isset($activate_data['expiration_date']) ? $activate_data['expiration_date'] : null
                                    );
                                    if (isset($activate_data['source_domain'])) {
                                        update_option('wpiko_chatbot_license_source_domain', $activate_data['source_domain']);
                                    }

                                    $message = 'License key activated successfully.';
                                    $message_type = 'success';
                                } else {
                                    $activation_error = isset($activate_data['message']) ? $activate_data['message'] : 'Maximum number of activations reached.';
                                    $message = 'License key is valid but could not be activated: ' . esc_html($activation_error);
                                    $message_type = 'error';
                                }
                            } else {
                                $message = 'Failed to activate license. Please try again later.';
                                $message_type = 'error';
                            }
                        } else {
                            $message = 'Invalid product type. This license key is not for the WPiko AI Chatbot plugin.';
                            $message_type = 'error';
                        }
                    } else {
                        $message = 'Invalid, expired, or already activated license key.';
                        $message_type = 'error';
                    }
                }
            }
        } // Close the else statement for nonce verification
    }

    $encrypted_license_key = get_option('wpiko_chatbot_license_key', '');
    $decrypted_license_key = wpiko_chatbot_pro_decrypt_data($encrypted_license_key);
    $masked_license_key = '';

    if (!empty($decrypted_license_key)) {
        $visible_chars = 3;
        $masked_length = max(0, strlen($decrypted_license_key) - $visible_chars);
        $masked_license_key = str_repeat('*', $masked_length) . substr($decrypted_license_key, -$visible_chars);
    }

    $encrypted_status = get_option('wpiko_chatbot_license_status', '');
    $license_status = $encrypted_status ? wpiko_chatbot_pro_decrypt_data($encrypted_status) : 'inactive';
    $encrypted_expiration = get_option('wpiko_chatbot_license_expiration', '');
    $license_expiration = wpiko_chatbot_pro_decrypt_data($encrypted_expiration);
    $encrypted_lifetime = get_option('wpiko_chatbot_license_is_lifetime', '');
    $is_lifetime = wpiko_chatbot_pro_decrypt_data($encrypted_lifetime) === '1';

    // Generate nonces for AJAX requests
    $delete_nonce = wp_create_nonce('wpiko_chatbot_delete_license');
    $check_nonce = wp_create_nonce('wpiko_chatbot_nonce');
    $test_connection_nonce = wp_create_nonce('wpiko_chatbot_test_connection');

    // Localize the script with new nonce
    wp_localize_script('wpiko-chatbot-license-management', 'wpiko_chatbot_license', array(
        'delete_nonce' => $delete_nonce,
        'check_nonce' => $check_nonce,
        'test_connection_nonce' => $test_connection_nonce
    ));

    ?>
    <div class="wpiko-chatbot-settings-page wpiko-license-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>License Activation</h1>
            <p class="wpiko-chatbot-settings-subtitle">Manage your WPiko AI Chatbot Pro license and check your connection and update status.</p>
        </header>
        <?php if (!empty($message)): ?>
            <div class="wpiko-chatbot-settings-notice is-<?php echo esc_attr($message_type); ?>" role="status"><p><?php echo wp_kses_post($message); ?></p></div>
        <?php endif; ?>
        <?php if ($license_status === 'expired'): ?>
            <div class="wpiko-chatbot-settings-notice is-error" role="alert">
                <p><?php esc_html_e('Your license has expired. If it was converted to lifetime, click Refresh License below. Otherwise get a lifetime license to continue using all Pro features.', 'wpiko-chatbot-pro'); ?></p>
            </div>
        <?php endif; ?>
        <div class="wpiko-chatbot-settings-content">
            <section class="wpiko-chatbot-settings-card" aria-labelledby="license-details-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="license-details-title">License details</h2>
                <form method="post" action="" class="license-details-form">
                    <?php wp_nonce_field('wpiko_chatbot_license_action', 'wpiko_chatbot_license_nonce'); ?>
                    <div class="wpiko-chatbot-settings-field-row">
                        <label class="wpiko-chatbot-settings-field-label" for="wpiko-chatbot-license-key">License key</label>
                        <div class="wpiko-chatbot-settings-field-control">
                            <div class="license-key-controls">
                                <input type="text" id="wpiko-chatbot-license-key" name="wpiko_chatbot_license_key" value="<?php echo esc_attr($masked_license_key); ?>" class="<?php echo $license_status === 'expired' ? 'expired-license' : ''; ?>" placeholder="Enter your license key" autocomplete="off" spellcheck="false" aria-describedby="license-key-description" <?php echo $license_status === 'active' ? 'readonly' : ''; ?>>
                                <?php if (in_array($license_status, ['active', 'expired'])): ?>
                                    <button type="button" id="wpiko-delete-license" class="button wpiko-chatbot-settings-danger"><span class="dashicons dashicons-trash" aria-hidden="true"></span>Delete License</button>
                                <?php endif; ?>
                            </div>
                            <p class="wpiko-chatbot-settings-field-description" id="license-key-description">
                                <?php if ($license_status === 'active'): ?>
                                    Your saved license key is masked for security.
                                <?php else: ?>
                                    Enter your key to unlock Pro features. <a href="https://wpiko.com/chatbot-pricing/" target="_blank" rel="noopener">Get a license key</a>.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="wpiko-chatbot-settings-field-row">
                        <div class="wpiko-chatbot-settings-field-label">License status</div>
                        <div class="wpiko-chatbot-settings-field-control"><span class="license-status <?php echo esc_attr($license_status); ?>"><?php echo esc_html(ucfirst($license_status)); ?></span></div>
                    </div>
                    <?php if (in_array($license_status, ['active', 'expired'])): ?>
                        <div class="wpiko-chatbot-settings-field-row">
                            <div class="wpiko-chatbot-settings-field-label">Expiration date</div>
                            <div class="wpiko-chatbot-settings-field-control license-expiration">
                                <?php
                                if ($is_lifetime) {
                                    echo 'Lifetime License';
                                } elseif (!empty($license_expiration)) {
                                    echo esc_html(gmdate('F j, Y', strtotime($license_expiration)));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($license_status === 'inactive'): ?>
                        <div class="wpiko-chatbot-settings-actions"><button type="submit" name="submit" id="submit" class="button wpiko-chatbot-settings-save">Activate License</button></div>
                    <?php endif; ?>
                </form>
                <?php if (in_array($license_status, ['active', 'expired'])): ?>
                    <div class="wpiko-chatbot-settings-actions">
                        <button type="button" id="wpiko-chatbot-manual-license-check" class="button wpiko-chatbot-settings-save"><?php esc_html_e('Refresh License', 'wpiko-chatbot-pro'); ?></button>
                        <?php if ($license_status === 'expired'): ?>
                            <a href="https://wpiko.com/chatbot-pricing/" class="button" target="_blank" rel="noopener">Get Lifetime License</a>
                        <?php endif; ?>
                    </div>
                    <div id="wpiko-chatbot-license-check-result" role="status" aria-live="polite"></div>
                <?php endif; ?>
            </section>
            <section class="wpiko-chatbot-settings-card wpiko-connection-diagnostic" aria-labelledby="license-diagnostics-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="license-diagnostics-title">Connection diagnostics</h2>
                <p class="wpiko-chatbot-settings-field-description">Having trouble activating your license? Check whether your server can connect to the licensing server.</p>
                <div class="wpiko-chatbot-settings-actions"><button type="button" id="wpiko-test-connection" class="button">Test Connection</button></div>
                <div id="wpiko-connection-results" style="display: none;" aria-live="polite">
                    <div id="wpiko-connection-loading" style="display: none;" role="status">
                        <span class="spinner is-active" aria-hidden="true"></span>
                        Testing connection to licensing server...
                    </div>
                    <div id="wpiko-connection-output"></div>
                </div>
            </section>
            <?php wpiko_chatbot_pro_github_status_widget(); ?>
        </div>
    </div>
    <?php
    // Ensure the status is always stored encrypted
    if ($license_status !== 'inactive') {
        $encrypted_status = wpiko_chatbot_pro_encrypt_data($license_status);
        update_option('wpiko_chatbot_license_status', $encrypted_status);
    }
    
}
