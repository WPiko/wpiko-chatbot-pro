<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Hook to handle license status changes
function wpiko_chatbot_pro_handle_license_change($new_status) {
    if ($new_status !== 'active') {
        // Disable auto-delete when license becomes inactive or expires
        update_option('wpiko_chatbot_enable_auto_delete', false);
        wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
        
        // Log this action for debugging purposes
        wpiko_chatbot_log('Auto-delete disabled due to license status change to: ' . $new_status, 'info');
    }
}
add_action('wpiko_chatbot_license_status_changed', 'wpiko_chatbot_pro_handle_license_change');

// Function to check and update auto-delete status regularly
function wpiko_chatbot_pro_check_auto_delete_status() {
    if (!wpiko_chatbot_is_license_active() && get_option('wpiko_chatbot_enable_auto_delete', false)) {
        // If license is not active but auto-delete is enabled, disable it
        update_option('wpiko_chatbot_enable_auto_delete', false);
        wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
        wpiko_chatbot_log('Auto-delete disabled during regular check - license inactive', 'info');
    }
}
add_action('admin_init', 'wpiko_chatbot_pro_check_auto_delete_status');

// Function to Auto delete old conversations
function wpiko_chatbot_pro_delete_old_conversations() {
    // Force check license status before running
    if (!wpiko_chatbot_is_license_active()) {
        // Ensure auto-delete is disabled if license is not active
        if (get_option('wpiko_chatbot_enable_auto_delete', false)) {
            update_option('wpiko_chatbot_enable_auto_delete', false);
            wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
            wpiko_chatbot_log('Auto-delete disabled during scheduled task - license inactive', 'info');
        }
        return;
    }
    
    // Get current license status (double check)
    $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
    
    // Check if license is active (not expired or invalid)
    if ($license_status !== 'active' || !get_option('wpiko_chatbot_enable_auto_delete', false)) {
        // If license is not active, disable auto-delete
        if ($license_status !== 'active') {
            update_option('wpiko_chatbot_enable_auto_delete', false);
            wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
            wpiko_chatbot_log('Auto-delete disabled during scheduled task - license status: ' . $license_status, 'info');
        }
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    $days = intval(get_option('wpiko_chatbot_auto_delete_days', 90));
    
    $result = $wpdb->query($wpdb->prepare(
        "DELETE FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $days
    ));
    
    if ($result !== false) {
        wpiko_chatbot_log('Successfully deleted ' . $result . ' old conversations', 'info');
    } else {
        wpiko_chatbot_log('Error deleting old conversations', 'error');
    }
}
add_action('wpiko_chatbot_auto_delete_conversations', 'wpiko_chatbot_pro_delete_old_conversations');

// Handle plugin activation/deactivation
function wpiko_chatbot_pro_schedule_auto_delete() {
    if (wpiko_chatbot_is_license_active() && get_option('wpiko_chatbot_enable_auto_delete', false)) {
        if (!wp_next_scheduled('wpiko_chatbot_auto_delete_conversations')) {
            wp_schedule_event(time(), 'daily', 'wpiko_chatbot_auto_delete_conversations');
        }
    }
}
add_action('wp', 'wpiko_chatbot_pro_schedule_auto_delete');

// Clear scheduled event on plugin deactivation
function wpiko_chatbot_pro_clear_auto_delete_schedule() {
    wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
}
register_deactivation_hook(__FILE__, 'wpiko_chatbot_pro_clear_auto_delete_schedule');