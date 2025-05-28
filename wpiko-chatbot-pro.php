<?php
/**
 * Plugin Name: WPiko Chatbot Pro
 * Plugin URI: https://wpiko.com/chatbot
 * Description: Premium add-on for WPiko Chatbot with advanced features.
 * Version: 1.0.0
 * Author: WPiko
 * Author URI: https://wpiko.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpiko-chatbot-pro
 *
 * @package WPiko_Chatbot_Pro
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WPIKO_CHATBOT_PRO_VERSION', '1.0.0');
define('WPIKO_CHATBOT_PRO_FILE', __FILE__);
define('WPIKO_CHATBOT_PRO_PATH', plugin_dir_path(__FILE__));
define('WPIKO_CHATBOT_PRO_URL', plugin_dir_url(__FILE__));
define('WPIKO_CHATBOT_PRO_BASENAME', plugin_basename(__FILE__));

// Include GitHub updater files
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/github-config.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/github-updater.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/github-helpers.php';

// Global variable to store updater instance
global $wpiko_chatbot_pro_github_updater;

/**
 * Check if WPiko Chatbot (free version) is active
 */
function wpiko_chatbot_pro_check_base_plugin() {
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    // Check if the base plugin is active
    if (!is_plugin_active('wpiko-chatbot/wpiko-chatbot.php')) {
        add_action('admin_notices', 'wpiko_chatbot_pro_missing_base_plugin_notice');
        deactivate_plugins(plugin_basename(__FILE__));
        // Verify nonce before processing the activate parameter
        if (isset($_GET['activate']) && isset($_REQUEST['_wpnonce']) && wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'activate-plugin_' . plugin_basename(__FILE__))) {
            unset($_GET['activate']);
        }
        return false;
    }
    
    return true;
}

/**
 * Admin notice for missing base plugin
 */
function wpiko_chatbot_pro_missing_base_plugin_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php esc_html_e('WPiko Chatbot Pro requires the free WPiko Chatbot plugin to be installed and activated.', 'wpiko-chatbot-pro'); ?></p>
        <p>
            <?php
            if (file_exists(WP_PLUGIN_DIR . '/wpiko-chatbot/wpiko-chatbot.php')) {
                echo '<a href="' . esc_url(wp_nonce_url('plugins.php?action=activate&plugin=wpiko-chatbot/wpiko-chatbot.php', 'activate-plugin_wpiko-chatbot/wpiko-chatbot.php')) . '" class="button button-primary">' . esc_html__('Activate WPiko Chatbot', 'wpiko-chatbot-pro') . '</a>';
            } else {
                echo '<a href="' . esc_url(wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=wpiko-chatbot'), 'install-plugin_wpiko-chatbot')) . '" class="button button-primary">' . esc_html__('Install WPiko Chatbot', 'wpiko-chatbot-pro') . '</a>';
            }
            ?>
        </p>
    </div>
    <?php
}

/**
 * Initialize the pro plugin
 */
function wpiko_chatbot_pro_init() {
    // Check if the base plugin is active before proceeding
    if (!wpiko_chatbot_pro_check_base_plugin()) {
        return;
    }

    // Include the admin integration file
    require_once WPIKO_CHATBOT_PRO_PATH . 'admin/admin-integration.php';
    
    // Initialize GitHub updater
    if (class_exists('WPiko_Chatbot_Pro_GitHub_Updater')) {
        global $wpiko_chatbot_pro_github_updater;
        $wpiko_chatbot_pro_github_updater = new WPiko_Chatbot_Pro_GitHub_Updater(
            WPIKO_CHATBOT_PRO_FILE,
            'wpiko-chatbot-pro',
            WPIKO_CHATBOT_PRO_VERSION
        );
    }
}
add_action('plugins_loaded', 'wpiko_chatbot_pro_init');

/**
 * Deactivate the license when the pro plugin is deactivated
 */
function wpiko_chatbot_pro_deactivate_license() {
    // Only run if the main plugin function exists
    if (function_exists('wpiko_chatbot_deactivate_license') && function_exists('wpiko_chatbot_encrypt_data')) {
        // Deactivate the license key
        wpiko_chatbot_deactivate_license();
        
        // Update the license status
        update_option('wpiko_chatbot_license_status', wpiko_chatbot_encrypt_data('inactive'));
        
        // Log the deactivation
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('WPiko Chatbot Pro plugin deactivated - license has been deactivated', 'info');
        }
    }
}
register_deactivation_hook(__FILE__, 'wpiko_chatbot_pro_deactivate_license');

/**
 * Clean license key and related options on plugin activation
 */
function wpiko_chatbot_pro_clean_license_on_activation() {
    // Remove license key and related options
    delete_option('wpiko_chatbot_license_key');
    delete_option('wpiko_chatbot_license_status');
    delete_option('wpiko_chatbot_license_expiration');
    delete_option('wpiko_chatbot_license_is_lifetime');
    delete_option('wpiko_chatbot_license_domain');
    delete_option('wpiko_chatbot_license_source_domain');
    delete_option('wpiko_chatbot_license_product_type');
}
register_activation_hook(__FILE__, 'wpiko_chatbot_pro_clean_license_on_activation');