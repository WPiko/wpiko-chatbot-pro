<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Display and handle the contact form settings section.
 */
function wpiko_chatbot_contact_form_section()
{
    $settings_saved = false;

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
        $settings_saved = true;
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

    $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
    $is_license_expired = $license_status === 'expired';

    ?>
    <div class="wpiko-chatbot-settings-page wpiko-contact-form-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>
                Contact Form<?php if (!wpiko_chatbot_is_license_active()): ?>
                <span class="contact-form-pro-badge">PRO</span>
                <?php endif; ?>
            </h1>
            <p class="wpiko-chatbot-settings-subtitle">Let visitors contact you through the chatbot. Configure form fields, AI assistance, spam protection, and messages.</p>
        </header>
        <?php if ($settings_saved): ?>
        <div class="wpiko-chatbot-settings-notice is-success" role="status">
            <p>Contact form settings saved successfully!</p>
        </div>
        <?php endif; ?>
        <?php if (wpiko_chatbot_is_license_active()): ?>
        <form method="post" action="" class="wpiko-chatbot-settings-form">
            <?php wp_nonce_field('wpiko_chatbot_contact_form_nonce', 'wpiko_chatbot_contact_form_nonce'); ?>
            <section class="wpiko-chatbot-settings-card" aria-labelledby="contact-form-settings-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="contact-form-settings-title">Contact form settings</h2>
                <div class="wpiko-chatbot-settings-field-row">
                    <div class="wpiko-chatbot-settings-field-label">
                        Enable Contact Form
                    </div>
                    <div class="wpiko-chatbot-settings-field-control">
                        <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_enable_contact_form">
                            <input type="checkbox" role="switch" id="wpiko_chatbot_enable_contact_form" aria-controls="contact-form-options" name="wpiko_chatbot_enable_contact_form" value="1" <?php checked('1', $enable_contact_form); ?>>
                            <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true">
                            </span>
                            <span class="wpiko-chatbot-settings-switch__label">Enable the contact form feature in the chatbot</span>
                        </label>
                        <p class="wpiko-chatbot-settings-field-description">When enabled, a contact form option will appear in the chatbot menu.</p>
                    </div>
                </div>
            </section>
            <div class="wpiko-chatbot-settings-content" id="contact-form-options" <?php if (!$enable_contact_form): ?>hidden<?php endif; ?>>
                <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure">
                    <summary class="wpiko-chatbot-settings-disclosure-title">General Settings</summary>
                    <div class="wpiko-chatbot-settings-disclosure-content">
                        <!-- Custom Fields -->
                        <?php for ($i = 1; $i <= 2; $i++): ?>
                        <div class="wpiko-chatbot-settings-field-row">
                            <div class="wpiko-chatbot-settings-field-label">
                                Custom Field <?php echo esc_html($i); ?>
                            </div>
                            <div class="wpiko-chatbot-settings-field-control">
                                <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>">
                                    <input type="checkbox" role="switch" id="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>" name="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>" value="1" <?php checked('1', $custom_fields[$i]['enabled']); ?>>
                                    <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true">
                                    </span>
                                    <span class="wpiko-chatbot-settings-switch__label">Enable Custom Field <?php echo esc_html($i); ?></span>
                                </label>
                                <p class="wpiko-chatbot-settings-field-description">When enabled, a custom text field will appear in the contact form.</p>
                                <div class="contact-form-subfield">
                                    <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_label">Field label</label>
                                    <input type="text" id="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_label" name="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_label" value="<?php echo esc_attr($custom_fields[$i]['label']); ?>" placeholder="Enter field label (e.g. Phone Number, Company Name)">
                                    <p class="wpiko-chatbot-settings-field-description">This label will be used as the placeholder text for the field.</p>
                                </div>
                                <div class="contact-form-subfield">
                                    <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_required">
                                        <input type="checkbox" role="switch" id="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_required" name="wpiko_chatbot_contact_form_custom_field_<?php echo esc_attr($i); ?>_required" value="1" <?php checked('1', $custom_fields[$i]['required']); ?>>
                                        <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true">
                                        </span>
                                        <span class="wpiko-chatbot-settings-switch__label">Required field</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                        <div class="wpiko-chatbot-settings-field-row enable-form-dropdown-row">
                            <div class="wpiko-chatbot-settings-field-label">
                                Dropdown
                            </div>
                            <div class="wpiko-chatbot-settings-field-control">
                                <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_contact_form_dropdown">
                                    <input type="checkbox" role="switch" id="wpiko_chatbot_contact_form_dropdown" name="wpiko_chatbot_contact_form_dropdown" value="1" <?php checked('1', $enable_dropdown); ?>>
                                    <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true">
                                    </span>
                                    <span class="wpiko-chatbot-settings-switch__label">Enable dropdown menu in contact form</span>
                                </label>
                                <p class="wpiko-chatbot-settings-field-description">When enabled, a dropdown menu will appear in the contact form.</p>
                                <div class="contact-form-subfield">
                                    <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_form_dropdown_options">Dropdown options</label>
                                    <textarea id="wpiko_chatbot_contact_form_dropdown_options" name="wpiko_chatbot_contact_form_dropdown_options" rows="5" placeholder="Enter each option on a new line"><?php echo esc_textarea($dropdown_options); ?></textarea>
                                    <p class="wpiko-chatbot-settings-field-description">Enter each dropdown option on a new line. These will appear in the contact form dropdown menu when enabled.</p>
                                </div>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <div class="wpiko-chatbot-settings-field-label">
                                File Attachments
                            </div>
                            <div class="wpiko-chatbot-settings-field-control">
                                <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_contact_form_attachments">
                                    <input type="checkbox" role="switch" id="wpiko_chatbot_contact_form_attachments" name="wpiko_chatbot_contact_form_attachments" value="1" <?php checked('1', $enable_attachments); ?>>
                                    <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true">
                                    </span>
                                    <span class="wpiko-chatbot-settings-switch__label">Allow file attachments in contact form</span>
                                </label>
                                <p class="wpiko-chatbot-settings-field-description">When enabled, users can attach images to their messages.</p>
                                <p class="wpiko-chatbot-settings-field-description">Only image files (jpg, jpeg, png, gif) are allowed. Maximum file size per image: 3MB.</p>
                            </div>
                        </div>
                    </div>
                </details>
                <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure">
                    <summary class="wpiko-chatbot-settings-disclosure-title">AI Integration</summary>
                    <div class="wpiko-chatbot-settings-disclosure-content">
                        <div class="wpiko-chatbot-settings-field-row">
                            <div class="wpiko-chatbot-settings-field-label">
                                Enable AI Contact Form Response
                            </div>
                            <div class="wpiko-chatbot-settings-field-control">
                                <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_contact_form_ai_response">
                                    <input type="checkbox" role="switch" id="wpiko_chatbot_contact_form_ai_response" name="wpiko_chatbot_contact_form_ai_response" value="1" <?php checked('1', $enable_ai_response); ?>>
                                    <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true">
                                    </span>
                                    <span class="wpiko-chatbot-settings-switch__label">Allow the AI to automatically offer the contact form</span>
                                </label>
                                <p class="wpiko-chatbot-settings-field-description">When enabled, the AI will automatically suggest the contact form when users need human assistance. No manual assistant instructions needed.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row ai-trigger-row" <?php if ($enable_ai_response !== '1'): ?>hidden<?php endif; ?>>
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_form_ai_trigger">AI Trigger Behavior</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <select id="wpiko_chatbot_contact_form_ai_trigger" name="wpiko_chatbot_contact_form_ai_trigger">
                                    <option value="support" <?php selected('support', $ai_trigger); ?>>
                                        When user needs human support (Recommended)
                                    </option>
                                    <option value="explicit" <?php selected('explicit', $ai_trigger); ?>>
                                        Only when user explicitly asks to contact
                                    </option>
                                    <option value="custom" <?php selected('custom', $ai_trigger); ?>>
                                        Custom trigger conditions
                                    </option>
                                </select>
                                <p class="wpiko-chatbot-settings-field-description">
                                    <strong>When user needs human support:</strong>
                                    The AI offers the form for problems, complaints, questions needing follow-up, or when it can't help directly.
                                </p>
                                <p class="wpiko-chatbot-settings-field-description">
                                    <strong>Only when user explicitly asks:</strong>
                                    The AI only shows the form when the user clearly asks to contact support or send a message.
                                </p>
                                <p class="wpiko-chatbot-settings-field-description">
                                    <strong>Custom:</strong>
                                    Define your own conditions below.
                                </p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row ai-custom-trigger-row" <?php if ($enable_ai_response !== '1' || $ai_trigger !== 'custom'): ?>hidden<?php endif; ?>>
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_form_ai_custom_trigger">Custom Trigger Instructions</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <textarea id="wpiko_chatbot_contact_form_ai_custom_trigger" name="wpiko_chatbot_contact_form_ai_custom_trigger" rows="4" placeholder="e.g. Offer the contact form when the user asks about pricing, has a billing issue, or requests a feature."><?php echo esc_textarea($ai_custom_trigger); ?></textarea>
                                <p class="wpiko-chatbot-settings-field-description">Describe when the AI should offer the contact form. Be specific about the types of queries that should trigger it.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row ai-prefill-row" <?php if ($enable_ai_response !== '1'): ?>hidden<?php endif; ?>>
                            <div class="wpiko-chatbot-settings-field-label">
                                Enable Form Pre-fill
                            </div>
                            <div class="wpiko-chatbot-settings-field-control">
                                <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_contact_form_ai_prefill">
                                    <input type="checkbox" role="switch" id="wpiko_chatbot_contact_form_ai_prefill" name="wpiko_chatbot_contact_form_ai_prefill" value="1" <?php checked('1', $enable_ai_prefill); ?>>
                                    <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true">
                                    </span>
                                    <span class="wpiko-chatbot-settings-switch__label">Allow AI to pre-fill form fields</span>
                                </label>
                                <p class="wpiko-chatbot-settings-field-description">When enabled, the AI will summarize the user's inquiry and pre-fill the message field (and category if available). Users can review and edit before sending.</p>
                            </div>
                        </div>
                    </div>
                </details>
                <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure">
                    <summary class="wpiko-chatbot-settings-disclosure-title">Google reCAPTCHA</summary>
                    <div class="wpiko-chatbot-settings-disclosure-content">
                        <div class="wpiko-chatbot-settings-field-row enable-google-recaptcha-row">
                            <div class="wpiko-chatbot-settings-field-label">
                                Enable Google reCAPTCHA
                            </div>
                            <div class="wpiko-chatbot-settings-field-control">
                                <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_enable_recaptcha">
                                    <input type="checkbox" role="switch" id="wpiko_chatbot_enable_recaptcha" name="wpiko_chatbot_enable_recaptcha" value="1" <?php checked('1', $enable_recaptcha); ?>>
                                    <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true">
                                    </span>
                                    <span class="wpiko-chatbot-settings-switch__label">Enable Google reCAPTCHA for contact form</span>
                                </label>
                                <p class="wpiko-chatbot-settings-field-description">When enabled, Google reCAPTCHA will be used to prevent spam submissions.</p>
                                <p class="wpiko-chatbot-settings-field-description">
                                    <strong>Note:</strong>
                                    You must use reCAPTCHA v3 for this integration to work properly.
                                    <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">
                                        Create your reCAPTCHA keys here
                                    </a>
                                    .
                                </p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row recaptcha-site-key-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_recaptcha_site_key">reCAPTCHA Site Key</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_recaptcha_site_key" name="wpiko_chatbot_recaptcha_site_key" value="<?php echo esc_attr($recaptcha_site_key); ?>" placeholder="Enter your reCAPTCHA site key">
                                <p class="wpiko-chatbot-settings-field-description">Enter the site key provided by Google reCAPTCHA.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row recaptcha-secret-key-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_recaptcha_secret_key">reCAPTCHA Secret Key</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_recaptcha_secret_key" name="wpiko_chatbot_recaptcha_secret_key" value="<?php echo esc_attr($recaptcha_secret_key); ?>" placeholder="Enter your reCAPTCHA secret key">
                                <p class="wpiko-chatbot-settings-field-description">Enter the secret key provided by Google reCAPTCHA.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row recaptcha-threshold-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_recaptcha_threshold">reCAPTCHA Score Threshold</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="number" id="wpiko_chatbot_recaptcha_threshold" name="wpiko_chatbot_recaptcha_threshold" value="<?php echo esc_attr($recaptcha_threshold); ?>" min="0" max="1" step="0.1">
                                <p class="wpiko-chatbot-settings-field-description">
                                    Set the minimum score required to accept submissions (0.0 - 1.0).
                                    <strong>Default: 0.5 (Recommended)</strong>
                                </p>
                                <div class="contact-form-score-guide">
                                    <strong>Score guide</strong>
                                    <div class="contact-form-score-guide-item">
                                        <span class="contact-form-score-guide-range">0.9-1.0</span>
                                        <span class="contact-form-score-guide-description">Very strict - may block some legitimate users</span>
                                    </div>
                                    <div class="contact-form-score-guide-item">
                                        <span class="contact-form-score-guide-range">0.7-0.8</span>
                                        <span class="contact-form-score-guide-description">Strict - good balance for high-security needs</span>
                                    </div>
                                    <div class="contact-form-score-guide-item">
                                        <span class="contact-form-score-guide-range">0.5</span>
                                        <span class="contact-form-score-guide-description">Balanced - recommended for most sites</span>
                                    </div>
                                    <div class="contact-form-score-guide-item">
                                        <span class="contact-form-score-guide-range">0.3-0.4</span>
                                        <span class="contact-form-score-guide-description">Lenient - more spam may pass through</span>
                                    </div>
                                    <div class="contact-form-score-guide-item">
                                        <span class="contact-form-score-guide-range">0.0-0.2</span>
                                        <span class="contact-form-score-guide-description">Very lenient - not recommended</span>
                                    </div>
                                    <div class="contact-form-score-guide-note">
                                        <strong>Note:</strong>
                                        reCAPTCHA v3 assigns a score to each submission. Lower scores indicate bot-like behavior. If you're getting spam, increase this value. If legitimate users are blocked, decrease it slightly.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <div class="wpiko-chatbot-settings-field-label">
                                Hide reCAPTCHA Badge
                            </div>
                            <div class="wpiko-chatbot-settings-field-control">
                                <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_hide_recaptcha_badge">
                                    <input type="checkbox" role="switch" id="wpiko_chatbot_hide_recaptcha_badge" name="wpiko_chatbot_hide_recaptcha_badge" value="1" <?php checked('1', $hide_recaptcha_badge); ?>>
                                    <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true">
                                    </span>
                                    <span class="wpiko-chatbot-settings-switch__label">Hide the reCAPTCHA badge</span>
                                </label>
                                <p class="wpiko-chatbot-settings-field-description">When enabled, the reCAPTCHA badge will be hidden.</p>
                                <p class="wpiko-chatbot-settings-field-description">
                                    <strong>Note:</strong>
                                    When the badge is hidden, a reCAPTCHA notice text is displayed on the contact form instead.
                                </p>
                            </div>
                        </div>
                    </div>
                </details>
                <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure">
                    <summary class="wpiko-chatbot-settings-disclosure-title">Customizable Text</summary>
                    <div class="wpiko-chatbot-settings-disclosure-content">
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_menu_text">Menu Item Text</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_menu_text" name="wpiko_chatbot_contact_menu_text" value="<?php echo esc_attr($contact_menu_text); ?>">
                                <p class="wpiko-chatbot-settings-field-description">
                                    Text displayed in the chatbot menu. Default:
                                    <em>
                                        Contact Form
                                    </em>
                                </p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_form_title">Form Title</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_form_title" name="wpiko_chatbot_contact_form_title" value="<?php echo esc_attr($contact_title); ?>">
                                <p class="wpiko-chatbot-settings-field-description">The heading displayed at the top of the contact form.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_form_intro">Intro Text</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <textarea id="wpiko_chatbot_contact_form_intro" name="wpiko_chatbot_contact_form_intro" rows="3"><?php echo esc_textarea($contact_intro); ?></textarea>
                                <p class="wpiko-chatbot-settings-field-description">The introductory message shown below the form title.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_name_label">Name Field Label</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_name_label" name="wpiko_chatbot_contact_name_label" value="<?php echo esc_attr($contact_name_label); ?>">
                                <p class="wpiko-chatbot-settings-field-description">Placeholder text for the name input field.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_email_label">Email Field Label</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_email_label" name="wpiko_chatbot_contact_email_label" value="<?php echo esc_attr($contact_email_label); ?>">
                                <p class="wpiko-chatbot-settings-field-description">Placeholder text for the email input field.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_category_label">Category Dropdown Label</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_category_label" name="wpiko_chatbot_contact_category_label" value="<?php echo esc_attr($contact_category_label); ?>">
                                <p class="wpiko-chatbot-settings-field-description">Default option text for the category dropdown menu.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_message_label">Message Field Label</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_message_label" name="wpiko_chatbot_contact_message_label" value="<?php echo esc_attr($contact_message_label); ?>">
                                <p class="wpiko-chatbot-settings-field-description">Placeholder text for the message textarea.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_cancel_btn">Cancel Button Text</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_cancel_btn" name="wpiko_chatbot_contact_cancel_btn" value="<?php echo esc_attr($contact_cancel_btn); ?>">
                                <p class="wpiko-chatbot-settings-field-description">Text for the button that closes the contact form.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_send_btn">Send Button Text</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_send_btn" name="wpiko_chatbot_contact_send_btn" value="<?php echo esc_attr($contact_send_btn); ?>">
                                <p class="wpiko-chatbot-settings-field-description">Text for the button that submits the contact form.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_try_again_btn">Try Again Button Text</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_try_again_btn" name="wpiko_chatbot_contact_try_again_btn" value="<?php echo esc_attr($contact_try_again_btn); ?>">
                                <p class="wpiko-chatbot-settings-field-description">Shown when an error occurs during form submission.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_recaptcha_text">reCAPTCHA Text</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <textarea id="wpiko_chatbot_contact_recaptcha_text" name="wpiko_chatbot_contact_recaptcha_text" rows="2"><?php echo esc_textarea($contact_recaptcha_text); ?></textarea>
                                <p class="wpiko-chatbot-settings-field-description">
                                    This text is displayed on the contact form only when the reCAPTCHA badge is hidden. Default:
                                    <em>
                                        This site is protected by reCAPTCHA.
                                    </em>
                                </p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_attachment_label">Attachments Label</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_attachment_label" name="wpiko_chatbot_contact_attachment_label" value="<?php echo esc_attr($contact_attachment_label); ?>">
                                <p class="wpiko-chatbot-settings-field-description">Label shown above the file attachment slots.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_success_message">Success Message</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <textarea id="wpiko_chatbot_contact_success_message" name="wpiko_chatbot_contact_success_message" rows="3"><?php echo esc_textarea($contact_success_message); ?></textarea>
                                <p class="wpiko-chatbot-settings-field-description">
                                    Default:
                                    <em>
                                        Contact Form - Your message has been sent successfully. We will get back to you as soon as possible.
                                    </em>
                                </p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_email_failed_error">Email Send Failed</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <textarea id="wpiko_chatbot_contact_email_failed_error" name="wpiko_chatbot_contact_email_failed_error" rows="2"><?php echo esc_textarea($contact_email_failed_error); ?></textarea>
                                <p class="wpiko-chatbot-settings-field-description">
                                    Default:
                                    <em>
                                        Contact Form - Failed to send your message. Please try again later or contact us through another method.
                                    </em>
                                </p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_rate_limit_error">Rate Limit Error</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_contact_rate_limit_error" name="wpiko_chatbot_contact_rate_limit_error" value="<?php echo esc_attr($contact_rate_limit_error); ?>">
                                <p class="wpiko-chatbot-settings-field-description">
                                    Default:
                                    <em>
                                        You have submitted too many contact forms. Please try again later.
                                    </em>
                                </p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_contact_upload_error">File Upload Error</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <textarea id="wpiko_chatbot_contact_upload_error" name="wpiko_chatbot_contact_upload_error" rows="3"><?php echo esc_textarea($contact_upload_error); ?></textarea>
                                <p class="wpiko-chatbot-settings-field-description">
                                    Default:
                                    <em>
                                        There was a problem with your file upload. Please ensure it is a valid image (JPG, PNG, GIF) under 3MB.
                                    </em>
                                </p>
                            </div>
                        </div>
                    </div>
                </details>
                <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure">
                    <summary class="wpiko-chatbot-settings-disclosure-title">
                        Integration
                        &
                        Instructions
                    </summary>
                    <div class="wpiko-chatbot-settings-disclosure-content">
                        <div class="contact-form-instructions">
                            <h4>AI Automatic Integration</h4>
                            <p>
                                When
                                <strong>AI Contact Form Response</strong>
                                is enabled (in the AI Integration section above), the AI will automatically offer the contact form when appropriate.
                                <strong>No manual instructions are needed.</strong>
                            </p>
                            <p>The AI will also pre-fill the form with a summary of the user's inquiry (if Form Pre-fill is enabled).</p>
                            <hr>
                            <h4>Manual Methods (Alternative)</h4>
                            <p>If you prefer manual control, you can still use the following methods:</p>
                            <h5>Method 1: Chatbot provides a Contact Form link that instantly opens the Contact Form</h5>
                            <p>
                                <strong>Go to:</strong>
                                WPiko AI Chatbot → AI Configuration → Edit Assistant → Specific System Instructions:
                            </p>
                            <pre class="wpiko-chatbot-settings-code">For support inquiries, provide only: Wpiko Form</pre>
                            <h5>Method 2: Link redirects users to page and launches form</h5>
                            <pre class="wpiko-chatbot-settings-code">For support inquiries, direct users to https://example.com/page/?wpiko_contact=open</pre>
                            <hr>
                            <h4>Custom Contact Link (Menu / Buttons)</h4>
                            <p>You can add a link anywhere on your website to open the chatbot with the contact form directly.</p>
                            <h5>Method 1: Add a Custom Link to Menus</h5>
                            <p>
                                Go to
                                <strong>Appearance → Menus → Custom Links</strong>
                                and set URL to
                                <code>?wpiko_contact=open</code>
                                with Label
                                <code>Contact Us</code>
                                .
                            </p>
                            <h5>Method 2: Use a Website URL</h5>
                            <p>
                                Simply add
                                <code>?wpiko_contact=open</code>
                                to any URL on your website.
                            </p>
                            <pre class="wpiko-chatbot-settings-code">https://example.com/page/?wpiko_contact=open</pre>
                        </div>
                    </div>
                </details>
            </div>
            <div class="wpiko-chatbot-settings-actions">
                <input type="submit" name="wpiko_chatbot_save_contact_form" class="button wpiko-chatbot-settings-save" value="Save Changes">
            </div>
        </form>
        <script>
            jQuery(function($) {
                var $page = $('.wpiko-contact-form-page');
                $('#wpiko_chatbot_enable_contact_form').on('change', function() {
                    $('#contact-form-options').prop('hidden', !this.checked);
                });
                function updateAISettings() {
                    var enabled = $('#wpiko_chatbot_contact_form_ai_response').is(':checked');
                    $page.find('.ai-trigger-row, .ai-prefill-row').prop('hidden', !enabled);
                    $page.find('.ai-custom-trigger-row').prop('hidden', !enabled || $('#wpiko_chatbot_contact_form_ai_trigger').val() !== 'custom');
                }
                $('#wpiko_chatbot_contact_form_ai_response, #wpiko_chatbot_contact_form_ai_trigger').on('change', updateAISettings);
            });
        </script>
        <?php elseif ($is_license_expired): ?>
        <section class="wpiko-chatbot-settings-card wpiko-chatbot-settings-upgrade" aria-labelledby="contact-form-expired-title">
            <h2 class="wpiko-chatbot-settings-card-title" id="contact-form-expired-title">Contact Form Disabled</h2>
            <div class="wpiko-chatbot-settings-notice is-warning">
                <p>Your license has expired. Contact form feature has been disabled.</p>
            </div>
            <p class="wpiko-chatbot-settings-field-description">Get a lifetime license to regain access to contact forms, file attachments, custom fields, AI integration, and reCAPTCHA protection.</p>
            <ul class="wpiko-chatbot-settings-benefits">
                <li>Add a contact form to the chatbot menu</li>
                <li>Customize fields, categories, and messages</li>
                <li>Accept image attachments and protect against spam</li>
                <li>Let AI offer and pre-fill the form</li>
            </ul>
            <a href="https://wpiko.com/chatbot-pricing/" target="_blank" rel="noopener" class="button wpiko-chatbot-settings-save"> Get Lifetime License </a>
        </section>
        <?php else: ?>
        <section class="wpiko-chatbot-settings-card wpiko-chatbot-settings-upgrade" aria-labelledby="contact-form-unlock-title">
            <h2 class="wpiko-chatbot-settings-card-title" id="contact-form-unlock-title">Unlock Contact Form</h2>
            <p class="wpiko-chatbot-settings-field-description">Activate Pro to add a contact form to your chatbot.</p>
            <ul class="wpiko-chatbot-settings-benefits">
                <li>Add a contact form to the chatbot menu</li>
                <li>Customize fields, categories, and messages</li>
                <li>Accept image attachments and protect against spam</li>
                <li>Let AI offer and pre-fill the form</li>
            </ul>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-chatbot&tab=license_activation')); ?>" class="button wpiko-chatbot-settings-save"> Activate Pro </a>
        </section>
        <?php endif; ?>
    </div>
    <?php
}
