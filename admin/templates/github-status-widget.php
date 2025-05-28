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
    ?>
    <div class="wpiko-github-status-widget" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #23282d;">
            <span class="dashicons dashicons-update" style="margin-right: 5px;"></span>
            GitHub Update System
        </h3>
        
        <?php if (!$status['config_valid']): ?>
            <div style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 15px;">
                <strong>Configuration Required:</strong>
                <p style="margin: 5px 0 0 0;">
                    <?php echo esc_html($status['config_error']); ?><br>
                    <small>Please update your GitHub settings in <code>includes/github-config.php</code></small>
                </p>
            </div>
        <?php elseif (!$status['connection_valid']): ?>
            <div style="padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; margin-bottom: 15px;">
                <strong>Connection Error:</strong>
                <p style="margin: 5px 0 0 0;">
                    <?php echo esc_html($status['connection_error']); ?>
                </p>
            </div>
        <?php else: ?>
            <div style="padding: 10px; background: #d1edff; border-left: 4px solid #0073aa; margin-bottom: 15px;">
                <strong>✓ GitHub connection is active</strong>
                <p style="margin: 5px 0 0 0;">
                    Automatic updates are working correctly.
                </p>
            </div>
            
            <?php if ($status['update_available']): ?>
                <div style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 15px;">
                    <strong>Update Available!</strong>
                    <p style="margin: 5px 0 0 0;">
                        A new version is available. 
                        <a href="<?php echo admin_url('update-core.php'); ?>">Check for updates</a>
                    </p>
                </div>
            <?php else: ?>
                <p style="color: #46b450; margin: 0;">
                    <span class="dashicons dashicons-yes-alt" style="margin-right: 5px;"></span>
                    Your plugin is up to date.
                </p>
            <?php endif; ?>
        <?php endif; ?>
        
        <div style="border-top: 1px solid #e1e1e1; padding-top: 10px; margin-top: 15px;">
            <p style="margin: 0; color: #666; font-size: 12px;">
                Current Version: <strong><?php echo WPIKO_CHATBOT_PRO_VERSION; ?></strong> | 
                <a href="#" onclick="wpikoChatbotProForceUpdateCheck(); return false;">Force Update Check</a>
            </p>
        </div>
    </div>
    
    <script>
    function wpikoChatbotProForceUpdateCheck() {
        if (confirm('This will force a check for updates. Continue?')) {
            var data = {
                action: 'wpiko_chatbot_pro_force_update_check',
                nonce: '<?php echo wp_create_nonce('wpiko_chatbot_pro_update_check'); ?>'
            };
            
            jQuery.post(ajaxurl, data, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Update check failed: ' + (response.data || 'Unknown error'));
                }
            });
        }
    }
    </script>
    <?php
}

/**
 * AJAX handler for force update check
 */
function wpiko_chatbot_pro_ajax_force_update_check() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wpiko_chatbot_pro_update_check')) {
        wp_die('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Force update check
    $result = wpiko_chatbot_pro_force_update_check();
    
    if ($result) {
        wp_send_json_success('Update check completed');
    } else {
        wp_send_json_error('Update check failed');
    }
}
add_action('wp_ajax_wpiko_chatbot_pro_force_update_check', 'wpiko_chatbot_pro_ajax_force_update_check');
