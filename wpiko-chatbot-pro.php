<?php
/**
 * Plugin Name: WPiko Chatbot Pro
 * Plugin URI: https://wpiko.com/chatbot
 * Description: Premium add-on for WPiko Chatbot with advanced features.
 * Version: 1.0.7
 * Requires at least: 5.0
 * Tested up to: 6.8.1
 * Requires PHP: 7.0
 * Author: WPiko
 * Author URI: https://wpiko.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpiko-chatbot-pro
 * Update URI: wpiko-chatbot-pro
 *
 * @package WPiko_Chatbot_Pro
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WPIKO_CHATBOT_PRO_VERSION', '1.0.7');
define('WPIKO_CHATBOT_PRO_FILE', __FILE__);
define('WPIKO_CHATBOT_PRO_PATH', plugin_dir_path(__FILE__));
define('WPIKO_CHATBOT_PRO_URL', plugin_dir_url(__FILE__));
define('WPIKO_CHATBOT_PRO_BASENAME', plugin_basename(__FILE__));

// Include GitHub updater files
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/github-config.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/github-updater.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/github-helpers.php';

// Include pro functions
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/license-activation.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/scan-website.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/qa-management.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/woocommerce-integration.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/contact-form-handler.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/chatbot-interface-integration.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/markdown-handler-integration.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/contact-handler.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/conversation-handler.php';
require_once WPIKO_CHATBOT_PRO_PATH . 'includes/cache-integration.php';

// Global variable to store updater instance
global $wpiko_chatbot_pro_github_updater;

/**
 * Check if WPiko Chatbot (free version) is active
 */
function wpiko_chatbot_pro_check_base_plugin()
{
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
function wpiko_chatbot_pro_missing_base_plugin_notice()
{
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php esc_html_e('WPiko Chatbot Pro requires the free WPiko Chatbot plugin to be installed and activated.', 'wpiko-chatbot-pro'); ?>
        </p>
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
 * Enqueue frontend styles
 */
function wpiko_chatbot_pro_enqueue_frontend_styles()
{
    wp_enqueue_style(
        'wpiko-chatbot-pro-frontend-styles',
        WPIKO_CHATBOT_PRO_URL . 'css/wpiko-chatbot-pro.css',
        array(),
        WPIKO_CHATBOT_PRO_VERSION,
        'all'
    );
}

/**
 * Enqueue email capture scripts
 */
function wpiko_chatbot_pro_enqueue_email_capture_scripts()
{
    // Only enqueue if base plugin is active and email capture is enabled
    if (!wpiko_chatbot_pro_check_base_plugin()) {
        return;
    }

    // Check if email capture is enabled and license is active
    $email_capture_enabled = get_option('wpiko_chatbot_enable_email_capture', false);
    $is_license_active = function_exists('wpiko_chatbot_is_license_active') ? wpiko_chatbot_is_license_active() : false;

    if ($email_capture_enabled && $is_license_active) {
        // Get plugin version
        $version = WPIKO_CHATBOT_PRO_VERSION;

        wp_enqueue_script(
            'wpiko-chatbot-pro-email-capture',
            WPIKO_CHATBOT_PRO_URL . 'js/email-capture.js',
            array('jquery', 'wpiko-chatbot-js'), // Depend on the main chatbot script
            $version,
            true
        );

        // Pass any additional data needed for email capture
        wp_localize_script('wpiko-chatbot-pro-email-capture', 'wpikoProEmailCapture', array(
            'version' => $version,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }
}
add_action('wp_enqueue_scripts', 'wpiko_chatbot_pro_enqueue_email_capture_scripts', 25); // Run after main plugin scripts

/**
 * Initialize the GitHub updater (always available for updates)
 */
function wpiko_chatbot_pro_init_updater()
{
    // Only initialize updater in admin context to avoid frontend interference
    if (!is_admin()) {
        return;
    }

    // Initialize GitHub updater regardless of base plugin status
    // This ensures updates work even if base plugin is temporarily disabled
    if (class_exists('WPiko_Chatbot_Pro_GitHub_Updater')) {
        global $wpiko_chatbot_pro_github_updater;
        $wpiko_chatbot_pro_github_updater = new WPiko_Chatbot_Pro_GitHub_Updater(
            WPIKO_CHATBOT_PRO_FILE,
            'wpiko-chatbot-pro',
            WPIKO_CHATBOT_PRO_VERSION
        );
    }
}

/**
 * Load essential admin functionality (always available)
 */
function wpiko_chatbot_pro_load_admin_essentials()
{
    if (is_admin()) {
        // Load GitHub status widget and AJAX handlers
        require_once WPIKO_CHATBOT_PRO_PATH . 'admin/templates/github-status-widget.php';

        // Add admin menu for update management if base plugin is not active
        add_action('admin_menu', 'wpiko_chatbot_pro_fallback_admin_menu');
    }
}

/**
 * Add fallback admin menu when base plugin is not active
 */
function wpiko_chatbot_pro_fallback_admin_menu()
{
    // Only add if base plugin is not active
    if (!wpiko_chatbot_pro_check_base_plugin()) {
        add_menu_page(
            'WPiko Chatbot Pro',
            'WPiko Chatbot Pro',
            'manage_options',
            'wpiko-chatbot-pro',
            'wpiko_chatbot_pro_fallback_admin_page',
            'dashicons-format-chat',
            81
        );
    }
}

/**
 * Fallback admin page when base plugin is not active
 */
function wpiko_chatbot_pro_fallback_admin_page()
{
    ?>
    <div class="wrap">
        <h1>WPiko Chatbot Pro</h1>

        <?php wpiko_chatbot_pro_missing_base_plugin_notice(); ?>

        <div style="margin-top: 30px;">
            <h2>Update Management</h2>
            <p>You can still manage plugin updates even when the base plugin is not active:</p>

            <?php
            // Display GitHub status widget
            wpiko_chatbot_pro_render_github_status_widget();
            ?>
        </div>
    </div>
    <?php
}

/**
 * Initialize the pro plugin
 */
function wpiko_chatbot_pro_init()
{
    // Always initialize the updater
    wpiko_chatbot_pro_init_updater();

    // Always load admin essentials
    wpiko_chatbot_pro_load_admin_essentials();

    // Check if the base plugin is active before proceeding with other features
    if (!wpiko_chatbot_pro_check_base_plugin()) {
        return;
    }

    // Initialize license activation and enqueue scripts
    add_action('wp_enqueue_scripts', 'wpiko_chatbot_pro_enqueue_scripts');

    // Enqueue frontend styles
    add_action('wp_enqueue_scripts', 'wpiko_chatbot_pro_enqueue_frontend_styles');

    // Include the admin integration file
    require_once WPIKO_CHATBOT_PRO_PATH . 'admin/admin-integration.php';
}

/**
 * Enqueue pro plugin scripts and license activation
 */
function wpiko_chatbot_pro_enqueue_scripts()
{
    // Check if license is active
    $is_license_active = function_exists('wpiko_chatbot_pro_is_license_active') ? wpiko_chatbot_pro_is_license_active() : false;

    if ($is_license_active) {
        // Prepare the license data to add
        $license_data = array(
            'enable_email_capture' => get_option('wpiko_chatbot_enable_email_capture', false) ? '1' : '0',
            'is_license_active' => '1'
        );

        // Convert to JavaScript format - extend the existing wpikoChatbot object
        $license_js = 'if (typeof wpikoChatbot !== "undefined") { ';
        foreach ($license_data as $key => $value) {
            $license_js .= "wpikoChatbot.{$key} = " . json_encode($value) . "; ";
        }
        $license_js .= '}';

        // Add to existing script data
        wp_add_inline_script('wpiko-chatbot-js', $license_js);
    }
}
add_action('plugins_loaded', 'wpiko_chatbot_pro_init');

/**
 * Deactivate the license when the pro plugin is deactivated
 */
function wpiko_chatbot_pro_deactivate_license_on_plugin_deactivation()
{
    // Only run if the main plugin function exists
    if (function_exists('wpiko_chatbot_pro_deactivate_license') && function_exists('wpiko_chatbot_pro_encrypt_data')) {
        // Deactivate the license key
        wpiko_chatbot_pro_deactivate_license();

        // Update the license status
        update_option('wpiko_chatbot_license_status', wpiko_chatbot_pro_encrypt_data('inactive'));

        // Log the deactivation
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('WPiko Chatbot Pro plugin deactivated - license has been deactivated', 'info');
        }
    }
}
register_deactivation_hook(__FILE__, 'wpiko_chatbot_pro_deactivate_license_on_plugin_deactivation');

/**
 * Pro plugin activation handler
 */
function wpiko_chatbot_pro_activation()
{
    // Create QA table for Q&A management functionality
    wpiko_chatbot_pro_create_qa_table();

    // Clean license key and related options
    wpiko_chatbot_pro_clean_license_on_activation();
}
register_activation_hook(__FILE__, 'wpiko_chatbot_pro_activation');

/**
 * Clean license key and related options on plugin activation
 */
function wpiko_chatbot_pro_clean_license_on_activation()
{
    // Remove license key and related options
    delete_option('wpiko_chatbot_license_key');
    delete_option('wpiko_chatbot_license_status');
    delete_option('wpiko_chatbot_license_expiration');
    delete_option('wpiko_chatbot_license_is_lifetime');
    delete_option('wpiko_chatbot_license_domain');
    delete_option('wpiko_chatbot_license_source_domain');
    delete_option('wpiko_chatbot_license_product_type');
}