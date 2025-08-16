<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_email_capture_section() {
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
            
            echo '<div class="updated"><p>Email Capture settings updated successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Email Capture settings cannot be saved without an active license.</p></div>';
        }
    }
    
    // Get current settings
    $enable_email_capture = get_option('wpiko_chatbot_enable_email_capture', '0');
    $email_capture_title = get_option('wpiko_chatbot_email_capture_title', 'Enter your details to get started');
    $email_capture_description = get_option('wpiko_chatbot_email_capture_description', 'Please provide your name and email to start chatting with our AI assistant.');
    $email_capture_button_text = get_option('wpiko_chatbot_email_capture_button_text', 'Continue');
    
    // Check if license is active
    $is_license_active = wpiko_chatbot_pro_is_license_active();
    ?>
    
    <div class="email-capture-section">
        <div class="email-capture-section-header">
            <h2>
                <span class="dashicons dashicons-email"></span> 
                Email Capture
                <?php if (!wpiko_chatbot_is_license_active()): ?>
                    <span class="premium-feature-badge">Premium</span>
                <?php endif; ?>
            </h2>
            <p class="description">Configure email capture settings to collect user information before starting chat sessions.</p>
        </div>
        <?php if ($is_license_active): ?>
            <form method="post" action="">
                <?php wp_nonce_field('save_email_capture_settings', 'email_capture_nonce'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Enable Email Capture</th>
                        <td>
                            <label class="wpiko-switch">
                                <input type="checkbox" name="wpiko_chatbot_enable_email_capture" id="wpiko_chatbot_enable_email_capture" value="1" <?php checked($enable_email_capture, '1'); ?>>
                                <span class="wpiko-slider round"></span>
                            </label>
                            <label for="wpiko_chatbot_enable_email_capture">Enable the email capture feature in the chatbot</label>
                            <p class="description">When enabled, guest users will be asked to provide their name and email before starting the chat.</p>
                        </td>
                    </tr>
                </table>

                <div class="email-capture-settings-collapsible <?php echo $enable_email_capture ? 'active' : ''; ?>">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="email_capture_title">Email Capture Title</label></th>
                            <td>
                                <input type="text" name="email_capture_title" id="email_capture_title" value="<?php echo esc_attr($email_capture_title); ?>" class="regular-text">
                                <p class="description">The title text displayed in the email capture popup.</p>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row"><label for="email_capture_description">Email Capture Description</label></th>
                            <td>
                                <textarea name="email_capture_description" id="email_capture_description" rows="3" class="large-text"><?php echo esc_textarea($email_capture_description); ?></textarea>
                                <p class="description">The description text displayed in the email capture popup.</p>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row"><label for="email_capture_button_text">Button Text</label></th>
                            <td>
                                <input type="text" name="email_capture_button_text" id="email_capture_button_text" value="<?php echo esc_attr($email_capture_button_text); ?>" class="regular-text">
                                <p class="description">The text displayed on the submit button in the email capture popup.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <input type="hidden" name="action" value="save_email_capture_settings">
                <?php submit_button('Save Email Capture Settings'); ?>
            </form>

            <script>
                jQuery(document).ready(function($) {
                    // Toggle email capture settings visibility based on checkbox state
                    $('#wpiko_chatbot_enable_email_capture').change(function() {
                        if ($(this).is(':checked')) {
                            $('.email-capture-settings-collapsible').addClass('active');
                        } else {
                            $('.email-capture-settings-collapsible').removeClass('active');
                        }
                    });
                });
            </script>
            
        <?php else: ?>
            <div class="premium-feature-notice email-capture-notice">
                <h3>📧 Unlock Email Capture</h3>
                <p>Upgrade to Premium to collect user information before chat sessions:</p>
                <ul>
                    <li>✨ Capture user names and email addresses</li>
                    <li>📧 Build your email list automatically</li>
                    <li>🎯 Improve lead generation</li>
                    <li>📊 Track user engagement</li>
                    <li>🔧 Customize popup appearance</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Upgrade to Premium</a>
            </div>
        <?php endif; ?>
    </div>
    
    
    <?php
}
