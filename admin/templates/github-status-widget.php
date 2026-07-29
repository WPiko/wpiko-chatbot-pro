<?php
/**
 * GitHub Update Status Widget
 * 
 * Admin widget to display GitHub update status.
 * 
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Display GitHub update status widget
 */
function wpiko_chatbot_pro_github_status_widget() {
    $status = wpiko_chatbot_pro_get_github_status();
    $connection_ok = $status['config_valid'] && $status['connection_valid'];
    ?>
    <div class="wpiko-github-status-widget">
        <h3>
            <span class="dashicons dashicons-update"></span>
            GitHub Update System
        </h3>

        <?php if (!$status['config_valid']): ?>
            <div class="github-status-banner is-error">
                <span class="github-status-banner__icon dashicons dashicons-warning"></span>
                <div class="github-status-banner__content">
                    <span class="github-status-banner__title">Configuration Required</span>
                    <p class="github-status-banner__text">
                        <?php echo esc_html($status['config_error']); ?><br>
                        <small>Please update your GitHub settings in <code>includes/github-config.php</code>.</small>
                    </p>
                </div>
            </div>
        <?php elseif (!$status['connection_valid']): ?>
            <div class="github-status-banner is-error">
                <span class="github-status-banner__icon dashicons dashicons-dismiss"></span>
                <div class="github-status-banner__content">
                    <span class="github-status-banner__title">Connection Error</span>
                    <p class="github-status-banner__text"><?php echo esc_html($status['connection_error']); ?></p>
                </div>
            </div>
        <?php elseif ($status['update_available']): ?>
            <div class="github-status-banner is-update">
                <span class="github-status-banner__icon dashicons dashicons-download"></span>
                <div class="github-status-banner__content">
                    <span class="github-status-banner__title">Update Available</span>
                    <p class="github-status-banner__text">
                        A new version is ready to install.
                        <a href="<?php echo esc_url(admin_url('update-core.php')); ?>">Go to updates &rarr;</a>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="github-status-banner is-success">
                <span class="github-status-banner__icon dashicons dashicons-yes-alt"></span>
                <div class="github-status-banner__content">
                    <span class="github-status-banner__title">You&rsquo;re up to date</span>
                    <p class="github-status-banner__text">GitHub connection is active and automatic updates are working correctly.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="github-status-footer">
            <div class="github-status-meta">
                <span class="github-status-pill <?php echo $connection_ok ? 'is-connected' : 'is-disconnected'; ?>">
                    <span class="github-status-pill__dot"></span>
                    <?php echo $connection_ok ? 'Connected' : 'Disconnected'; ?>
                </span>
                <span class="github-status-version">
                    <span class="github-status-version__label">Current version</span>
                    <span class="github-status-version__value">v<?php echo esc_html(WPIKO_CHATBOT_PRO_VERSION); ?></span>
                </span>
            </div>
            <button type="button" id="force-update-check-btn" class="button button-secondary github-force-check-btn" onclick="wpikoChatbotProForceUpdateCheck(); return false;">
                <span class="dashicons dashicons-update github-force-check-btn__icon"></span>
                <span class="github-force-check-btn__label">Force Update Check</span>
            </button>
        </div>
        <div id="github-update-check-result"></div>
    </div>

    <script>
    function wpikoChatbotProForceUpdateCheck() {
        if (confirm('This will force a check for updates. Continue?')) {
            var $btn = jQuery('#force-update-check-btn');
            var $label = $btn.find('.github-force-check-btn__label');
            var $icon = $btn.find('.github-force-check-btn__icon');
            var $result = jQuery('#github-update-check-result');

            // Show loading state
            $btn.prop('disabled', true);
            $icon.addClass('is-spinning');
            $label.text('Checking...');
            $result.removeClass('github-update-success-message github-update-error-message')
                   .text('Checking for updates...')
                   .show();

            var data = {
                action: 'wpiko_chatbot_pro_force_update_check',
                nonce: '<?php echo esc_attr(wp_create_nonce('wpiko_chatbot_pro_update_check')); ?>'
            };

            jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', data, function(response) {
                setTimeout(function() {
                    $btn.prop('disabled', false);
                    $icon.removeClass('is-spinning');
                    $label.text('Force Update Check');
                    $result.removeClass('github-update-success-message github-update-error-message');

                    if (response.success) {
                        $result.text(response.data.message || 'Update check completed successfully!')
                               .addClass('github-update-success-message');

                        // Auto-hide success message after 5 seconds and reload if update available
                        setTimeout(function() {
                            $result.fadeOut();
                            if (response.data.update_available) {
                                location.reload();
                            }
                        }, 5000);
                    } else {
                        var errorMessage = response.data && response.data.message ?
                                         response.data.message :
                                         (response.data || 'Update check failed. Please try again.');
                        $result.text(errorMessage)
                               .addClass('github-update-error-message');
                    }
                }, 500);
            }).fail(function() {
                setTimeout(function() {
                    $btn.prop('disabled', false);
                    $icon.removeClass('is-spinning');
                    $label.text('Force Update Check');
                    $result.removeClass('github-update-success-message github-update-error-message')
                           .text('Network error. Please check your connection and try again.')
                           .addClass('github-update-error-message');
                }, 500);
            });
        }
    }
    </script>
    <?php
}

/**
 * AJAX handler for force update check
 */
/**
 * AJAX handler for forcing update checks
 */
function wpiko_chatbot_pro_ajax_force_update_check() {
    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'wpiko_chatbot_pro_update_check')) {
        wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions. Administrator access required.'));
    }
    
    // Check if helper function exists
    if (!function_exists('wpiko_chatbot_pro_force_update_check')) {
        wp_send_json_error(array('message' => 'Update helper function not available. Please check plugin installation.'));
    }
    
    // Check if updater is available
    $updater = wpiko_chatbot_pro_get_github_updater();
    if (!$updater) {
        wp_send_json_error(array('message' => 'GitHub updater not initialized. Please check configuration.'));
    }
    
    // Force update check
    $result = wpiko_chatbot_pro_force_update_check();
    
    if ($result) {
        // Check if an update is available after clearing cache
        $update_available = wpiko_chatbot_pro_is_update_available();
        $latest_version_info = wpiko_chatbot_pro_get_latest_version_info();
        
        if ($update_available && $latest_version_info) {
            $current_version = WPIKO_CHATBOT_PRO_VERSION;
            $latest_version = isset($latest_version_info['tag_name']) ? ltrim($latest_version_info['tag_name'], 'v') : 'Unknown';
            
            wp_send_json_success(array(
                'message' => "Update check completed! New version {$latest_version} is available (current: {$current_version}). The page will reload to show the update.",
                'update_available' => true,
                'current_version' => $current_version,
                'latest_version' => $latest_version
            ));
        } else {
            wp_send_json_success(array(
                'message' => 'Update check completed successfully. Your plugin is up to date!',
                'update_available' => false,
                'current_version' => WPIKO_CHATBOT_PRO_VERSION
            ));
        }
    } else {
        // Get GitHub status for more detailed error information
        $github_status = wpiko_chatbot_pro_get_github_status();
        
        if (!$github_status['config_valid']) {
            wp_send_json_error(array('message' => 'GitHub configuration error: ' . $github_status['config_error']));
        } elseif (!$github_status['connection_valid']) {
            wp_send_json_error(array('message' => 'GitHub connection error: ' . $github_status['connection_error']));
        } else {
            wp_send_json_error(array('message' => 'Update check failed. Please check your GitHub configuration and try again.'));
        }
    }
}
add_action('wp_ajax_wpiko_chatbot_pro_force_update_check', 'wpiko_chatbot_pro_ajax_force_update_check');
