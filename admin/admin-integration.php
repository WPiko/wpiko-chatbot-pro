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
 * Add tabs to the base plugin's tab list.
 *
 * Each Pro screen sits in the core group it belongs to and carries a PRO
 * badge. License Activation is a small link at the bottom of the sidebar.
 */
function wpiko_chatbot_pro_add_tab($tabs) {

    // Add Analytics tab
    $tabs['analytics'] = array(
        'label' => 'Analytics',
        'icon' => 'dashicons-chart-bar',
        'group' => 'monitor',
        'order' => 20,
        'pro' => true,
    );

    // Add Mobile App (PWA) tab
    $tabs['pwa_settings'] = array(
        'label' => 'Mobile App',
        'icon' => 'dashicons-smartphone',
        'group' => 'monitor',
        'order' => 30,
        'pro' => true,
    );

    // Add Email Capture tab
    $tabs['email_capture'] = array(
        'label' => 'Email Capture',
        'icon' => 'dashicons-email',
        'group' => 'leads_sales',
        'order' => 10,
        'pro' => true,
    );

    // Add Contact Form tab
    $tabs['contact_form'] = array(
        'label' => 'Contact Form',
        'icon' => 'dashicons-email-alt',
        'group' => 'leads_sales',
        'order' => 20,
        'pro' => true,
    );

    // Add Product Card tabs only if WooCommerce is active
    if (class_exists('WooCommerce')) {
        $tabs['product_card'] = array(
            'label' => 'Product Card',
            'icon' => 'dashicons-align-full-width',
            'group' => 'leads_sales',
            'order' => 30,
            'pro' => true,
        );
    }

    // Add License Activation tab (shown as a sidebar footer link)
    $tabs['license_activation'] = array(
        'label' => 'License Activation',
        'nav_label' => 'License',
        'icon' => 'dashicons-unlock',
        'group' => 'more',
        'order' => 90,
        'nav' => 'footer',
    );

    return $tabs;
}
add_filter('wpiko_chatbot_admin_tabs', 'wpiko_chatbot_pro_add_tab');

/**
 * Make sure the groups Pro screens use exist on older core versions.
 */
function wpiko_chatbot_pro_add_tab_group($groups) {
    if (!isset($groups['monitor'])) {
        $groups['monitor'] = array('label' => 'Monitor', 'order' => 15);
    }
    if (!isset($groups['leads_sales'])) {
        $groups['leads_sales'] = array('label' => 'Leads & Sales', 'order' => 40);
    }

    return $groups;
}
add_filter('wpiko_chatbot_admin_tab_groups', 'wpiko_chatbot_pro_add_tab_group');

/**
 * Add Pro sections to the WordPress admin menu
 */
function wpiko_chatbot_pro_admin_menu() {
    // Current core versions build all submenus from the shared tab registry.
    // Retain the code below for compatibility with older core versions.
    if (function_exists('wpiko_chatbot_get_admin_tabs')) {
        return;
    }

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
    
    // Add Mobile App (PWA) submenu
    add_submenu_page(
        'ai-chatbot', 
        'Mobile App', 
        'Mobile App', 
        'manage_options', 
        'ai-chatbot&tab=pwa_settings', 
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
        array('wpiko-chatbot-settings-controls'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/css/email-capture.css')
    );
    
    // Add Premium feature styles CSS
    wp_enqueue_style(
        'wpiko-chatbot-premium-feature-style-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/premium-feature-style.css', 
        array('wpiko-chatbot-settings-controls'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/css/premium-feature-style.css')
    );
    
    // Add scan website CSS
    wp_enqueue_style(
        'wpiko-chatbot-scan-website-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/scan-website.css', 
        array('wpiko-chatbot-settings-controls'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/css/scan-website.css')
    );
    
    // Add qa management CSS
    wp_enqueue_style(
        'wpiko-chatbot-qa-management-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/qa-management.css', 
        array('wpiko-chatbot-settings-controls'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/css/qa-management.css')
    );
    
    // Add woocommerce integration CSS
    wp_enqueue_style(
        'wpiko-chatbot-woocommerce-integration-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/woocommerce-integration.css', 
        array('wpiko-chatbot-settings-controls'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/css/woocommerce-integration.css')
    );
    
    // Add product card CSS
    wp_enqueue_style(
        'wpiko-chatbot-product-card-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/product-card.css', 
        array('wpiko-chatbot-settings-controls'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/css/product-card.css')
    );
    
    // Add contact form CSS
    wp_enqueue_style(
        'wpiko-chatbot-contact-form-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/contact-form.css', 
        array('wpiko-chatbot-settings-controls'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/css/contact-form.css')
    );
    
    // Add license activation CSS
    wp_enqueue_style(
        'wpiko-chatbot-license-activation-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/license-activation.css', 
        array('wpiko-chatbot-settings-controls'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/css/license-activation.css')
    );
    
    // Add mobile app (PWA) CSS
    wp_enqueue_style(
        'wpiko-chatbot-mobile-app-css', 
        WPIKO_CHATBOT_PRO_URL . 'admin/css/mobile-app.css', 
        array('wpiko-chatbot-settings-controls'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/css/mobile-app.css')
    );
    
    // Enqueue JS files for premium features
    wp_enqueue_script(
        'wpiko-chatbot-license-management',
        WPIKO_CHATBOT_PRO_URL . 'admin/js/license-management.js',
        array('jquery'),
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/js/license-management.js'),
        true
    );
    
    wp_enqueue_script(
        'wpiko-chatbot-scan-website-js', 
        WPIKO_CHATBOT_PRO_URL . 'admin/js/scan-website.js', 
        array('jquery'), 
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/js/scan-website.js'), 
        true
    );
    
    wp_enqueue_script(
        'wpiko-chatbot-qa-management-js', 
        WPIKO_CHATBOT_PRO_URL . 'admin/js/qa-management.js', 
        array('jquery'), 
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/js/qa-management.js'), 
        true
    );
    
    wp_enqueue_script(
        'wpiko-chatbot-woocommerce-integration-js', 
        WPIKO_CHATBOT_PRO_URL . 'admin/js/woocommerce-integration.js', 
        array('jquery'), 
        $version . '.' . filemtime(WPIKO_CHATBOT_PRO_PATH . 'admin/js/woocommerce-integration.js'), 
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
 * Print the Pro screens for the base plugin's admin page.
 *
 * `wpiko_chatbot_admin_tab_content` is a filter: it starts with the active tab
 * ID, and a callback returns true once it has printed that tab. To let other
 * add-ons see the tab ID too, this callback passes the value on unchanged when
 * the tab isn't a Pro screen, and keeps true when another callback already
 * printed the tab. wpiko_chatbot_pro_finish_tab_content() turns the result back
 * into true or false at the end.
 *
 * @param string|bool $active_tab Active tab ID, or true/false from an earlier callback.
 * @return string|bool True when a Pro screen was printed, otherwise the value received.
 */
function wpiko_chatbot_pro_add_tab_content($active_tab) {
    if ($active_tab === true) {
        return true;
    }
    if (!is_string($active_tab) || $active_tab === '') {
        // An older add-on returned false instead of passing the tab ID on.
        // Read it the same way the base plugin does, so Pro screens still load.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects which screen to show; each screen checks its own nonces.
        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        if ($active_tab === '') {
            return false;
        }
    }

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
    if ($active_tab === 'pwa_settings') {
        require_once WPIKO_CHATBOT_PRO_PATH . 'admin/sections/pwa-settings-section.php';
        wpiko_chatbot_pwa_settings_section();
        return true;
    }
    if ($active_tab === 'license_activation') {
        require_once WPIKO_CHATBOT_PRO_PATH . 'admin/sections/license-activation-section.php';
        wpiko_chatbot_license_activation_page();
        return true;
    }
    return $active_tab;
}
add_filter('wpiko_chatbot_admin_tab_content', 'wpiko_chatbot_pro_add_tab_content');

/**
 * Turn the tab content result into true or false for the base plugin.
 *
 * The base plugin shows the Dashboard when no callback printed the tab, but it
 * treats any non-empty value as printed. A tab ID that is still unhandled after
 * every other callback becomes false here, so unknown tabs (for example
 * Product Card while WooCommerce is inactive) still fall back to the Dashboard.
 *
 * @param string|bool $handled Result of the earlier callbacks.
 * @return bool True only when a callback printed the tab.
 */
function wpiko_chatbot_pro_finish_tab_content($handled) {
    return $handled === true;
}
add_filter('wpiko_chatbot_admin_tab_content', 'wpiko_chatbot_pro_finish_tab_content', PHP_INT_MAX);

/**
 * Add user location to conversation details
 */
function wpiko_chatbot_pro_add_user_location() {
    if (wpiko_chatbot_pro_is_license_active()) {
        echo '<div class="user-country"></div>';
    } else {
        echo '<div class="user-country locked">
            <span>User Location</span>
            <span class="premium-feature-badge" title="Activate Pro to unlock user location">PRO</span>
        </div>';
    }
}
add_action('wpiko_chatbot_conversation_user_location', 'wpiko_chatbot_pro_add_user_location');

/**
 * Add contact user button to conversation details
 */
function wpiko_chatbot_pro_add_contact_button() {
    $mail_icon = '<svg class="button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
    $allowed_svg = array(
        'svg'  => array(
            'class'           => true,
            'viewbox'         => true,
            'fill'            => true,
            'stroke'          => true,
            'stroke-width'    => true,
            'stroke-linecap'  => true,
            'stroke-linejoin' => true,
            'aria-hidden'     => true,
        ),
        'rect' => array(
            'x'      => true,
            'y'      => true,
            'width'  => true,
            'height' => true,
            'rx'     => true,
        ),
        'path' => array(
            'd' => true,
        ),
    );
    if (wpiko_chatbot_pro_is_license_active()) {
        echo '<button class="button contact-user" disabled>' . wp_kses($mail_icon, $allowed_svg) . ' Contact User</button>';
    } else {
        echo '<div class="contact-user-locked">
            <div class="button-content">
                ' . wp_kses($mail_icon, $allowed_svg) . '
                <span>Contact User</span>
                <span class="premium-feature-badge" title="Activate Pro to unlock contact user">PRO</span>
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
        <div class="auto-delete-section wpiko-chatbot-settings-card">
            <div class="wpiko-settings-card-header">
                <div class="wpiko-settings-card-heading">
                    <h2 class="wpiko-chatbot-settings-card-title">Automatic cleanup</h2>
                </div>
            </div>
            <form method="post" action="" class="auto-delete-form wpiko-chatbot-settings-form">
                <?php wp_nonce_field('save_auto_delete_settings', 'auto_delete_nonce'); ?>
                <div class="auto-delete-toggle-row">
                    <label class="wpiko-chatbot-settings-switch" for="wpiko-enable-auto-delete">
                        <input type="checkbox" role="switch" name="enable_auto_delete" id="wpiko-enable-auto-delete" aria-describedby="wpiko-auto-delete-help"
                               <?php checked(get_option('wpiko_chatbot_enable_auto_delete', false)); ?>>
                        <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                        <span class="wpiko-chatbot-settings-switch__label">Enable automatic cleanup</span>
                    </label>
                </div>
                <div class="auto-delete-field">
                    <label for="wpiko_auto_delete_days" class="auto-delete-field-label wpiko-chatbot-settings-field-label">Delete conversations older than</label>
                    <div class="wpiko-select-wrap">
                        <select id="wpiko_auto_delete_days" name="auto_delete_days" <?php disabled(!get_option('wpiko_chatbot_enable_auto_delete', false)); ?>>
                            <option value="7" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 7); ?>>7 days</option>
                            <option value="14" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 14); ?>>14 days</option>
                            <option value="30" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 30); ?>>30 days</option>
                            <option value="60" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 60); ?>>60 days</option>
                            <option value="90" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 90); ?>>90 days</option>
                            <option value="365" <?php selected(get_option('wpiko_chatbot_auto_delete_days', 90), 365); ?>>1 year</option>
                        </select>
                    </div>
                </div>
                <input type="submit" class="button auto-delete-save wpiko-chatbot-settings-save" value="Save Settings">
            </form>
            <p class="auto-delete-description" id="wpiko-auto-delete-help">
                <span>Conversations older than the selected period are <strong>permanently deleted</strong>, checked once daily. For example, at 7 days, any conversation older than a week is removed.</span>
            </p>
        </div>
    <?php
    } elseif ($is_license_expired) {
        ?>
        <div class="premium-feature-notice auto-delete-notice">
            <h3>Auto Delete Disabled</h3>
            <p>Your license has expired. Auto-delete feature has been disabled.</p>
            <p>Get a lifetime license to continue using the auto-delete feature:</p>
            <ul>
                <li>Re-enable automatic conversation cleanup</li>
                <li>Restore your previous auto-delete settings</li>
                <li>Resume automated maintenance</li>
            </ul>
            <a href="https://wpiko.com/chatbot-pricing/" target="_blank" rel="noopener" class="button button-primary">Get Lifetime License</a>
        </div>
    <?php
    } else {
        ?>
        <div class="premium-feature-notice auto-delete-notice">
            <h3>Unlock Auto Delete <span class="premium-feature-badge">PRO</span></h3>
            <p>Activate Pro to automatically clean up old conversations:</p>
            <ul>
                <li>Automatically delete old conversations</li>
                <li>Configure deletion timeframe</li>
                <li>Set it and forget it maintenance</li>
                <li>Optimize database storage</li>
                <li>Improve site performance</li>
            </ul>
            <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Activate Pro</a>
        </div>
    <?php
    }
}
add_action('wpiko_chatbot_conversation_auto_delete_settings', 'wpiko_chatbot_pro_add_auto_delete_settings');


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
            <span class="premium-feature-badge">PRO</span>
        <?php endif; ?>
    </button>
    <?php
}
add_action('wpiko_chatbot_responses_scan_website_button', 'wpiko_chatbot_pro_add_scan_website_button');

/**
 * Add Scan Website modal to Responses API Configuration section
 */
function wpiko_chatbot_pro_add_scan_website_modal() {
    wpiko_chatbot_pro_render_training_modal(array(
        'id'           => 'responses-scan-website-modal',
        'container_id' => 'responses-scan-website-container',
        'title'        => __('Scan Website', 'wpiko-chatbot-pro'),
        'description'  => __('Train your chatbot on your pages, posts and docs.', 'wpiko-chatbot-pro'),
        'icon'         => 'dashicons-admin-site-alt3',
        'size'         => 'wide',
    ));
}

/**
 * Print a Train AI popup with the core plugin's shared shell.
 * Falls back to the previous markup when an older core version is active.
 */
function wpiko_chatbot_pro_render_training_modal($args) {
    if (!wpiko_chatbot_pro_is_license_active()) {
        $args['badge'] = 'PRO';
    }
    if (function_exists('wpiko_chatbot_render_modal')) {
        wpiko_chatbot_render_modal($args);
        return;
    }
    ?>
    <div id="<?php echo esc_attr($args['id']); ?>" class="wpiko-modal">
        <div class="wpiko-modal-content">
            <span class="wpiko-modal-close">&times;</span>
            <div id="<?php echo esc_attr($args['container_id']); ?>"></div>
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
            <span class="premium-feature-badge">PRO</span>
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
    wpiko_chatbot_pro_render_training_modal(array(
        'id'           => 'responses-qa-management-modal',
        'container_id' => 'responses-qa-management-container',
        'title'        => __('Q&A Builder', 'wpiko-chatbot-pro'),
        /* translators: %d: maximum number of Q&A pairs. */
        'description'  => sprintf(__('Write exact answers to the questions visitors ask most. Up to %d pairs.', 'wpiko-chatbot-pro'), function_exists('wpiko_chatbot_pro_qa_max_pairs') ? wpiko_chatbot_pro_qa_max_pairs() : 50),
        'icon'         => 'dashicons-insert',
        'size'         => 'wide',
    ));
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
        <span class="dashicons dashicons-cart"></span> WooCommerce Integration
        <?php if (!wpiko_chatbot_pro_is_license_active()): ?>
            <span class="premium-feature-badge">PRO</span>
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
        <span class="dashicons dashicons-cart"></span> WooCommerce Integration
        <?php if (!wpiko_chatbot_pro_is_license_active()): ?>
            <span class="premium-feature-badge">PRO</span>
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
    wpiko_chatbot_pro_render_training_modal(array(
        'id'           => 'responses-woocommerce-integration-modal',
        'container_id' => 'responses-woocommerce-integration-container',
        'title'        => __('WooCommerce Integration', 'wpiko-chatbot-pro'),
        'description'  => __('Sync your product catalog and let the chatbot look up orders.', 'wpiko-chatbot-pro'),
        'icon'         => 'dashicons-cart',
    ));
}
add_action('wpiko_chatbot_responses_woocommerce_integration_modal', 'wpiko_chatbot_pro_add_responses_woocommerce_integration_modal');

/**
 * Function to load Responses Scan Website template content
 */
function wpiko_chatbot_pro_load_responses_scan_website_callback() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to do this.'), 403);
    }
    
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
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to do this.'), 403);
    }
    
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
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to do this.'), 403);
    }
    
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
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to do this.'), 403);
    }
    
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
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to do this.'), 403);
    }
    
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
            console.log('WPiko AI Chatbot Pro: Email capture functionality loaded');
        }
    </script>
    <?php
}

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
add_action('wpiko_chatbot_enqueue_frontend_assets', 'wpiko_chatbot_pro_add_woocommerce_script_data', 30);

/**
 * Pro features on the dashboard's Configuration status (On/Off chips).
 *
 * @param array[] $features Features from the base plugin.
 * @return array[]
 */
function wpiko_chatbot_pro_dashboard_features($features) {
    if (!function_exists('wpiko_chatbot_pro_is_license_active')) {
        return $features;
    }
    $features = is_array($features) ? $features : array();
    $tab = function ($slug) {
        return wp_nonce_url('?page=ai-chatbot&tab=' . $slug, 'wpiko_chatbot_tab_nonce');
    };

    $license_active = wpiko_chatbot_pro_is_license_active();
    $license_status = wpiko_chatbot_pro_decrypt_data(get_option('wpiko_chatbot_license_status', ''));

    $features['pro_license'] = array(
        'label' => __('Pro license', 'wpiko-chatbot-pro'),
        'on' => $license_active,
        'state' => $license_status === 'expired' ? 'warn' : '',
        'state_label' => $license_status === 'expired' ? __('Expired', 'wpiko-chatbot-pro') : ($license_active ? __('Active', 'wpiko-chatbot-pro') : __('Inactive', 'wpiko-chatbot-pro')),
        'url' => $tab('license_activation'),
    );

    $pro_features = array(
        'email_capture' => array(__('Email capture', 'wpiko-chatbot-pro'), get_option('wpiko_chatbot_enable_email_capture', '0') === '1', 'email_capture'),
        'contact_form' => array(__('Contact form', 'wpiko-chatbot-pro'), get_option('wpiko_chatbot_enable_contact_form', '0') === '1', 'contact_form'),
        'mobile_app' => array(__('Mobile app', 'wpiko-chatbot-pro'), get_option('wpiko_chatbot_enable_pwa', '0') === '1', 'pwa_settings'),
        'auto_delete' => array(__('Automatic cleanup', 'wpiko-chatbot-pro'), (bool) get_option('wpiko_chatbot_enable_auto_delete', false), 'conversations'),
    );
    if (class_exists('WooCommerce')) {
        $pro_features['product_card'] = array(__('Product cards', 'wpiko-chatbot-pro'), get_option('wpiko_chatbot_enable_product_cards', '0') === '1', 'product_card');
    }

    foreach ($pro_features as $key => $item) {
        $features[$key] = array(
            'label' => $item[0],
            'on' => $license_active && $item[1],
            'url' => $tab($item[2]),
            'pro' => true,
            'locked' => !$license_active,
        );
    }

    return $features;
}
add_filter('wpiko_chatbot_dashboard_features', 'wpiko_chatbot_pro_dashboard_features');

/**
 * Add actionable Pro issues to the core dashboard.
 *
 * @param array $attention_items Existing dashboard attention items.
 * @return array
 */
function wpiko_chatbot_pro_add_dashboard_attention_items($attention_items) {
    if (!is_array($attention_items)) {
        $attention_items = array();
    }

    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !function_exists('wpiko_chatbot_pro_decrypt_data')) {
        return $attention_items;
    }

    $license_active = wpiko_chatbot_pro_is_license_active();
    $license_status = wpiko_chatbot_pro_decrypt_data(get_option('wpiko_chatbot_license_status', ''));

    if ($license_status === 'expired') {
        $attention_items[] = array(
            'severity' => 'critical',
            'icon' => 'dashicons-unlock',
            'title' => __('Your WPiko AI Chatbot Pro license has expired', 'wpiko-chatbot-pro'),
            'description' => __('Get a lifetime license to restore access to Pro features and updates. If your license was converted to lifetime, open License and click Refresh License.', 'wpiko-chatbot-pro'),
            'url' => wp_nonce_url('?page=ai-chatbot&tab=license_activation', 'wpiko_chatbot_tab_nonce'),
            'action_label' => __('Open License', 'wpiko-chatbot-pro'),
        );
    }

    if (!$license_active || !class_exists('WooCommerce') || !function_exists('wpiko_chatbot_is_woocommerce_integration_enabled') || !wpiko_chatbot_is_woocommerce_integration_enabled()) {
        return $attention_items;
    }

    $integration_url = wp_nonce_url('?page=ai-chatbot&tab=ai_configuration', 'wpiko_chatbot_tab_nonce');

    if (get_option('wpiko_chatbot_sync_status', '') === 'failed') {
        $product_sync_error = trim((string) get_option('wpiko_chatbot_sync_error', ''));

        $attention_items[] = array(
            'severity' => 'warning',
            'icon' => 'dashicons-update',
            'title' => __('WooCommerce product sync failed', 'wpiko-chatbot-pro'),
            'description' => $product_sync_error !== ''
                ? wp_trim_words($product_sync_error, 18, '…')
                : __('Open the WooCommerce integration to review the failure and retry the sync.', 'wpiko-chatbot-pro'),
            'url' => $integration_url,
            'action_label' => __('Open integration', 'wpiko-chatbot-pro'),
        );
    }

    if (get_option('wpiko_chatbot_orders_sync_status', '') === 'failed') {
        $orders_sync_error = trim((string) get_option('wpiko_chatbot_orders_sync_error', ''));

        $attention_items[] = array(
            'severity' => 'warning',
            'icon' => 'dashicons-cart',
            'title' => __('WooCommerce order sync failed', 'wpiko-chatbot-pro'),
            'description' => $orders_sync_error !== ''
                ? wp_trim_words($orders_sync_error, 18, '…')
                : __('Open the WooCommerce integration to review the failure and retry the sync.', 'wpiko-chatbot-pro'),
            'url' => $integration_url,
            'action_label' => __('Open integration', 'wpiko-chatbot-pro'),
        );
    }

    return $attention_items;
}
add_filter('wpiko_chatbot_dashboard_attention_items', 'wpiko_chatbot_pro_add_dashboard_attention_items');

/**
 * Takeover button removed from WP admin conversations page.
 * Admins should use /wpiko-app/ for takeover functionality.
 */
