<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Display and handle the contact form settings section.
 */
function wpiko_chatbot_contact_form_section()
{
    // Save settings if POST request
    if (isset($_POST['wpiko_chatbot_save_contact_form'])) {
        check_admin_referer('wpiko_chatbot_contact_form_nonce', 'wpiko_chatbot_contact_form_nonce');

        // Save contact form settings
        $enable_contact_form = isset($_POST['wpiko_chatbot_enable_contact_form']) ? '1' : '0';
        $enable_dropdown = isset($_POST['wpiko_chatbot_contact_form_dropdown']) ? '1' : '0';
        $enable_attachments = isset($_POST['wpiko_chatbot_contact_form_attachments']) ? '1' : '0';
        $dropdown_options = isset($_POST['wpiko_chatbot_contact_form_dropdown_options']) ? sanitize_textarea_field(wp_unslash($_POST['wpiko_chatbot_contact_form_dropdown_options'])) : '';

        // Save custom fields settings
        for ($i = 1; $i <= 2; $i++) {
            $enable_field = isset($_POST["wpiko_chatbot_contact_form_custom_field_{$i}"]) ? '1' : '0';
            $field_label = isset($_POST["wpiko_chatbot_contact_form_custom_field_{$i}_label"]) ? sanitize_text_field(wp_unslash($_POST["wpiko_chatbot_contact_form_custom_field_{$i}_label"])) : '';
            $field_required = isset($_POST["wpiko_chatbot_contact_form_custom_field_{$i}_required"]) ? '1' : '0';
            update_option("wpiko_chatbot_contact_form_custom_field_{$i}", $enable_field);
            update_option("wpiko_chatbot_contact_form_custom_field_{$i}_label", $field_label);
            update_option("wpiko_chatbot_contact_form_custom_field_{$i}_required", $field_required);
        }

        // Save reCAPTCHA settings
        $enable_recaptcha = isset($_POST['wpiko_chatbot_enable_recaptcha']) ? '1' : '0';
        $recaptcha_site_key = isset($_POST['wpiko_chatbot_recaptcha_site_key']) ? sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_recaptcha_site_key'])) : '';
        $recaptcha_secret_key = isset($_POST['wpiko_chatbot_recaptcha_secret_key']) ? sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_recaptcha_secret_key'])) : '';
        $recaptcha_threshold = isset($_POST['wpiko_chatbot_recaptcha_threshold']) ? sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_recaptcha_threshold'])) : '0.5';
        $hide_recaptcha_badge = isset($_POST['wpiko_chatbot_hide_recaptcha_badge']) ? '1' : '0';

        update_option('wpiko_chatbot_enable_contact_form', $enable_contact_form);
        update_option('wpiko_chatbot_contact_form_dropdown', $enable_dropdown);
        update_option('wpiko_chatbot_contact_form_attachments', $enable_attachments);
        update_option('wpiko_chatbot_contact_form_dropdown_options', $dropdown_options);
        update_option('wpiko_chatbot_enable_recaptcha', $enable_recaptcha);
        update_option('wpiko_chatbot_recaptcha_site_key', $recaptcha_site_key);
        update_option('wpiko_chatbot_recaptcha_secret_key', $recaptcha_secret_key);
        update_option('wpiko_chatbot_recaptcha_threshold', $recaptcha_threshold);
        update_option('wpiko_chatbot_hide_recaptcha_badge', $hide_recaptcha_badge);

        // Save customizable text settings
        update_option('wpiko_chatbot_contact_menu_text', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_menu_text'] ?? 'Contact Form')));
        update_option('wpiko_chatbot_contact_form_title', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_form_title'] ?? 'Contact Form')));
        update_option('wpiko_chatbot_contact_form_intro', sanitize_textarea_field(wp_unslash($_POST['wpiko_chatbot_contact_form_intro'] ?? "Please fill out the form below and we'll get back to you as soon as possible.")));
        update_option('wpiko_chatbot_contact_name_label', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_name_label'] ?? 'Name')));
        update_option('wpiko_chatbot_contact_email_label', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_email_label'] ?? 'Email')));
        update_option('wpiko_chatbot_contact_category_label', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_category_label'] ?? 'Category')));
        update_option('wpiko_chatbot_contact_message_label', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_message_label'] ?? 'Message')));
        update_option('wpiko_chatbot_contact_cancel_btn', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_cancel_btn'] ?? 'Cancel')));
        update_option('wpiko_chatbot_contact_send_btn', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_send_btn'] ?? 'Send')));
        update_option('wpiko_chatbot_contact_try_again_btn', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_try_again_btn'] ?? 'Try Again')));
        update_option('wpiko_chatbot_contact_recaptcha_text', wp_kses_post(wp_unslash($_POST['wpiko_chatbot_contact_recaptcha_text'] ?? 'This site is protected by reCAPTCHA.')));
        update_option('wpiko_chatbot_contact_attachment_label', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_attachment_label'] ?? 'Attachments (Max 3MB each)')));
        update_option('wpiko_chatbot_contact_success_message', sanitize_textarea_field(wp_unslash($_POST['wpiko_chatbot_contact_success_message'] ?? 'Contact Form - Your message has been sent successfully. We will get back to you as soon as possible.')));
        update_option('wpiko_chatbot_contact_upload_error', sanitize_textarea_field(wp_unslash($_POST['wpiko_chatbot_contact_upload_error'] ?? 'There was a problem with your file upload. Please ensure it is a valid image (JPG, PNG, GIF) under 3MB.')));
        update_option('wpiko_chatbot_contact_rate_limit_error', sanitize_textarea_field(wp_unslash($_POST['wpiko_chatbot_contact_rate_limit_error'] ?? 'You have submitted too many contact forms. Please try again later.')));
        update_option('wpiko_chatbot_contact_email_failed_error', sanitize_textarea_field(wp_unslash($_POST['wpiko_chatbot_contact_email_failed_error'] ?? 'Contact Form - Failed to send your message. Please try again later or contact us through another method.')));

        // Save AI Integration settings
        $enable_ai_response = isset($_POST['wpiko_chatbot_contact_form_ai_response']) ? '1' : '0';
        update_option('wpiko_chatbot_contact_form_ai_response', $enable_ai_response);
        update_option('wpiko_chatbot_contact_form_ai_trigger', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_contact_form_ai_trigger'] ?? 'support')));
        update_option('wpiko_chatbot_contact_form_ai_custom_trigger', sanitize_textarea_field(wp_unslash($_POST['wpiko_chatbot_contact_form_ai_custom_trigger'] ?? '')));
        $enable_ai_prefill = isset($_POST['wpiko_chatbot_contact_form_ai_prefill']) ? '1' : '0';
        update_option('wpiko_chatbot_contact_form_ai_prefill', $enable_ai_prefill);

        // Show success message
        echo '<div class="notice notice-success is-dismissible"><p>Contact form settings saved successfully!</p></div>';
    }

    // Get current settings
    $enable_contact_form = get_option('wpiko_chatbot_enable_contact_form', '0');
    $enable_dropdown = get_option('wpiko_chatbot_contact_form_dropdown', '0');
    $enable_attachments = get_option('wpiko_chatbot_contact_form_attachments', '0');
    $dropdown_options = get_option('wpiko_chatbot_contact_form_dropdown_options', '');

    // Get custom fields settings
    $custom_fields = array();
    for ($i = 1; $i <= 2; $i++) {
        $custom_fields[$i] = array(
            'enabled' => get_option("wpiko_chatbot_contact_form_custom_field_{$i}", '0'),
            'label' => get_option("wpiko_chatbot_contact_form_custom_field_{$i}_label", ''),
            'required' => get_option("wpiko_chatbot_contact_form_custom_field_{$i}_required", '0'),
        );
    }
    $enable_recaptcha = get_option('wpiko_chatbot_enable_recaptcha', '0');
    $recaptcha_site_key = get_option('wpiko_chatbot_recaptcha_site_key', '');
    $recaptcha_secret_key = get_option('wpiko_chatbot_recaptcha_secret_key', '');
    $recaptcha_threshold = get_option('wpiko_chatbot_recaptcha_threshold', '0.5');
    $hide_recaptcha_badge = get_option('wpiko_chatbot_hide_recaptcha_badge', '0');

    // Get AI Integration settings
    $enable_ai_response = get_option('wpiko_chatbot_contact_form_ai_response', '0');
    $ai_trigger = get_option('wpiko_chatbot_contact_form_ai_trigger', 'support');
    $ai_custom_trigger = get_option('wpiko_chatbot_contact_form_ai_custom_trigger', '');
    $enable_ai_prefill = get_option('wpiko_chatbot_contact_form_ai_prefill', '1');

    // Get customizable text settings
    $contact_menu_text = get_option('wpiko_chatbot_contact_menu_text', 'Contact Form');
    $contact_title = get_option('wpiko_chatbot_contact_form_title', 'Contact Form');
    $contact_intro = get_option('wpiko_chatbot_contact_form_intro', "Please fill out the form below and we'll get back to you as soon as possible.");
    $contact_name_label = get_option('wpiko_chatbot_contact_name_label', 'Name');
    $contact_email_label = get_option('wpiko_chatbot_contact_email_label', 'Email');
    $contact_category_label = get_option('wpiko_chatbot_contact_category_label', 'Category');
    $contact_message_label = get_option('wpiko_chatbot_contact_message_label', 'Message');
    $contact_cancel_btn = get_option('wpiko_chatbot_contact_cancel_btn', 'Cancel');
    $contact_send_btn = get_option('wpiko_chatbot_contact_send_btn', 'Send');
    $contact_try_again_btn = get_option('wpiko_chatbot_contact_try_again_btn', 'Try Again');
    $contact_recaptcha_text = get_option('wpiko_chatbot_contact_recaptcha_text', 'This site is protected by reCAPTCHA.');
    $contact_attachment_label = get_option('wpiko_chatbot_contact_attachment_label', 'Attachments (Max 3MB each)');
    $contact_success_message = get_option('wpiko_chatbot_contact_success_message', 'Contact Form - Your message has been sent successfully. We will get back to you as soon as possible.');
    $contact_upload_error = get_option('wpiko_chatbot_contact_upload_error', 'There was a problem with your file upload. Please ensure it is a valid image (JPG, PNG, GIF) under 3MB.');
    $contact_rate_limit_error = get_option('wpiko_chatbot_contact_rate_limit_error', 'You have submitted too many contact forms. Please try again later.');
    $contact_email_failed_error = get_option('wpiko_chatbot_contact_email_failed_error', 'Contact Form - Failed to send your message. Please try again later or contact us through another method.');

    // Display the form
    ?>
    <div class="chatbot-contact-form-section">
        <div class="contact-form-section-header">
            <h2>
                <span class="dashicons dashicons-email"></span>
                Contact Form Settings
                <?php if (!wpiko_chatbot_is_license_active()): ?>
                    <span class="premium-feature-badge">Premium</span>
                <?php endif; ?>
            </h2>
            <p class="description">Configure the contact form functionality for the chatbot.</p>
        </div>
        <div class="contact-form-section-content">
            <?php
            // Get current license status
            $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
            $is_license_expired = $license_status === 'expired';

            if (wpiko_chatbot_is_license_active()): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('wpiko_chatbot_contact_form_nonce', 'wpiko_chatbot_contact_form_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Contact Form</th>
                            <td>
                                <label class="wpiko-switch">
                                    <input type="checkbox" id="wpiko_chatbot_enable_contact_form"
                                        name="wpiko_chatbot_enable_contact_form" value="1" <?php checked('1', $enable_contact_form); ?>>
                                    <span class="wpiko-slider round"></span>
                                </label>
                                <label for="wpiko_chatbot_enable_contact_form">Enable the contact form feature in the
                                    chatbot</label>
                                <p class="description">When enabled, a contact form option will appear in the chatbot menu.</p>
                            </td>
                        </tr>
                    </table>

                    <div class="contact-form-settings-collapsible <?php echo $enable_contact_form ? 'active' : ''; ?>">

                        <!-- 1. General Settings Accordion -->
                        <div class="wpiko-accordion-item">
                            <h3 class="collapsible-header">
                                <span><span class="dashicons dashicons-admin-settings"></span> General Settings</span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </h3>
                            <div class="collapsible-content">
                                <table class="form-table">
                                    <!-- Custom Fields -->
                                    <?php for ($i = 1; $i <= 2; $i++): ?>
                                        <tr>
                                            <th scope="row">Custom Field
                                                <?php echo esc_html($i); ?>
                                            </th>
                                            <td>
                                                <label class="wpiko-switch">
                                                    <input type="checkbox"
                                                        id="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>"
                                                        name="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>"
                                                        value="1" <?php checked('1', $custom_fields[$i]['enabled']); ?>>
                                                    <span class="wpiko-slider round"></span>
                                                </label>
                                                <label
                                                    for="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>">Enable
                                                    Custom
                                                    Field
                                                    <?php echo esc_html($i); ?>
                                                </label>
                                                <p class="description">When enabled, a custom text field will appear in the contact
                                                    form.</p>
                                                <div style="margin-top: 10px;">
                                                    <input type="text"
                                                        id="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_label"
                                                        name="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_label"
                                                        value="<?php echo esc_attr($custom_fields[$i]['label']); ?>"
                                                        style="width: 100%;"
                                                        placeholder="Enter field label (e.g. Phone Number, Company Name)">
                                                    <p class="description">This label will be used as the placeholder text for the
                                                        field.</p>
                                                </div>
                                                <div style="margin-top: 8px;">
                                                    <label class="wpiko-switch">
                                                        <input type="checkbox"
                                                            id="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_required"
                                                            name="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_required"
                                                            value="1" <?php checked('1', $custom_fields[$i]['required']); ?>>
                                                        <span class="wpiko-slider round"></span>
                                                    </label>
                                                    <label
                                                        for="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_required">Required
                                                        field</label>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                    <tr class="enable-form-dropdown-row">
                                        <th scope="row">Dropdown</th>
                                        <td>
                                            <label class="wpiko-switch">
                                                <input type="checkbox" id="wpiko_chatbot_contact_form_dropdown"
                                                    name="wpiko_chatbot_contact_form_dropdown" value="1" <?php checked('1', $enable_dropdown); ?>>
                                                <span class="wpiko-slider round"></span>
                                            </label>
                                            <label for="wpiko_chatbot_contact_form_dropdown">Enable dropdown menu in contact
                                                form</label>
                                            <p class="description">When enabled, a dropdown menu will appear in the contact
                                                form.</p>
                                            <div style="margin-top: 10px;">
                                                <textarea id="wpiko_chatbot_contact_form_dropdown_options"
                                                    name="wpiko_chatbot_contact_form_dropdown_options" rows="5"
                                                    style="width: 100%;"
                                                    placeholder="Enter each option on a new line"><?php echo esc_textarea($dropdown_options); ?></textarea>
                                                <p class="description">Enter each dropdown option on a new line. These will
                                                    appear
                                                    in the contact form dropdown menu when enabled.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">File Attachments</th>
                                        <td>
                                            <label class="wpiko-switch">
                                                <input type="checkbox" id="wpiko_chatbot_contact_form_attachments"
                                                    name="wpiko_chatbot_contact_form_attachments" value="1" <?php checked('1', $enable_attachments); ?>>
                                                <span class="wpiko-slider round"></span>
                                            </label>
                                            <label for="wpiko_chatbot_contact_form_attachments">Allow file attachments in
                                                contact form</label>
                                            <p class="description">When enabled, users can attach images to their messages.</p>
                                            <p class="description">Only image files (jpg, jpeg, png, gif) are allowed. Maximum
                                                file size per image: 3MB.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- 2. AI Integration Accordion -->
                        <div class="wpiko-accordion-item">
                            <h3 class="collapsible-header">
                                <span><span class="dashicons dashicons-format-chat"></span> AI Integration</span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </h3>
                            <div class="collapsible-content">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Enable AI Contact Form Response</th>
                                        <td>
                                            <label class="wpiko-switch">
                                                <input type="checkbox" id="wpiko_chatbot_contact_form_ai_response"
                                                    name="wpiko_chatbot_contact_form_ai_response" value="1" <?php checked('1', $enable_ai_response); ?>>
                                                <span class="wpiko-slider round"></span>
                                            </label>
                                            <label for="wpiko_chatbot_contact_form_ai_response">Allow the AI to automatically
                                                offer the contact form</label>
                                            <p class="description">When enabled, the AI will automatically suggest the contact
                                                form when users need human assistance. No manual assistant instructions needed.
                                            </p>
                                        </td>
                                    </tr>
                                    <tr class="ai-trigger-row"
                                        style="<?php echo $enable_ai_response === '1' ? '' : 'display:none;'; ?>">
                                        <th scope="row">AI Trigger Behavior</th>
                                        <td>
                                            <select id="wpiko_chatbot_contact_form_ai_trigger"
                                                name="wpiko_chatbot_contact_form_ai_trigger" style="width: 100%;">
                                                <option value="support" <?php selected('support', $ai_trigger); ?>>When user
                                                    needs human support (Recommended)</option>
                                                <option value="explicit" <?php selected('explicit', $ai_trigger); ?>>Only when
                                                    user explicitly asks to contact</option>
                                                <option value="custom" <?php selected('custom', $ai_trigger); ?>>Custom trigger
                                                    conditions</option>
                                            </select>
                                            <p class="description"><strong>When user needs human support:</strong> The AI offers
                                                the form for problems, complaints, questions needing follow-up, or when it can't
                                                help directly.</p>
                                            <p class="description"><strong>Only when user explicitly asks:</strong> The AI only
                                                shows the form when the user clearly asks to contact support or send a message.
                                            </p>
                                            <p class="description"><strong>Custom:</strong> Define your own conditions below.
                                            </p>
                                        </td>
                                    </tr>
                                    <tr class="ai-custom-trigger-row"
                                        style="<?php echo ($enable_ai_response === '1' && $ai_trigger === 'custom') ? '' : 'display:none;'; ?>">
                                        <th scope="row">Custom Trigger Instructions</th>
                                        <td>
                                            <textarea id="wpiko_chatbot_contact_form_ai_custom_trigger"
                                                name="wpiko_chatbot_contact_form_ai_custom_trigger" rows="4"
                                                style="width: 100%;"
                                                placeholder="e.g. Offer the contact form when the user asks about pricing, has a billing issue, or requests a feature."><?php echo esc_textarea($ai_custom_trigger); ?></textarea>
                                            <p class="description">Describe when the AI should offer the contact form. Be
                                                specific about the types of queries that should trigger it.</p>
                                        </td>
                                    </tr>
                                    <tr class="ai-prefill-row"
                                        style="<?php echo $enable_ai_response === '1' ? '' : 'display:none;'; ?>">
                                        <th scope="row">Enable Form Pre-fill</th>
                                        <td>
                                            <label class="wpiko-switch">
                                                <input type="checkbox" id="wpiko_chatbot_contact_form_ai_prefill"
                                                    name="wpiko_chatbot_contact_form_ai_prefill" value="1" <?php checked('1', $enable_ai_prefill); ?>>
                                                <span class="wpiko-slider round"></span>
                                            </label>
                                            <label for="wpiko_chatbot_contact_form_ai_prefill">Allow AI to pre-fill form
                                                fields</label>
                                            <p class="description">When enabled, the AI will summarize the user's inquiry and
                                                pre-fill the message field (and category if available). Users can review and
                                                edit before sending.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- 3. Google reCAPTCHA Settings Accordion -->
                        <div class="wpiko-accordion-item">
                            <h3 class="collapsible-header">
                                <span><span class="dashicons dashicons-shield"></span> Google reCAPTCHA</span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </h3>
                            <div class="collapsible-content">
                                <table class="form-table">
                                    <tr class="enable-google-recaptcha-row">
                                        <th scope="row">Enable Google reCAPTCHA</th>
                                        <td>
                                            <label class="wpiko-switch">
                                                <input type="checkbox" id="wpiko_chatbot_enable_recaptcha"
                                                    name="wpiko_chatbot_enable_recaptcha" value="1" <?php checked('1', $enable_recaptcha); ?>>
                                                <span class="wpiko-slider round"></span>
                                            </label>
                                            <label for="wpiko_chatbot_enable_recaptcha">Enable Google reCAPTCHA for contact
                                                form</label>
                                            <p class="description">When enabled, Google reCAPTCHA will be used to prevent spam
                                                submissions.</p>
                                            <p class="description"><strong>Note:</strong> You must use reCAPTCHA v3 for this
                                                integration to work properly. <a href="https://www.google.com/recaptcha/admin"
                                                    target="_blank">Create your reCAPTCHA keys here</a>.</p>
                                        </td>
                                    </tr>
                                    <tr class="recaptcha-site-key-row">
                                        <th scope="row">reCAPTCHA Site Key</th>
                                        <td>
                                            <input type="text" id="wpiko_chatbot_recaptcha_site_key"
                                                name="wpiko_chatbot_recaptcha_site_key"
                                                value="<?php echo esc_attr($recaptcha_site_key); ?>" style="width: 100%;"
                                                placeholder="Enter your reCAPTCHA site key">
                                            <p class="description">Enter the site key provided by Google reCAPTCHA.</p>
                                        </td>
                                    </tr>
                                    <tr class="recaptcha-secret-key-row">
                                        <th scope="row">reCAPTCHA Secret Key</th>
                                        <td>
                                            <input type="text" id="wpiko_chatbot_recaptcha_secret_key"
                                                name="wpiko_chatbot_recaptcha_secret_key"
                                                value="<?php echo esc_attr($recaptcha_secret_key); ?>" style="width: 100%;"
                                                placeholder="Enter your reCAPTCHA secret key">
                                            <p class="description">Enter the secret key provided by Google reCAPTCHA.</p>
                                        </td>
                                    </tr>
                                    <tr class="recaptcha-threshold-row">
                                        <th scope="row">reCAPTCHA Score Threshold</th>
                                        <td>
                                            <input type="number" id="wpiko_chatbot_recaptcha_threshold"
                                                name="wpiko_chatbot_recaptcha_threshold"
                                                value="<?php echo esc_attr($recaptcha_threshold); ?>" min="0" max="1" step="0.1"
                                                style="width: 100px;">
                                            <p class="description">
                                                Set the minimum score required to accept submissions (0.0 - 1.0).
                                                <strong>Default: 0.5 (Recommended)</strong>
                                            </p>
                                            <div class="score-guide">
                                                <strong style="display: block; margin-bottom: 10px; color: #0968FE;">Score
                                                    Guide:</strong>
                                                <div class="score-guide-item">
                                                    <span class="score-guide-range">0.9-1.0</span>
                                                    <span class="score-guide-description">Very strict - may block some
                                                        legitimate users</span>
                                                </div>
                                                <div class="score-guide-item">
                                                    <span class="score-guide-range">0.7-0.8</span>
                                                    <span class="score-guide-description">Strict - good balance for
                                                        high-security needs</span>
                                                </div>
                                                <div class="score-guide-item">
                                                    <span class="score-guide-range">0.5</span>
                                                    <span class="score-guide-description">Balanced - recommended for most
                                                        sites</span>
                                                </div>
                                                <div class="score-guide-item">
                                                    <span class="score-guide-range">0.3-0.4</span>
                                                    <span class="score-guide-description">Lenient - more spam may pass
                                                        through</span>
                                                </div>
                                                <div class="score-guide-item">
                                                    <span class="score-guide-range">0.0-0.2</span>
                                                    <span class="score-guide-description">Very lenient - not recommended</span>
                                                </div>
                                                <div class="score-guide-note">
                                                    <strong>Note:</strong> reCAPTCHA v3 assigns a score to each submission.
                                                    Lower scores indicate bot-like behavior.
                                                    If you're getting spam, increase this value. If legitimate users are
                                                    blocked, decrease it slightly.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Hide reCAPTCHA Badge</th>
                                        <td>
                                            <label class="wpiko-switch">
                                                <input type="checkbox" id="wpiko_chatbot_hide_recaptcha_badge"
                                                    name="wpiko_chatbot_hide_recaptcha_badge" value="1" <?php checked('1', $hide_recaptcha_badge); ?>>
                                                <span class="wpiko-slider round"></span>
                                            </label>
                                            <label for="wpiko_chatbot_hide_recaptcha_badge">Hide the reCAPTCHA badge</label>
                                            <p class="description">When enabled, the reCAPTCHA badge will be hidden.</p>
                                            <p class="description"><strong>Note:</strong> When the badge is hidden, a reCAPTCHA
                                                notice text is displayed on the contact form instead.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- 4. Customizable Text Accordion -->
                        <div class="wpiko-accordion-item">
                            <h3 class="collapsible-header">
                                <span><span class="dashicons dashicons-edit"></span> Customizable Text</span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </h3>
                            <div class="collapsible-content">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Menu Item Text</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_menu_text"
                                                value="<?php echo esc_attr($contact_menu_text); ?>" style="width: 100%;">
                                            <p class="description">Text displayed in the chatbot menu. Default: <em>Contact
                                                    Form</em></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Form Title</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_form_title"
                                                value="<?php echo esc_attr($contact_title); ?>" style="width: 100%;">
                                            <p class="description">The heading displayed at the top of the contact form.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Intro Text</th>
                                        <td>
                                            <textarea name="wpiko_chatbot_contact_form_intro" rows="3"
                                                style="width: 100%;"><?php echo esc_textarea($contact_intro); ?></textarea>
                                            <p class="description">The introductory message shown below the form title.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Name Field Label</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_name_label"
                                                value="<?php echo esc_attr($contact_name_label); ?>" style="width: 100%;">
                                            <p class="description">Placeholder text for the name input field.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Email Field Label</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_email_label"
                                                value="<?php echo esc_attr($contact_email_label); ?>" style="width: 100%;">
                                            <p class="description">Placeholder text for the email input field.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Category Dropdown Label</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_category_label"
                                                value="<?php echo esc_attr($contact_category_label); ?>" style="width: 100%;">
                                            <p class="description">Default option text for the category dropdown menu.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Message Field Label</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_message_label"
                                                value="<?php echo esc_attr($contact_message_label); ?>" style="width: 100%;">
                                            <p class="description">Placeholder text for the message textarea.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Cancel Button Text</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_cancel_btn"
                                                value="<?php echo esc_attr($contact_cancel_btn); ?>" style="width: 100%;">
                                            <p class="description">Text for the button that closes the contact form.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Send Button Text</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_send_btn"
                                                value="<?php echo esc_attr($contact_send_btn); ?>" style="width: 100%;">
                                            <p class="description">Text for the button that submits the contact form.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Try Again Button Text</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_try_again_btn"
                                                value="<?php echo esc_attr($contact_try_again_btn); ?>" style="width: 100%;">
                                            <p class="description">Shown when an error occurs during form submission.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">reCAPTCHA Text</th>
                                        <td>
                                            <textarea name="wpiko_chatbot_contact_recaptcha_text" rows="2"
                                                style="width: 100%;"><?php echo esc_textarea($contact_recaptcha_text); ?></textarea>
                                            <p class="description">This text is displayed on the contact form only when the
                                                reCAPTCHA badge is hidden. Default: <em>This site is protected by
                                                    reCAPTCHA.</em></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Attachments Label</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_attachment_label"
                                                value="<?php echo esc_attr($contact_attachment_label); ?>" style="width: 100%;">
                                            <p class="description">Label shown above the file attachment slots.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Success Message</th>
                                        <td>
                                            <textarea name="wpiko_chatbot_contact_success_message" rows="3"
                                                style="width: 100%;"><?php echo esc_textarea($contact_success_message); ?></textarea>
                                            <p class="description">Default: <em>Contact Form - Your message has been sent
                                                    successfully. We will get back to you as soon as possible.</em></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Email Send Failed</th>
                                        <td>
                                            <textarea name="wpiko_chatbot_contact_email_failed_error" rows="2"
                                                style="width: 100%;"><?php echo esc_textarea($contact_email_failed_error); ?></textarea>
                                            <p class="description">Default: <em>Contact Form - Failed to send your message.
                                                    Please try again later or contact us through another method.</em></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rate Limit Error</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_rate_limit_error"
                                                value="<?php echo esc_attr($contact_rate_limit_error); ?>" style="width: 100%;">
                                            <p class="description">Default: <em>You have submitted too many contact forms.
                                                    Please try again later.</em></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">File Upload Error</th>
                                        <td>
                                            <textarea name="wpiko_chatbot_contact_upload_error" rows="3"
                                                style="width: 100%;"><?php echo esc_textarea($contact_upload_error); ?></textarea>
                                            <p class="description">Default: <em>There was a problem with your file upload.
                                                    Please ensure it is a valid image (JPG, PNG, GIF) under 3MB.</em></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- 5. Usage & Integration -->
                        <div class="wpiko-accordion-item">
                            <h3 class="collapsible-header">
                                <span><span class="dashicons dashicons-editor-help"></span> Integration & Instructions</span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </h3>
                            <div class="collapsible-content">
                                <div class="instruction-content">
                                    <h4>AI Automatic Integration</h4>
                                    <p>When <strong>AI Contact Form Response</strong> is enabled (in the AI Integration section
                                        above), the AI will automatically offer the contact form when appropriate. <strong>No
                                            manual instructions are needed.</strong></p>
                                    <p>The AI will also pre-fill the form with a summary of the user's inquiry (if Form Pre-fill
                                        is enabled).</p>

                                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

                                    <h4>Manual Methods (Alternative)</h4>
                                    <p>If you prefer manual control, you can still use the following methods:</p>

                                    <h5>Method 1: Chatbot provides a Contact Form link that instantly opens the Contact Form
                                    </h5>
                                    <p><strong>Go to:</strong> WPiko Chatbot → AI Configuration → Edit Assistant → Specific
                                        System Instructions:</p>
                                    <pre>For support inquiries, provide only:  Wpiko Form</pre>

                                    <h5>Method 2: Link redirects users to page and launches form</h5>
                                    <pre>For support inquiries, direct users to https://example.com/page/?wpiko_contact=open</pre>

                                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

                                    <h4>Custom Contact Link (Menu / Buttons)</h4>
                                    <p>You can add a link anywhere on your website to open the chatbot with the contact form
                                        directly.</p>

                                    <h5>Method 1: Add a Custom Link to Menus</h5>
                                    <p>Go to <strong>Appearance → Menus → Custom Links</strong> and set URL to
                                        <code>?wpiko_contact=open</code> with Label <code>Contact Us</code>.
                                    </p>

                                    <h5>Method 2: Use a Website URL</h5>
                                    <p>Simply add <code>?wpiko_contact=open</code> to any URL on your website.</p>
                                    <pre>https://example.com/page/?wpiko_contact=open</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="submit">
                        <input type="submit" name="wpiko_chatbot_save_contact_form" class="button-primary" value="Save Changes">
                    </p>
                </form>

                <script>
                    jQuery(document).ready(function ($) {
                        // Toggle contact form settings visibility based on checkbox state
                        $('#wpiko_chatbot_enable_contact_form').change(function () {
                            if ($(this).is(':checked')) {
                                $('.contact-form-settings-collapsible').addClass('active');
                            } else {
                                $('.contact-form-settings-collapsible').removeClass('active');
                            }
                        });

                        // Standard accordion toggle
                        $('.collapsible-header').click(function () {
                            // Toggle active class on header for arrow rotation
                            $(this).toggleClass('active');
                            // Toggle active class on next sibling content
                            $(this).next('.collapsible-content').toggleClass('active');
                        });

                        // Toggle AI Integration sub-settings visibility
                        $('#wpiko_chatbot_contact_form_ai_response').change(function () {
                            if ($(this).is(':checked')) {
                                $('.ai-trigger-row, .ai-prefill-row').show();
                                // Show custom trigger row if custom is selected
                                if ($('#wpiko_chatbot_contact_form_ai_trigger').val() === 'custom') {
                                    $('.ai-custom-trigger-row').show();
                                }
                            } else {
                                $('.ai-trigger-row, .ai-custom-trigger-row, .ai-prefill-row').hide();
                            }
                        });

                        // Toggle custom trigger textarea based on dropdown selection
                        $('#wpiko_chatbot_contact_form_ai_trigger').change(function () {
                            if ($(this).val() === 'custom') {
                                $('.ai-custom-trigger-row').show();
                            } else {
                                $('.ai-custom-trigger-row').hide();
                            }
                        });
                    });
                </script>
            <?php elseif ($is_license_expired): ?>
                <div class="premium-feature-notice">
                    <h3>🔒 Contact Form Disabled</h3>
                    <p>Your license has expired. Contact form feature has been disabled.</p>
                    <p>Renew your license to regain access to these features:</p>
                    <ul>
                        <li>✨ Enable contact form in chatbot menu</li>
                        <li>📎 Allow file attachments</li>
                        <li>📝 Customizable dropdown options</li>
                        <li>🛡️ reCAPTCHA integration</li>
                        <li>📧 Direct email communication</li>
                    </ul>
                    <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Renew License</a>
                </div>
            <?php else: ?>
                <div class="premium-feature-notice">
                    <h3>📨 Unlock Contact Form</h3>
                    <p>Upgrade to Premium to enhance your chatbot with integrated contact form:</p>
                    <ul>
                        <li>✨ Enable contact form in chatbot menu</li>
                        <li>📎 Allow file attachments</li>
                        <li>📝 Customizable dropdown options</li>
                        <li>🛡️ reCAPTCHA integration</li>
                        <li>📧 Direct email communication</li>
                    </ul>
                    <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Upgrade to Premium</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>