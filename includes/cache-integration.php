<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Cache Management Integration for WPiko Chatbot Pro
 * 
 * This file extends the cache management system to include Pro plugin features
 */

// Add Pro plugin options to cache monitoring
function wpiko_chatbot_pro_add_cache_monitored_options($options) {
    $pro_options = array(
        // Email capture options
        'wpiko_chatbot_enable_email_capture',
        'wpiko_chatbot_email_capture_title',
        'wpiko_chatbot_email_capture_description',
        'wpiko_chatbot_email_capture_button_text',
        
        // Contact form options
        'wpiko_chatbot_enable_contact_form',
        'wpiko_chatbot_contact_form_dropdown',
        'wpiko_chatbot_contact_form_attachments',
        'wpiko_chatbot_contact_form_dropdown_options',
        
        // reCAPTCHA options
        'wpiko_chatbot_enable_recaptcha',
        'wpiko_chatbot_recaptcha_site_key',
        'wpiko_chatbot_hide_recaptcha_badge',
        
        // WooCommerce integration options
        'wpiko_chatbot_products_auto_sync',
        'wpiko_chatbot_orders_auto_sync',
        
        // License activation (affects feature availability)
        'wpiko_chatbot_pro_license_key',
        'wpiko_chatbot_pro_license_status',
    );
    
    return array_merge($options, $pro_options);
}
add_filter('wpiko_chatbot_cache_monitored_options', 'wpiko_chatbot_pro_add_cache_monitored_options');

// Clear cache when Pro features are updated
function wpiko_chatbot_pro_clear_cache_on_feature_update() {
    // Get the cache manager instance and clear cache
    if (function_exists('wpiko_chatbot_init_cache_manager')) {
        $cache_manager = WPiko_Chatbot_Cache_Manager::get_instance();
        $cache_manager->clear_chatbot_cache();
    }
}

// Hook into Pro plugin specific actions
add_action('wpiko_chatbot_pro_license_activated', 'wpiko_chatbot_pro_clear_cache_on_feature_update');
add_action('wpiko_chatbot_pro_license_deactivated', 'wpiko_chatbot_pro_clear_cache_on_feature_update');
add_action('wpiko_chatbot_pro_contact_form_updated', 'wpiko_chatbot_pro_clear_cache_on_feature_update');
add_action('wpiko_chatbot_pro_email_capture_updated', 'wpiko_chatbot_pro_clear_cache_on_feature_update');

// Add cache version to Pro plugin JavaScript variables
function wpiko_chatbot_pro_add_cache_version_to_js() {
    if (function_exists('wpiko_chatbot_get_cache_version')) {
        $cache_version = wpiko_chatbot_get_cache_version();
        ?>
        <script>
            if (typeof wpikoChatbot !== 'undefined') {
                wpikoChatbot.pro_cache_version = '<?php echo esc_js($cache_version); ?>';
            }
        </script>
        <?php
    }
}
add_action('wp_head', 'wpiko_chatbot_pro_add_cache_version_to_js', 25);

// Update Pro plugin script enqueuing to use dynamic versioning
function wpiko_chatbot_pro_use_dynamic_versioning() {
    if (function_exists('wpiko_chatbot_get_cache_version') && defined('WPIKO_CHATBOT_PRO_VERSION')) {
        $version = WPIKO_CHATBOT_PRO_VERSION;
        $cache_version = wpiko_chatbot_get_cache_version();
        return $version . '-' . $cache_version;
    }
    
    // Fallback to just the plugin version if cache management is not available
    return defined('WPIKO_CHATBOT_PRO_VERSION') ? WPIKO_CHATBOT_PRO_VERSION : false;
}

// Add filter for Pro plugin asset versioning
add_filter('wpiko_chatbot_pro_asset_version', 'wpiko_chatbot_pro_use_dynamic_versioning');
