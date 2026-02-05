<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Pro plugin integration for chatbot interface
 */

/**
 * Add contact form functionality to chatbot menu
 */
function wpiko_chatbot_pro_add_contact_form_menu()
{
    // Check if license is active and contact form is enabled
    if (!wpiko_chatbot_is_license_active() || get_option('wpiko_chatbot_enable_contact_form', '0') !== '1') {
        return;
    }

    // Enqueue contact form script on pages where chatbot is displayed
    add_action('wp_enqueue_scripts', 'wpiko_chatbot_pro_enqueue_contact_form_scripts');

    // Add contact form menu item visibility logic
    add_filter('wpiko_chatbot_menu_items', 'wpiko_chatbot_pro_filter_menu_items');
}
add_action('init', 'wpiko_chatbot_pro_add_contact_form_menu');

/**
 * Enqueue contact form scripts
 */
function wpiko_chatbot_pro_enqueue_contact_form_scripts()
{
    $version = apply_filters('wpiko_chatbot_pro_asset_version', defined('WPIKO_CHATBOT_PRO_VERSION') ? WPIKO_CHATBOT_PRO_VERSION : '1.0.0');

    // Enqueue contact form CSS
    wp_enqueue_style(
        'wpiko-chatbot-pro-contact-form-css',
        WPIKO_CHATBOT_PRO_URL . 'admin/css/contact-form.css',
        array(),
        $version
    );

    // Enqueue contact form JavaScript
    wp_enqueue_script(
        'wpiko-chatbot-pro-contact-form-js',
        WPIKO_CHATBOT_PRO_URL . 'js/contact-form.js',
        array('jquery', 'wpiko-chatbot-js'),
        $version,
        true
    );

    // Pass contact form settings to JavaScript by extending the existing wpikoChatbot object
    $contact_form_settings = array(
        'enable_recaptcha' => get_option('wpiko_chatbot_enable_recaptcha', '0'),
        'recaptcha_site_key' => get_option('wpiko_chatbot_recaptcha_site_key', ''),
        'hide_recaptcha_badge' => get_option('wpiko_chatbot_hide_recaptcha_badge', '0'),
        'enable_contact_form' => get_option('wpiko_chatbot_enable_contact_form', '0'),
        'enable_dropdown' => get_option('wpiko_chatbot_contact_form_dropdown', '0'),
        'enable_attachments' => get_option('wpiko_chatbot_contact_form_attachments', '0'),
        'dropdown_options' => get_option('wpiko_chatbot_contact_form_dropdown_options', ''),
    );

    // Add these settings to the existing wpikoChatbot object
    wp_add_inline_script(
        'wpiko-chatbot-pro-contact-form-js',
        'if (typeof wpikoChatbot !== "undefined") {
            wpikoChatbot = Object.assign(wpikoChatbot, ' . wp_json_encode($contact_form_settings) . ');
        }',
        'before'
    );
}

/**
 * Filter menu items to show/hide contact form based on pro settings
 */
function wpiko_chatbot_pro_filter_menu_items($menu_items)
{
    // If license is not active or contact form is disabled, hide the contact form menu item
    if (!wpiko_chatbot_is_license_active() || get_option('wpiko_chatbot_enable_contact_form', '0') !== '1') {
        // Add CSS to hide the contact form menu item
        add_action('wp_head', function () {
            echo '<style>#wpiko-chatbot-contact-form { display: none !important; }</style>';
        });
    }

    return $menu_items;
}

/**
 * Add pro plugin menu items to chatbot
 */
function wpiko_chatbot_pro_add_menu_items()
{
    // Check if license is active
    $license_active = function_exists('wpiko_chatbot_pro_is_license_active') ? wpiko_chatbot_pro_is_license_active() : false;

    if (!$license_active) {
        return;
    }

    // Add contact form menu item if enabled
    $contact_form_enabled = get_option('wpiko_chatbot_enable_contact_form', '0');
    if ($contact_form_enabled === '1') {
        $contact_menu_text = get_option('wpiko_chatbot_contact_menu_text', 'Contact Form');
        echo '<li id="wpiko-chatbot-contact-form"><span class="menu-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></span><span class="menu-text">' . esc_html($contact_menu_text) . '</span></li>';
    }

    // Add email capture change email option if enabled and user is not logged in
    $email_capture_enabled = get_option('wpiko_chatbot_enable_email_capture', '0');
    if (!is_user_logged_in() && $email_capture_enabled === '1') {
        $change_email_text = get_option('wpiko_chatbot_email_capture_change_email_text', 'Change Email');
        echo '<li id="change-email" style="display: none;"><span class="menu-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="menu-text">' . esc_html($change_email_text) . '</span></li>';
    }
}
add_action('wpiko_chatbot_menu_items', 'wpiko_chatbot_pro_add_menu_items');

/**
 * Enqueue contact form scripts when contact form is enabled
 */
function wpiko_chatbot_pro_enqueue_contact_form_assets()
{
    // Check if license is active and contact form is enabled
    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
        return;
    }

    if (get_option('wpiko_chatbot_enable_contact_form', '0') !== '1') {
        return;
    }

    $version = apply_filters('wpiko_chatbot_pro_asset_version', defined('WPIKO_CHATBOT_PRO_VERSION') ? WPIKO_CHATBOT_PRO_VERSION : '1.0.0');

    // Enqueue contact form CSS
    wp_enqueue_style(
        'wpiko-chatbot-pro-contact-form-css',
        WPIKO_CHATBOT_PRO_URL . 'admin/css/contact-form.css',
        array(),
        $version
    );

    // Enqueue contact form JavaScript
    wp_enqueue_script(
        'wpiko-chatbot-pro-contact-form-js',
        WPIKO_CHATBOT_PRO_URL . 'js/contact-form.js',
        array('jquery', 'wpiko-chatbot-js'),
        $version,
        true
    );

    // Add contact form settings to wpikoChatbot object via inline script
    $contact_form_js = 'if (typeof wpikoChatbot !== "undefined") { 
        wpikoChatbot.is_license_active = true;
        wpikoChatbot.enable_contact_form = "1";
        wpikoChatbot.contact_form_ajax_url = "' . admin_url('admin-ajax.php') . '";
        wpikoChatbot.contact_form_nonce = "' . wp_create_nonce('wpiko_chatbot_contact_form') . '";';

    // Also add email capture setting if enabled
    if (get_option('wpiko_chatbot_enable_email_capture', '0') === '1') {
        $contact_form_js .= '
        wpikoChatbot.enable_email_capture = "1";';
    }

    $contact_form_js .= '
    }';

    wp_add_inline_script('wpiko-chatbot-js', $contact_form_js);
}
add_action('wp_enqueue_scripts', 'wpiko_chatbot_pro_enqueue_contact_form_assets', 25);

/**
 * Override email capture setting for pro users
 */
function wpiko_chatbot_pro_enable_email_capture($enable_email_capture)
{
    // Enable email capture if license is active and feature is enabled
    if (wpiko_chatbot_is_license_active() && get_option('wpiko_chatbot_enable_email_capture', false)) {
        return true;
    }
    return $enable_email_capture;
}

/**
 * Enable email capture for pro users
 */
function wpiko_chatbot_pro_enable_email_capture_filter($enable_email_capture)
{
    // Enable email capture if license is active and feature is enabled
    if (function_exists('wpiko_chatbot_pro_is_license_active') && wpiko_chatbot_pro_is_license_active() && get_option('wpiko_chatbot_enable_email_capture', '0') === '1') {
        return true;
    }
    return $enable_email_capture;
}
add_filter('wpiko_chatbot_enable_email_capture', 'wpiko_chatbot_pro_enable_email_capture_filter');

/**
 * Initialize pro plugin chatbot interface integrations
 */
function wpiko_chatbot_pro_init_interface_integration()
{
    // Only run if main plugin is active
    if (!function_exists('wpiko_chatbot_display')) {
        return;
    }

    // Set JavaScript global variable for email capture if license is active and feature is enabled
    if (function_exists('wpiko_chatbot_pro_is_license_active') && wpiko_chatbot_pro_is_license_active() && get_option('wpiko_chatbot_enable_email_capture', '0') === '1') {
        add_action('wp_head', function () {
            echo '<script>window.wpiko_pro_email_capture_enabled = true;</script>';
        }, 1); // High priority to run early
    }
}
add_action('init', 'wpiko_chatbot_pro_init_interface_integration');
