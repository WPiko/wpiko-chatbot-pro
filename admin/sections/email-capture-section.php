<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_email_capture_section() {
    $settings_saved = false;
    $license_error = false;

    // Handle form submission
    if (isset($_POST['action']) && $_POST['action'] == 'save_email_capture_settings') {
        check_admin_referer('save_email_capture_settings', 'email_capture_nonce');
        
        // Only save if license is active
        if (wpiko_chatbot_pro_is_license_active()) {
            // Save email capture enabled/disabled setting
            $enable_email_capture = isset($_POST['wpiko_chatbot_enable_email_capture']) ? '1' : '0';
            update_option('wpiko_chatbot_enable_email_capture', $enable_email_capture);
            
            // Save email capture title if provided
            if (isset($_POST['email_capture_title'])) {
                update_option('wpiko_chatbot_email_capture_title', sanitize_text_field(wp_unslash($_POST['email_capture_title'])));
            }
            
            // Save email capture description if provided
            if (isset($_POST['email_capture_description'])) {
                update_option('wpiko_chatbot_email_capture_description', sanitize_textarea_field(wp_unslash($_POST['email_capture_description'])));
            }
            
            // Save email capture button text if provided
            if (isset($_POST['email_capture_button_text'])) {
                update_option('wpiko_chatbot_email_capture_button_text', sanitize_text_field(wp_unslash($_POST['email_capture_button_text'])));
            }

            // Save email capture name placeholder if provided
            if (isset($_POST['email_capture_name_placeholder'])) {
                update_option('wpiko_chatbot_email_capture_name_placeholder', sanitize_text_field(wp_unslash($_POST['email_capture_name_placeholder'])));
            }
            
            // Save email capture email placeholder if provided
            if (isset($_POST['email_capture_email_placeholder'])) {
                update_option('wpiko_chatbot_email_capture_email_placeholder', sanitize_text_field(wp_unslash($_POST['email_capture_email_placeholder'])));
            }

            // Save validation error message if provided
            if (isset($_POST['email_capture_validation_error'])) {
                update_option('wpiko_chatbot_email_capture_validation_error', sanitize_text_field(wp_unslash($_POST['email_capture_validation_error'])));
            }

            // Save change email menu text if provided
            if (isset($_POST['email_capture_change_email_text'])) {
                update_option('wpiko_chatbot_email_capture_change_email_text', sanitize_text_field(wp_unslash($_POST['email_capture_change_email_text'])));
            }
            
            $settings_saved = true;
        } else {
            $license_error = true;
        }
    }
    
    // Get current settings
    $enable_email_capture = get_option('wpiko_chatbot_enable_email_capture', '0');
    $email_capture_title = get_option('wpiko_chatbot_email_capture_title', 'Enter your details to get started');
    $email_capture_description = get_option('wpiko_chatbot_email_capture_description', 'Please provide your name and email to start chatting with our AI assistant.');
    $email_capture_button_text = get_option('wpiko_chatbot_email_capture_button_text', 'Continue');
    $email_capture_name_placeholder = get_option('wpiko_chatbot_email_capture_name_placeholder', 'Enter your name');
    $email_capture_email_placeholder = get_option('wpiko_chatbot_email_capture_email_placeholder', 'Enter your email');
    $email_capture_validation_error = get_option('wpiko_chatbot_email_capture_validation_error', 'Please check your input and try again.');
    $email_capture_change_email_text = get_option('wpiko_chatbot_email_capture_change_email_text', 'Change Email');
    
    // Check if license is active
    $is_license_active = wpiko_chatbot_pro_is_license_active();
    ?>
    <div class="wpiko-chatbot-settings-page wpiko-email-capture-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>Email Capture<?php if (!wpiko_chatbot_is_license_active()): ?> <span class="email-capture-pro-badge">PRO</span><?php endif; ?></h1>
            <p class="wpiko-chatbot-settings-subtitle">Collect guest names and email addresses before chat sessions and customize the capture form text.</p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p>Email Capture settings updated successfully.</p></div>
        <?php endif; ?>
        <?php if ($license_error): ?>
            <div class="wpiko-chatbot-settings-notice is-error" role="alert"><p>Email Capture settings cannot be saved without an active license.</p></div>
        <?php endif; ?>
        <?php if ($is_license_active): ?>
            <form method="post" action="" class="wpiko-chatbot-settings-form">
                <?php wp_nonce_field('save_email_capture_settings', 'email_capture_nonce'); ?>
                <section class="wpiko-chatbot-settings-card" aria-labelledby="email-capture-settings-title">
                    <h2 class="wpiko-chatbot-settings-card-title" id="email-capture-settings-title">Capture settings</h2>
                    <div class="wpiko-chatbot-settings-field-row">
                        <div class="wpiko-chatbot-settings-field-label">Email capture</div>
                        <div class="wpiko-chatbot-settings-field-control">
                            <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_enable_email_capture">
                                <input type="checkbox" role="switch" name="wpiko_chatbot_enable_email_capture" id="wpiko_chatbot_enable_email_capture" value="1" aria-controls="email-capture-customization" aria-describedby="email-capture-enabled-description" <?php checked($enable_email_capture, '1'); ?>>
                                <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                                <span class="wpiko-chatbot-settings-switch__label">Enable email capture</span>
                            </label>
                            <p class="wpiko-chatbot-settings-field-description" id="email-capture-enabled-description">Guest users will be asked to provide their name and email before starting the chat.</p>
                        </div>
                    </div>
                </section>
                <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure" id="email-capture-customization" <?php if (!$enable_email_capture): ?>hidden<?php endif; ?>>
                    <summary class="wpiko-chatbot-settings-disclosure-title">Customizable text</summary>
                    <div class="wpiko-chatbot-settings-disclosure-content">
                        <p class="wpiko-chatbot-settings-field-description">Set the messages, field placeholders, and button labels shown to visitors.</p>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="email_capture_title">Email capture title</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" name="email_capture_title" id="email_capture_title" value="<?php echo esc_attr($email_capture_title); ?>" aria-describedby="email_capture_title-description">
                                <p class="wpiko-chatbot-settings-field-description" id="email_capture_title-description">The title displayed in the email capture popup.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="email_capture_description">Email capture description</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <textarea name="email_capture_description" id="email_capture_description" rows="3" aria-describedby="email_capture_description-description"><?php echo esc_textarea($email_capture_description); ?></textarea>
                                <p class="wpiko-chatbot-settings-field-description" id="email_capture_description-description">The description displayed below the popup title.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="email_capture_name_placeholder">Name field placeholder</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" name="email_capture_name_placeholder" id="email_capture_name_placeholder" value="<?php echo esc_attr($email_capture_name_placeholder); ?>" aria-describedby="email_capture_name_placeholder-description">
                                <p class="wpiko-chatbot-settings-field-description" id="email_capture_name_placeholder-description">The placeholder text for the name input field.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="email_capture_email_placeholder">Email field placeholder</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" name="email_capture_email_placeholder" id="email_capture_email_placeholder" value="<?php echo esc_attr($email_capture_email_placeholder); ?>" aria-describedby="email_capture_email_placeholder-description">
                                <p class="wpiko-chatbot-settings-field-description" id="email_capture_email_placeholder-description">The placeholder text for the email input field.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="email_capture_button_text">Button text</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" name="email_capture_button_text" id="email_capture_button_text" value="<?php echo esc_attr($email_capture_button_text); ?>" aria-describedby="email_capture_button_text-description">
                                <p class="wpiko-chatbot-settings-field-description" id="email_capture_button_text-description">The text on the submit button in the email capture popup.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="email_capture_validation_error">Validation error message</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" name="email_capture_validation_error" id="email_capture_validation_error" value="<?php echo esc_attr($email_capture_validation_error); ?>" aria-describedby="email_capture_validation_error-description">
                                <p class="wpiko-chatbot-settings-field-description" id="email_capture_validation_error-description">Shown when a visitor submits invalid or incomplete information.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="email_capture_change_email_text">Change email menu text</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" name="email_capture_change_email_text" id="email_capture_change_email_text" value="<?php echo esc_attr($email_capture_change_email_text); ?>" aria-describedby="email_capture_change_email_text-description">
                                <p class="wpiko-chatbot-settings-field-description" id="email_capture_change_email_text-description">The chatbot menu label for changing an email address. Default: Change Email.</p>
                            </div>
                        </div>
                    </div>
                </details>
                <input type="hidden" name="action" value="save_email_capture_settings">
                <div class="wpiko-chatbot-settings-actions">
                    <button type="submit" name="submit" id="submit" class="button wpiko-chatbot-settings-save">Save Email Capture Settings</button>
                </div>
            </form>
            <script>
                jQuery(function($) {
                    $('#wpiko_chatbot_enable_email_capture').on('change', function() {
                        $('#email-capture-customization').prop('hidden', !this.checked);
                    });
                });
            </script>
        <?php else: ?>
            <section class="wpiko-chatbot-settings-card wpiko-chatbot-settings-upgrade" aria-labelledby="email-capture-unlock-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="email-capture-unlock-title">Unlock Email Capture</h2>
                <p class="wpiko-chatbot-settings-field-description">Activate Pro to collect user information before chat sessions.</p>
                <ul class="wpiko-chatbot-settings-benefits">
                    <li>Capture guest names and email addresses</li>
                    <li>Build your email list automatically</li>
                    <li>Improve lead generation and track engagement</li>
                    <li>Customize the capture form text</li>
                </ul>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-chatbot&tab=license_activation')); ?>" class="button wpiko-chatbot-settings-save">Activate Pro</a>
            </section>
        <?php endif; ?>
    </div>
    <?php
}
