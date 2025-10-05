<?php
/**
 * Functions to integrate the pro version with the base plugin
 * 
 * This file contains hooks and filters to add new tabs and functionality to the main plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Add tabs to the base plugin's tab list
 */
function wpiko_chatbot_pro_add_tab($tabs) {
   
    // Add Email Capture tab
    $tabs['email_capture'] = array(
        'label' => 'Email Capture',
        'icon' => 'dashicons-email',
    );
   
    // Add Contact Form tab
    $tabs['contact_form'] = array(
        'label' => 'Contact Form',
        'icon' => 'dashicons-email-alt',
    );
    
    // Add Product Card tabs only if WooCommerce is active
    if (class_exists('WooCommerce')) {
        $tabs['product_card'] = array(
            'label' => 'Product Card',
            'icon' => 'dashicons-align-full-width',
        );
    }
    
    // Add Analytics tab
    $tabs['analytics'] = array(
        'label' => 'Analytics',
        'icon' => 'dashicons-chart-bar',
    );
    
    // Add License Activation tabs
    $tabs['license_activation'] = array(
        'label' => 'License Activation',
        'icon' => 'dashicons-unlock',
    );
    
    return $tabs;
}
add_filter('wpiko_chatbot_admin_tabs', 'wpiko_chatbot_pro_add_tab');

/**
 * Add Pro sections to the WordPress admin menu
 */
function wpiko_chatbot_pro_admin_menu() {
    // Only add submenu items if the main menu exists
    global $submenu;
    if (!isset($submenu['ai-chatbot'])) {
        return;
    }
    
    // Add Email Capture submenu
    add_submenu_page(
        'ai-chatbot', 
        'Email Capture', 
        'Email Capture', 
        'manage_options', 
        'ai-chatbot&tab=email_capture', 
        'wpiko_chatbot_admin_page'
    );
    
    // Add Contact Form submenu
    add_submenu_page(
        'ai-chatbot', 
        'Contact Form', 
        'Contact Form', 
        'manage_options', 
        'ai-chatbot&tab=contact_form', 
        'wpiko_chatbot_admin_page'
    );
    
    // Add Product Card submenu only if WooCommerce is active
    if (class_exists('WooCommerce')) {
        add_submenu_page(
            'ai-chatbot', 
            'Product Card', 
            'Product Card', 
            'manage_options', 
            'ai-chatbot&tab=product_card', 
            'wpiko_chatbot_admin_page'
        );
    }
    
    // Add Analytics submenu
    add_submenu_page(
        'ai-chatbot', 
        'Analytics', 
        'Analytics', 
        'manage_options', 
        'ai-chatbot&tab=analytics', 
        'wpiko_chatbot_admin_page'
    );
    
    // Add License Activation submenu
    add_submenu_page(
        'ai-chatbot', 
        'License Activation', 
        'License Activation', 
        'manage_options', 
        'ai-chatbot&tab=license_activation', 
        'wpiko_chatbot_admin_page'
    );
}
add_action('admin_menu', 'wpiko_chatbot_pro_admin_menu', 20); // Run after the main menu is created

/**
 * Register CSS for our tabs
 */
function wpiko_chatbot_pro_enqueue_styles($hook) {
    if ($hook !== 'toplevel_page_ai-chatbot') {
        return;
    }
    
    $version = apply_filters('wpiko_chatbot_pro_asset_version', WPIKO_CHATBOT_PRO_VERSION);
        
    // Add email capture CSS
    wp_enqueue_style(
        'wpiko-chatbot-email-capture-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/email-capture.css', 
        array(), 
        $version
    );
    
    // Add Premium feature styles CSS
    wp_enqueue_style(
        'wpiko-chatbot-premium-feature-style-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/premium-feature-style.css', 
        array(), 
        $version
    );
    
    // Add scan website CSS
    wp_enqueue_style(
        'wpiko-chatbot-scan-website-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/scan-website.css', 
        array(), 
        $version
    );
    
    // Add qa management CSS
    wp_enqueue_style(
        'wpiko-chatbot-qa-management-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/qa-management.css', 
        array(), 
        $version
    );
    
    // Add woocommerce integration CSS
    wp_enqueue_style(
        'wpiko-chatbot-woocommerce-integration-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/woocommerce-integration.css', 
        array(), 
        $version
    );
    
    // Add product card CSS
    wp_enqueue_style(
        'wpiko-chatbot-product-card-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/product-card.css', 
        array(), 
        $version
    );
    
    // Add contact form CSS
    wp_enqueue_style(
        'wpiko-chatbot-contact-form-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/contact-form.css', 
        array(), 
        $version
    );
    
    // Add license activation CSS
    wp_enqueue_style(
        'wpiko-chatbot-license-activation-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/license-activation.css', 
        array(), 
        $version
    );
    
    // Enqueue JS files for premium features
    wp_enqueue_script(
        'wpiko-chatbot-license-management',
        WPIKO_CHATBOT_PRO_URL . 'admin/js/license-management.js',
        array('jquery'),
        $version,
        true
    );
    
    wp_enqueue_script(
        'wpiko-chatbot-scan-website-js', 
        WPIKO_CHATBOT_PRO_URL . 'admin/js/scan-website.js', 
        array('jquery'), 
        $version, 
        true
    );
    
    wp_enqueue_script(
        'wpiko-chatbot-qa-management-js', 
        WPIKO_CHATBOT_PRO_URL . 'admin/js/qa-management.js', 
        array('jquery'), 
        $version, 
        true
    );
    
    wp_enqueue_script(
        'wpiko-chatbot-woocommerce-integration-js', 
        WPIKO_CHATBOT_PRO_URL . 'admin/js/woocommerce-integration.js', 
        array('jquery'), 
        $version, 
        true
    );
    
    wp_enqueue_script(
        'wpiko-chatbot-pro-conversations', 
        WPIKO_CHATBOT_PRO_URL . 'admin/js/conversations.js', 
        array('jquery'), 
        $version, 
        true
    );
}
add_action('admin_enqueue_scripts', 'wpiko_chatbot_pro_enqueue_styles');

/**
 * Add section content to the switch statement in the base plugin
 */
function wpiko_chatbot_pro_add_tab_content($active_tab) {
    if ($active_tab === 'email_capture') {
        require_once WPIKO_CHATBOT_PRO_PATH . 'admin/sections/email-capture-section.php';
        wpiko_chatbot_email_capture_section();
        return true;
    }
    if ($active_tab === 'analytics') {
        require_once WPIKO_CHATBOT_PRO_PATH . 'admin/sections/analytics-section.php';
        wpiko_chatbot_analytics_section();
        return true;
    }
    if ($active_tab === 'contact_form') {
        require_once WPIKO_CHATBOT_PRO_PATH . 'admin/sections/contact-form-section.php';
        wpiko_chatbot_contact_form_section();
        return true;
    }
    if ($active_tab === 'product_card' && class_exists('WooCommerce')) {
        require_once WPIKO_CHATBOT_PRO_PATH . 'admin/sections/product-card-section.php';
        wpiko_chatbot_product_card_section();
        return true;
    }
    if ($active_tab === 'license_activation') {
        require_once WPIKO_CHATBOT_PRO_PATH . 'admin/sections/license-activation-section.php';
        wpiko_chatbot_license_activation_page();
        return true;
    }
    return false;
}
add_action('wpiko_chatbot_admin_tab_content', 'wpiko_chatbot_pro_add_tab_content');

/**
 * Add user location to conversation details
 */
function wpiko_chatbot_pro_add_user_location() {
    if (wpiko_chatbot_pro_is_license_active()) {
        echo '<div class="user-country"></div>';
    } else {
        echo '<div class="user-country locked">
            <span>User Location</span>
            <span class="lock-icon dashicons dashicons-lock" title="Upgrade to unlock user location"></span>
        </div>';
    }
}
add_action('wpiko_chatbot_conversation_user_location', 'wpiko_chatbot_pro_add_user_location');

/**
 * Add contact user button to conversation details
 */
function wpiko_chatbot_pro_add_contact_button() {
    if (wpiko_chatbot_pro_is_license_active()) {
        echo '<button class="button contact-user" disabled><span class="dashicons dashicons-email-alt"></span> Contact User</button>';
    } else {
        echo '<div class="contact-user-locked">
            <span class="lock-icon dashicons dashicons-lock" title="Upgrade to unlock contact user feature"></span>
            <div class="button-content">
                <span class="dashicons dashicons-email-alt"></span>
                <span>Contact User</span>
            </div>
        </div>';
    }
}
add_action('wpiko_chatbot_conversation_contact_user_button', 'wpiko_chatbot_pro_add_contact_button');

/**
 * Add auto delete settings section
 */
function wpiko_chatbot_pro_add_auto_delete_settings() {
    // Force disable auto-delete if license is not active
    if (!wpiko_chatbot_pro_is_license_active() && get_option('wpiko_chatbot_enable_auto_delete', false)) {
        update_option('wpiko_chatbot_enable_auto_delete', false);
        wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
    }
    
    // Process auto-delete settings
    if (isset($_POST['auto_delete_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['auto_delete_nonce'])), 'save_auto_delete_settings')) {
        if (wpiko_chatbot_pro_is_license_active()) {
            $enable_auto_delete = isset($_POST['enable_auto_delete']);
            
            update_option('wpiko_chatbot_enable_auto_delete', $enable_auto_delete);
            
            // Only process auto_delete_days if auto-delete is enabled
            if ($enable_auto_delete && isset($_POST['auto_delete_days'])) {
                $auto_delete_days = intval($_POST['auto_delete_days']);
                update_option('wpiko_chatbot_auto_delete_days', $auto_delete_days);
            }
        
            if ($enable_auto_delete) {
                wp_schedule_event(time(), 'daily', 'wpiko_chatbot_auto_delete_conversations');
            } else {
                wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
            }
        
            echo '<div class="notice notice-success"><p>Auto-delete settings updated successfully.</p></div>';
        } else {
            // If license is not active, ensure auto-delete is disabled
            update_option('wpiko_chatbot_enable_auto_delete', false);
            wp_clear_scheduled_hook('wpiko_chatbot_auto_delete_conversations');
            echo '<div class="notice notice-error"><p>Auto-delete settings cannot be enabled without an active license.</p></div>';
        }
    }

    // Check if license is expired
    $is_license_expired = function_exists('wpiko_chatbot_is_license_expired') ? wpiko_chatbot_is_license_expired() : false;
    
    if (wpiko_chatbot_pro_is_license_active()) {
        ?>
        <div class="auto-delete-section">
            <h3><span class="dashicons dashicons-clock"></span> Auto Delete Settings</h3>
            <form method="post" action="" class="auto-delete-form">
                <?php wp_nonce_field('save_auto_delete_settings', 'auto_delete_nonce'); ?>
                <label>
                    <input type="checkbox" name="enable_auto_delete" 
                           <?php checked(get_option('wpiko_chatbot_enable_auto_delete', false)); ?>>
                    Enable Auto Delete
                </label>
                <select name="auto_delete_days" <?php disabled(!get_option('wpiko_chatbot_enable_auto_delete', false)); ?>>
                    <option value="7" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 7); ?>>7 days</option>
                    <option value="14" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 14); ?>>14 days</option>
                    <option value="30" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 30); ?>>30 days</option>
                    <option value="60" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 60); ?>>60 days</option>
                    <option value="90" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 90); ?>>90 days</option>
                    <option value="365" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 365); ?>>1 year</option>
                </select>
                <input type="submit" class="button button-secondary" value="Save Settings">
            </form>
            <p class="auto-delete-description">
                Automatically deletes conversations older than the selected time period. For example, if set to 7 days, any conversations older than 7 days will be permanently deleted.
            </p>
        </div>
    <?php
    } elseif ($is_license_expired) {
        ?>
        <div class="premium-feature-notice auto-delete-notice">
            <h3>🔒 Auto Delete Disabled</h3>
            <p>Your license has expired. Auto-delete feature has been disabled.</p>
            <p>Renew your license to continue using the auto-delete feature:</p>
            <ul>
                <li>✨ Re-enable automatic conversation cleanup</li>
                <li>⚙️ Restore your previous auto-delete settings</li>
                <li>🔄 Resume automated maintenance</li>
            </ul>
            <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Renew License</a>
        </div>
    <?php
    } else {
        ?>
        <div class="premium-feature-notice auto-delete-notice">
            <h3>🗑️ Unlock Auto Delete <span class="premium-feature-badge" style="margin-left: 5px;">Premium</span></h3>
            <p>Upgrade to Premium to automatically clean up old conversations:</p>
            <ul>
                <li>✨ Automatically delete old conversations</li>
                <li>⚙️ Configure deletion timeframe</li>
                <li>🔄 Set it and forget it maintenance</li>
                <li>💾 Optimize database storage</li>
                <li>🚀 Improve site performance</li>
            </ul>
            <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Upgrade to Premium</a>
        </div>
    <?php
    }
}
add_action('wpiko_chatbot_conversation_auto_delete_settings', 'wpiko_chatbot_pro_add_auto_delete_settings');

/**
 * Add email capture option to the chatbot menu
 * NOTE: This function is no longer used as Email Capture has been moved to its own section
 * Keeping for backward compatibility but not hooking it to the action
 */
function wpiko_chatbot_pro_add_email_capture_to_menu() {
    // Handle email capture setting save
    if (isset($_POST['action']) && $_POST['action'] == 'save_chatbot_menu') {
        // Verify nonce before processing form data
        if (isset($_POST['chatbot_menu_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['chatbot_menu_nonce'])), 'save_chatbot_menu')) {
            $enable_email_capture = isset($_POST['wpiko_chatbot_enable_email_capture']) ? '1' : '0';
            update_option('wpiko_chatbot_enable_email_capture', $enable_email_capture);
        }
    }
    
    // Get current setting
    $enable_email_capture = get_option('wpiko_chatbot_enable_email_capture', '0');
    ?>
    <tr valign="top">
        <th scope="row"><label for="wpiko_chatbot_enable_email_capture">Enable Email Capture</label></th>
        <td>
            <?php if (wpiko_chatbot_pro_is_license_active()): ?>
                <label class="wpiko-switch">
                    <input type="checkbox" name="wpiko_chatbot_enable_email_capture" id="wpiko_chatbot_enable_email_capture" value="1" <?php checked($enable_email_capture, '1'); ?>>
                    <span class="wpiko-slider round"></span>
                </label>
                <p class="description">When enabled, users will be asked to provide their name and email before starting the chat.</p>
            <?php else: ?>
                <label class="wpiko-switch disabled">
                    <input type="checkbox" disabled>
                    <span class="wpiko-slider round"></span>
                </label>
                <p class="description">
                    <span class="premium-feature-badge">Premium</span>
                    This feature requires a premium license. 
                    <a href="?page=ai-chatbot&tab=license_activation">Upgrade now</a> to collect user information before chat starts.
                </p>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

/**
 * Add Scan Website button to Responses API Configuration section
 */
function wpiko_chatbot_pro_add_scan_website_button() {
    // Get license status using pro plugin functions
    $is_license_valid = wpiko_chatbot_pro_is_license_active();
    
    ?>
    <button type="button" id="responses-scan-website-button" class="button button-secondary premium-feature <?php echo !$is_license_valid ? 'premium-locked' : ''; ?>">
        <span class="dashicons dashicons-admin-site-alt3"></span> Scan Website
        <?php if (!wpiko_chatbot_pro_is_license_active()): ?>
            <span class="premium-feature-badge">Premium</span>
        <?php endif; ?>
    </button>
    <?php
}
add_action('wpiko_chatbot_responses_scan_website_button', 'wpiko_chatbot_pro_add_scan_website_button');

/**
 * Add Scan Website modal to Responses API Configuration section
 */
function wpiko_chatbot_pro_add_scan_website_modal() {
    ?>
    <!-- Modal container for website scanning -->
    <div id="responses-scan-website-modal" class="wpiko-modal">
        <div class="wpiko-modal-content">
            <span class="wpiko-modal-close">&times;</span>
            <div id="responses-scan-website-container">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
    <?php
}
add_action('wpiko_chatbot_responses_scan_website_modal', 'wpiko_chatbot_pro_add_scan_website_modal');

/**
 * Add Q&A Builder button to Responses API Configuration section
 */
function wpiko_chatbot_pro_add_responses_qa_builder_button() {
    // Get license status using pro plugin functions
    $is_license_valid = wpiko_chatbot_pro_is_license_active();
    
    ?>
    <button type="button" id="responses-qa-management-button" class="button button-secondary premium-feature <?php echo !$is_license_valid ? 'premium-locked' : ''; ?>">
        <span class="dashicons dashicons-insert"></span> Q&A Builder
        <?php if (!wpiko_chatbot_pro_is_license_active()): ?>
            <span class="premium-feature-badge">Premium</span>
        <?php endif; ?>
    </button>
    <?php
}
add_action('wpiko_chatbot_responses_qa_builder_button', 'wpiko_chatbot_pro_add_responses_qa_builder_button');

/**
 * Add Q&A Builder modal to AI Configuration section
 */
function wpiko_chatbot_pro_add_qa_builder_modal() {
    ?>
    <!-- Modal container for QA management -->
    <div id="qa-management-modal" class="wpiko-modal">
        <div class="wpiko-modal-content">
            <span class="wpiko-modal-close">&times;</span>
            <div id="qa-management-container">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
    <?php
}
add_action('wpiko_chatbot_qa_builder_modal', 'wpiko_chatbot_pro_add_qa_builder_modal');

/**
 * Add Q&A Builder modal to Responses API Configuration section
 */
function wpiko_chatbot_pro_add_responses_qa_builder_modal() {
    ?>
    <!-- Modal container for responses QA management -->
    <div id="responses-qa-management-modal" class="wpiko-modal">
        <div class="wpiko-modal-content">
            <span class="wpiko-modal-close">&times;</span>
            <div id="responses-qa-management-container">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
    <?php
}
add_action('wpiko_chatbot_responses_qa_builder_modal', 'wpiko_chatbot_pro_add_responses_qa_builder_modal');

/**
 * Add WooCommerce Integration button to AI Configuration section - Only for Responses API
 */
function wpiko_chatbot_pro_add_woocommerce_integration_button() {
    // Get license status using pro plugin functions
    $is_license_valid = wpiko_chatbot_pro_is_license_active();
    
    ?>
    <button type="button" id="woocommerce-integration-button" class="button button-secondary premium-feature <?php echo !$is_license_valid ? 'premium-locked' : ''; ?>">
        <span class="dashicons dashicons-update-alt"></span> Woocommerce Integration
        <?php if (!wpiko_chatbot_pro_is_license_active()): ?>
            <span class="premium-feature-badge">Premium</span>
        <?php endif; ?>
    </button>
    <?php
}
add_action('wpiko_chatbot_woocommerce_integration_button', 'wpiko_chatbot_pro_add_woocommerce_integration_button');

/**
 * Add WooCommerce Integration button to Responses API Configuration section
 */
function wpiko_chatbot_pro_add_responses_woocommerce_integration_button() {
    // Get license status using pro plugin functions
    $is_license_valid = wpiko_chatbot_pro_is_license_active();
    
    ?>
    <button type="button" id="responses-woocommerce-integration-button" class="button button-secondary premium-feature <?php echo !$is_license_valid ? 'premium-locked' : ''; ?>">
        <span class="dashicons dashicons-update-alt"></span> Woocommerce Integration
        <?php if (!wpiko_chatbot_pro_is_license_active()): ?>
            <span class="premium-feature-badge">Premium</span>
        <?php endif; ?>
    </button>
    <?php
}
add_action('wpiko_chatbot_responses_woocommerce_integration_button', 'wpiko_chatbot_pro_add_responses_woocommerce_integration_button');

/**
 * Add WooCommerce Integration modal to AI Configuration section
 */
function wpiko_chatbot_pro_add_woocommerce_integration_modal() {
    ?>
    <!-- Modal container for Woocommerce integration -->
    <div id="woocommerce-integration-modal" class="wpiko-modal">
        <div class="wpiko-modal-content">
            <span class="wpiko-modal-close">&times;</span>
            <div id="woocommerce-integration-container">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
    <?php
}
add_action('wpiko_chatbot_woocommerce_integration_modal', 'wpiko_chatbot_pro_add_woocommerce_integration_modal');

/**
 * Add WooCommerce Integration modal to Responses API Configuration section
 */
function wpiko_chatbot_pro_add_responses_woocommerce_integration_modal() {
    ?>
    <!-- Modal container for responses Woocommerce integration -->
    <div id="responses-woocommerce-integration-modal" class="wpiko-modal">
        <div class="wpiko-modal-content">
            <span class="wpiko-modal-close">&times;</span>
            <div id="responses-woocommerce-integration-container">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
    <?php
}
add_action('wpiko_chatbot_responses_woocommerce_integration_modal', 'wpiko_chatbot_pro_add_responses_woocommerce_integration_modal');

/**
 * Function to load Responses Scan Website template content
 */
function wpiko_chatbot_pro_load_responses_scan_website_callback() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    ob_start();
    include WPIKO_CHATBOT_PRO_PATH . 'admin/templates/scan-website.php';
    $content = ob_get_clean();
    
    wp_send_json_success($content);
}
add_action('wp_ajax_wpiko_chatbot_load_responses_scan_website', 'wpiko_chatbot_pro_load_responses_scan_website_callback', 20);

/**
 * Functions to load Responses QA Management template content
 */
function wpiko_chatbot_pro_load_responses_qa_management_callback() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    // Set a flag to indicate we're loading for Responses API
    set_transient('wpiko_qa_management_api_context', 'responses', 30);
    
    ob_start();
    include WPIKO_CHATBOT_PRO_PATH . 'admin/templates/qa-management.php';
    $content = ob_get_clean();
    
    // Clean up the transient
    delete_transient('wpiko_qa_management_api_context');
    
    wp_send_json_success($content);
}
add_action('wp_ajax_wpiko_chatbot_load_responses_qa_management', 'wpiko_chatbot_pro_load_responses_qa_management_callback', 20);

/**
 * Functions to load Responses WooCommerce Integration template content
 */
function wpiko_chatbot_pro_load_responses_woocommerce_integration_callback() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    // Set a flag to indicate we're loading for Responses API
    set_transient('wpiko_woocommerce_integration_api_context', 'responses', 30);
    
    ob_start();
    include WPIKO_CHATBOT_PRO_PATH . 'admin/templates/woocommerce-integration.php';
    $content = ob_get_clean();
    
    // Clean up the transient
    delete_transient('wpiko_woocommerce_integration_api_context');
    
    wp_send_json_success($content);
}
add_action('wp_ajax_wpiko_chatbot_load_responses_woocommerce_integration', 'wpiko_chatbot_pro_load_responses_woocommerce_integration_callback', 20);

/**
 * Functions to load QA Management template content
 */
function wpiko_chatbot_pro_load_qa_management_callback() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    ob_start();
    include WPIKO_CHATBOT_PRO_PATH . 'admin/templates/qa-management.php';
    $content = ob_get_clean();
    
    wp_send_json_success($content);
}
add_action('wp_ajax_wpiko_chatbot_load_qa_management', 'wpiko_chatbot_pro_load_qa_management_callback', 20);

/**
 * Functions to load WooCommerce Integration template content
 */
function wpiko_chatbot_pro_load_woocommerce_integration_callback() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    ob_start();
    include WPIKO_CHATBOT_PRO_PATH . 'admin/templates/woocommerce-integration.php';
    $content = ob_get_clean();
    
    wp_send_json_success($content);
}
add_action('wp_ajax_wpiko_chatbot_load_woocommerce_integration', 'wpiko_chatbot_pro_load_woocommerce_integration_callback', 20);

/**
 * Initialize email capture functionality
 */
function wpiko_chatbot_pro_init_email_capture() {
    // Only initialize if base plugin is active
    if (!wpiko_chatbot_pro_check_base_plugin()) {
        return;
    }
    
    // Check if email capture is enabled and license is active
    $email_capture_enabled = get_option('wpiko_chatbot_enable_email_capture', false);
    $is_license_active = function_exists('wpiko_chatbot_is_license_active') ? wpiko_chatbot_is_license_active() : false;
    
    if ($email_capture_enabled && $is_license_active) {
        // Add any additional initialization if needed
        add_action('wp_footer', 'wpiko_chatbot_pro_email_capture_footer_script');
    }
}
add_action('init', 'wpiko_chatbot_pro_init_email_capture');

/**
 * Add footer script for email capture initialization
 */
function wpiko_chatbot_pro_email_capture_footer_script() {
    ?>
    <script type="text/javascript">
        // Initialize email capture if the script is loaded
        if (typeof window.wpikoEmailCapture !== 'undefined') {
            // Email capture is ready
            console.log('WPiko Chatbot Pro: Email capture functionality loaded');
        }
    </script>
    <?php
}

/**
 * Add WooCommerce integration state JavaScript to AI Configuration section
 */
function wpiko_chatbot_pro_add_woocommerce_integration_state() {
    // Only add if WooCommerce is active and license is valid
    if (!wpiko_chatbot_is_woocommerce_active() || !wpiko_chatbot_is_license_active()) {
        return;
    }
    
    $woo_integration_enabled = wpiko_chatbot_is_woocommerce_integration_enabled();
    ?>
    <script type="text/javascript">
        var wpikoWooIntegrationEnabled = <?php echo $woo_integration_enabled ? 'true' : 'false'; ?>;
    </script>
    <?php
}
add_action('wpiko_chatbot_after_ai_configuration_title', 'wpiko_chatbot_pro_add_woocommerce_integration_state');

/**
 * Add WooCommerce system instructions to AI Configuration section
 */
function wpiko_chatbot_pro_add_woocommerce_system_instructions() {
    // Only add if WooCommerce is active, license is valid, and integration is enabled
    if (!wpiko_chatbot_is_woocommerce_active() || !wpiko_chatbot_is_license_active()) {
        return;
    }
    
    $instructions = wpiko_chatbot_get_system_instructions();
    $orders_auto_sync = get_option('wpiko_chatbot_orders_auto_sync', 'disabled');
    ?>
    <tr valign="top" class="products-instructions-row" data-woo-dependent="true" style="display: <?php echo wpiko_chatbot_is_woocommerce_integration_enabled() ? 'table-row' : 'none'; ?>">
        <th scope="row">Products System Instructions</th>
        <td>
            <textarea name="products_system_instructions" id="products_system_instructions" class="large-text" rows="5"><?php
                echo esc_textarea($instructions['products']);
            ?></textarea>
            <p class="description">Edit product-related system instructions for your assistant only if necessary.</p>
        </td>
    </tr>
    <?php 
    // Only show Orders System Instructions if Orders Auto-Sync is enabled
    if ($orders_auto_sync !== 'disabled'): 
    ?>
    <tr valign="top">
        <th scope="row">Orders System Instructions</th>
        <td>
            <textarea name="orders_system_instructions" id="orders_system_instructions" class="large-text" rows="5"><?php 
                echo esc_textarea($instructions['orders']); 
            ?></textarea>
            <p class="description">Edit order-related system instructions for your assistant only if necessary.</p>
        </td>
    </tr>
    <?php endif;
}
add_action('wpiko_chatbot_advanced_system_instructions', 'wpiko_chatbot_pro_add_woocommerce_system_instructions');

/**
 * Add WooCommerce system instructions to Responses API Configuration section
 */
function wpiko_chatbot_pro_add_responses_woocommerce_system_instructions() {
    // Only add if WooCommerce is active, license is valid, and integration is enabled
    if (!wpiko_chatbot_is_woocommerce_active() || !wpiko_chatbot_is_license_active()) {
        return;
    }
    
    $instructions = wpiko_chatbot_get_system_instructions();
    $orders_auto_sync = get_option('wpiko_chatbot_orders_auto_sync', 'disabled');
    ?>
    <tr valign="top" class="products-instructions-row" data-woo-dependent="true" style="display: <?php echo wpiko_chatbot_is_woocommerce_integration_enabled() ? 'table-row' : 'none'; ?>">
        <th scope="row">Products System Instructions</th>
        <td>
            <textarea name="responses_products_system_instructions" id="responses_products_system_instructions" class="large-text" rows="5"><?php
                echo esc_textarea($instructions['products']);
            ?></textarea>
            <p class="description">Edit product-related system instructions for your responses assistant only if necessary.</p>
        </td>
    </tr>
    <?php 
    // Only show Orders System Instructions if Orders Auto-Sync is enabled
    if ($orders_auto_sync !== 'disabled'): 
    ?>
    <tr valign="top">
        <th scope="row">Orders System Instructions</th>
        <td>
            <textarea name="responses_orders_system_instructions" id="responses_orders_system_instructions" class="large-text" rows="5"><?php 
                echo esc_textarea($instructions['orders']); 
            ?></textarea>
            <p class="description">Edit order-related system instructions for your responses assistant only if necessary.</p>
        </td>
    </tr>
    <?php endif;
}
add_action('wpiko_chatbot_responses_advanced_system_instructions', 'wpiko_chatbot_pro_add_responses_woocommerce_system_instructions');

/**
 * Add WooCommerce integration state to frontend JavaScript
 */
function wpiko_chatbot_pro_add_woocommerce_script_data() {
    // Only add if WooCommerce is active and license is valid
    if (!wpiko_chatbot_is_woocommerce_active() || !wpiko_chatbot_is_license_active()) {
        return;
    }
    
    // Add WooCommerce state to the existing wpikoChatbot JavaScript object
    $woo_js = 'if (typeof wpikoChatbot !== "undefined") { ';
    $woo_js .= 'wpikoChatbot.is_woocommerce_active = ' . (wpiko_chatbot_is_woocommerce_active() ? 'true' : 'false') . '; ';
    $woo_js .= '}';
    
    wp_add_inline_script('wpiko-chatbot-js', $woo_js);
}
add_action('wp_enqueue_scripts', 'wpiko_chatbot_pro_add_woocommerce_script_data', 30);

/**
 * Add Pro Plugin Configuration Status Items to Dashboard
 */
function wpiko_chatbot_pro_add_dashboard_config_status() {
    // Check if pro plugin functions are available
    if (!function_exists('wpiko_chatbot_pro_is_license_active')) {
        return;
    }
    
    // Pro Plugin License Status
    $license_active = wpiko_chatbot_pro_is_license_active();
    $license_status = wpiko_chatbot_pro_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
    
    if ($license_status === 'expired') {
        $license_class = 'config-incomplete';
        $license_action = 'Renew';
    } elseif ($license_active) {
        $license_class = 'config-complete';
        $license_action = 'Manage';
    } else {
        $license_class = 'config-optional';
        $license_action = 'Activate';
    }
    ?>
    
    <!-- Pro License Status -->
    <div class="config-item <?php echo esc_attr($license_class); ?>">
        <span class="config-indicator"></span>
        <span class="config-label">Pro License</span>
        <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=license_activation', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action"><?php echo esc_html($license_action); ?></a>
    </div>
    
    <?php
    // Only show other pro features if license is active
    if ($license_active) {
        // Auto Delete Conversations Status
        $auto_delete_enabled = get_option('wpiko_chatbot_enable_auto_delete', false);
        ?>
        <div class="config-item <?php echo ($auto_delete_enabled) ? 'config-complete' : 'config-optional'; ?>">
            <span class="config-indicator"></span>
            <span class="config-label">Auto Delete Conversations</span>
            <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=conversations', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action"><?php echo ($auto_delete_enabled) ? 'Configure' : 'Enable'; ?></a>
        </div>
        
        <?php
        // Email Capture Status
        $email_capture_enabled = get_option('wpiko_chatbot_enable_email_capture', '0');
        ?>
        <div class="config-item <?php echo ($email_capture_enabled === '1') ? 'config-complete' : 'config-optional'; ?>">
            <span class="config-indicator"></span>
            <span class="config-label">Email Capture</span>
            <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=email_capture', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action"><?php echo ($email_capture_enabled === '1') ? 'Configure' : 'Enable'; ?></a>
        </div>
        
        <?php
        
        // Contact Form Status
        $contact_form_enabled = get_option('wpiko_chatbot_enable_contact_form', '0');
        ?>
        <div class="config-item <?php echo ($contact_form_enabled === '1') ? 'config-complete' : 'config-optional'; ?>">
            <span class="config-indicator"></span>
            <span class="config-label">Contact Form</span>
            <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=contact_form', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action"><?php echo ($contact_form_enabled === '1') ? 'Configure' : 'Enable'; ?></a>
        </div>
        
        <?php
        // Product Card Status (only if WooCommerce is active)
        if (class_exists('WooCommerce')) {
            $product_card_enabled = get_option('wpiko_chatbot_enable_product_cards', '0');
            ?>
            <div class="config-item <?php echo ($product_card_enabled === '1') ? 'config-complete' : 'config-optional'; ?>">
                <span class="config-indicator"></span>
                <span class="config-label">Product Card</span>
                <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=product_card', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action"><?php echo ($product_card_enabled === '1') ? 'Configure' : 'Enable'; ?></a>
            </div>
            <?php
        }
        ?>
    <?php
    }
}
add_action('wpiko_chatbot_dashboard_config_status', 'wpiko_chatbot_pro_add_dashboard_config_status');

