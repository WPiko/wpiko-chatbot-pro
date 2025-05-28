<?php
/**
 * GitHub Update Helper Functions
 * 
 * Utility functions for managing GitHub-based updates.
 * 
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Get the GitHub updater instance
 * 
 * @return WPiko_Chatbot_Pro_GitHub_Updater|false
 */
function wpiko_chatbot_pro_get_github_updater() {
    global $wpiko_chatbot_pro_github_updater;
    return $wpiko_chatbot_pro_github_updater ?? false;
}

/**
 * Force check for updates
 * 
 * @return bool True if check was initiated
 */
function wpiko_chatbot_pro_force_update_check() {
    $updater = wpiko_chatbot_pro_get_github_updater();
    if ($updater) {
        $updater->force_update_check();
        return true;
    }
    return false;
}

/**
 * Check if an update is available
 * 
 * @return bool
 */
function wpiko_chatbot_pro_is_update_available() {
    $updater = wpiko_chatbot_pro_get_github_updater();
    if ($updater) {
        return $updater->is_update_available();
    }
    return false;
}

/**
 * Get latest version information from GitHub
 * 
 * @return array|false
 */
function wpiko_chatbot_pro_get_latest_version_info() {
    $updater = wpiko_chatbot_pro_get_github_updater();
    if ($updater) {
        return $updater->get_latest_version_info();
    }
    return false;
}

/**
 * Test GitHub connection
 * 
 * @return bool|WP_Error
 */
function wpiko_chatbot_pro_test_github_connection() {
    return WPiko_Chatbot_Pro_GitHub_Config::test_connection();
}

/**
 * Get GitHub configuration status
 * 
 * @return array
 */
function wpiko_chatbot_pro_get_github_status() {
    $config_valid = WPiko_Chatbot_Pro_GitHub_Config::validate_config();
    $connection_test = false;
    
    if (!is_wp_error($config_valid)) {
        $connection_test = WPiko_Chatbot_Pro_GitHub_Config::test_connection();
    }
    
    return array(
        'config_valid' => !is_wp_error($config_valid),
        'config_error' => is_wp_error($config_valid) ? $config_valid->get_error_message() : null,
        'connection_valid' => !is_wp_error($connection_test),
        'connection_error' => is_wp_error($connection_test) ? $connection_test->get_error_message() : null,
        'update_available' => wpiko_chatbot_pro_is_update_available(),
    );
}

/**
 * Display GitHub update status in admin
 * 
 * @return void
 */
function wpiko_chatbot_pro_display_github_status() {
    $status = wpiko_chatbot_pro_get_github_status();
    
    echo '<div class="wpiko-github-status">';
    echo '<h4>GitHub Update Status</h4>';
    
    if (!$status['config_valid']) {
        echo '<div class="notice notice-error"><p><strong>Configuration Error:</strong> ' . esc_html($status['config_error']) . '</p></div>';
    } elseif (!$status['connection_valid']) {
        echo '<div class="notice notice-warning"><p><strong>Connection Error:</strong> ' . esc_html($status['connection_error']) . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p><strong>GitHub connection is working correctly.</strong></p></div>';
        
        if ($status['update_available']) {
            echo '<div class="notice notice-info"><p><strong>A new version is available!</strong> Please check the WordPress updates page.</p></div>';
        } else {
            echo '<p>Your plugin is up to date.</p>';
        }
    }
    
    echo '</div>';
}

/**
 * Add GitHub status to plugin row meta
 * 
 * @param array $plugin_meta
 * @param string $plugin_file
 * @return array
 */
function wpiko_chatbot_pro_add_github_meta($plugin_meta, $plugin_file) {
    if ($plugin_file === WPIKO_CHATBOT_PRO_BASENAME) {
        $status = wpiko_chatbot_pro_get_github_status();
        
        if ($status['config_valid'] && $status['connection_valid']) {
            $plugin_meta[] = '<span style="color: #46b450;">GitHub Updates: Active</span>';
        } else {
            $plugin_meta[] = '<span style="color: #dc3232;">GitHub Updates: Error</span>';
        }
    }
    
    return $plugin_meta;
}
add_filter('plugin_row_meta', 'wpiko_chatbot_pro_add_github_meta', 10, 2);

/**
 * Add admin notice for GitHub configuration
 */
function wpiko_chatbot_pro_github_admin_notices() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Only show on admin pages
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, array('plugins', 'update-core'))) {
        return;
    }
    
    $status = wpiko_chatbot_pro_get_github_status();
    
    if (!$status['config_valid']) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>WPiko Chatbot Pro:</strong> GitHub update configuration needs attention. ';
        echo 'Please configure your GitHub settings in the plugin files to enable automatic updates.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'wpiko_chatbot_pro_github_admin_notices');
