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

        // Show success message
        echo '<div class="notice notice-success is-dismissible"><p>Contact form settings saved successfully!</p></div>';
    }

    // Get current settings
    $enable_contact_form = get_option('wpiko_chatbot_enable_contact_form', '0');
    $enable_dropdown = get_option('wpiko_chatbot_contact_form_dropdown', '0');
    $enable_attachments = get_option('wpiko_chatbot_contact_form_attachments', '0');
    $dropdown_options = get_option('wpiko_chatbot_contact_form_dropdown_options', '');
    $enable_recaptcha = get_option('wpiko_chatbot_enable_recaptcha', '0');
    $recaptcha_site_key = get_option('wpiko_chatbot_recaptcha_site_key', '');
    $recaptcha_secret_key = get_option('wpiko_chatbot_recaptcha_secret_key', '');
    $recaptcha_threshold = get_option('wpiko_chatbot_recaptcha_threshold', '0.5');
    $hide_recaptcha_badge = get_option('wpiko_chatbot_hide_recaptcha_badge', '0');

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
                                    <tr class="enable-form-dropdown-row">
                                        <th scope="row">Enable Form Dropdown</th>
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
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Dropdown Options</th>
                                        <td>
                                            <textarea id="wpiko_chatbot_contact_form_dropdown_options"
                                                name="wpiko_chatbot_contact_form_dropdown_options" rows="5" style="width: 100%;"
                                                placeholder="Enter each option on a new line"><?php echo esc_textarea($dropdown_options); ?></textarea>
                                            <p class="description">Enter each dropdown option on a new line. These will appear
                                                in the contact form dropdown menu when enabled.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Enable File Attachments</th>
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

                        <!-- 2. Google reCAPTCHA Settings Accordion -->
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

                        <!-- 3. Customizable Text Accordion -->
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
                                            <p class="description">Text displayed in the chatbot menu. Default: <em>Contact Form</em></p>
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
                                            <p class="description">This text is displayed on the contact form only when the reCAPTCHA badge is hidden. Default: <em>This site is protected by reCAPTCHA.</em></p>
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
                                            <p class="description">Default: <em>Contact Form - Your message has been sent successfully. We will get back to you as soon as possible.</em></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Email Send Failed</th>
                                        <td>
                                            <textarea name="wpiko_chatbot_contact_email_failed_error" rows="2"
                                                style="width: 100%;"><?php echo esc_textarea($contact_email_failed_error); ?></textarea>
                                            <p class="description">Default: <em>Contact Form - Failed to send your message. Please try again later or contact us through another method.</em></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rate Limit Error</th>
                                        <td>
                                            <input type="text" name="wpiko_chatbot_contact_rate_limit_error"
                                                value="<?php echo esc_attr($contact_rate_limit_error); ?>" style="width: 100%;">
                                            <p class="description">Default: <em>You have submitted too many contact forms. Please try again later.</em></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">File Upload Error</th>
                                        <td>
                                            <textarea name="wpiko_chatbot_contact_upload_error" rows="3"
                                                style="width: 100%;"><?php echo esc_textarea($contact_upload_error); ?></textarea>
                                            <p class="description">Default: <em>There was a problem with your file upload. Please ensure it is a valid image (JPG, PNG, GIF) under 3MB.</em></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- 4. Usage & Integration -->
                        <div class="wpiko-accordion-item">
                            <h3 class="collapsible-header">
                                <span><span class="dashicons dashicons-editor-help"></span> Integration & Instructions</span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </h3>
                            <div class="collapsible-content">
                                <div class="instruction-content">
                                    <h4>Chatbot Contact Link (Assistant Instructions)</h4>
                                    <p>You can add assistant instructions to provide a contact form link within the chatbot
                                        conversation.</p>
                                    <p><strong>Go to:</strong> WPiko Chatbot → AI Configuration → Edit Assistant → Specific
                                        System Instructions:</p>

                                    <h5>Method 1: Chatbot provides a Contact Form link that instantly opens the Contact Form
                                    </h5>
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