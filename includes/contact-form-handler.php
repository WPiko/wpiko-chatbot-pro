<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Handle contact form submissions from the chatbot interface
 */
function wpiko_chatbot_pro_contact_form_handler() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    // Check if license is active
    if (!wpiko_chatbot_is_license_active()) {
        wp_send_json_error(array('message' => 'This feature is only available for premium users. Please upgrade to use the contact form.'));
        return;
    }
    
    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
    $thread_id = isset($_POST['thread_id']) ? sanitize_text_field(wp_unslash($_POST['thread_id'])) : '';
    
    // Get current user ID (0 if not logged in)
    $user_id = get_current_user_id();
    
    // Check if thread_id is empty or not provided - we only want to save to existing threads
    $save_to_conversation = !empty($thread_id);
    
    // HONEYPOT CHECK: Block if honeypot field is filled (bots fill hidden fields)
    $honeypot = isset($_POST['website_url']) ? sanitize_text_field(wp_unslash($_POST['website_url'])) : '';
    if (!empty($honeypot)) {
        // Silently fail - don't give bots feedback
        wpiko_chatbot_log(sprintf(
            'Contact form honeypot triggered - Email: %s, Name: %s, IP: %s',
            $email,
            $name,
            isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown'
        ), 'warning');
        wp_send_json_error(array('message' => 'Please complete all required fields correctly.'));
        return;
    }
    
    // TIMESTAMP VALIDATION: Check if form was submitted too quickly (likely a bot)
    $form_timestamp = isset($_POST['form_timestamp']) ? intval($_POST['form_timestamp']) : 0;
    $current_time = time();
    $min_time = 3; // Minimum 3 seconds to fill form
    
    if ($form_timestamp > 0) {
        $time_taken = $current_time - $form_timestamp;
        if ($time_taken < $min_time) {
            wpiko_chatbot_log(sprintf(
                'Contact form submitted too fast (%d seconds) - Email: %s, Name: %s, IP: %s',
                $time_taken,
                $email,
                $name,
                isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown'
            ), 'warning');
            wp_send_json_error(array('message' => 'Please take your time filling out the form.'));
            return;
        }
    }
    
    // RATE LIMITING: Check if this email has submitted too many times recently
    $rate_limit_key = 'wpiko_contact_rate_' . md5($email);
    $submission_count = get_transient($rate_limit_key);
    $max_submissions = 3; // Max 3 submissions per hour
    $rate_limit_window = HOUR_IN_SECONDS; // 1 hour
    
    if ($submission_count !== false && $submission_count >= $max_submissions) {
        $error_message = 'You have submitted too many contact forms. Please try again later.';
        if ($save_to_conversation) {
            wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
        }
        wpiko_chatbot_log(sprintf(
            'Contact form rate limit exceeded - Email: %s, Count: %d, IP: %s',
            $email,
            $submission_count,
            isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown'
        ), 'warning');
        wp_send_json_error(array('message' => $error_message));
        return;
    }
    
    // Check reCAPTCHA if enabled
    $enable_recaptcha = get_option('wpiko_chatbot_enable_recaptcha', '0');
    if ($enable_recaptcha === '1') {
        $recaptcha_secret = get_option('wpiko_chatbot_recaptcha_secret_key', '');
        $recaptcha_response = isset($_POST['recaptcha_response']) ? sanitize_text_field(wp_unslash($_POST['recaptcha_response'])) : '';
        
        if (empty($recaptcha_secret)) {
            $error_message = 'reCAPTCHA is enabled but not properly configured. Please contact the site administrator.';
            // Save error message to conversation history if thread exists
            if ($save_to_conversation) {
                wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
            }
            wp_send_json_error(array('message' => $error_message));
            return;
        }
        
        if (empty($recaptcha_response)) {
            $error_message = 'Please complete the reCAPTCHA verification.';
            // Save error message to conversation history if thread exists
            if ($save_to_conversation) {
                wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
            }
            wp_send_json_error(array('message' => $error_message));
            return;
        }
        
        // Verify the reCAPTCHA response
        $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret' => $recaptcha_secret,
                'response' => $recaptcha_response
            )
        ));
        
        if (is_wp_error($verify_response)) {
            $error_message = 'Contact Form - Failed to verify reCAPTCHA. Please try again.';
            // Save error message to conversation history if thread exists
            if ($save_to_conversation) {
                wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
            }
            wp_send_json_error(array('message' => $error_message));
            return;
        }
        
        $verify_response = json_decode(wp_remote_retrieve_body($verify_response), true);
        
        if (!isset($verify_response['success']) || $verify_response['success'] !== true) {
            $error_message = 'Contact Form - reCAPTCHA verification failed. Please try again.';
            // Save error message to conversation history if thread exists
            if ($save_to_conversation) {
                wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
            }
            wp_send_json_error(array('message' => $error_message));
            return;
        }
        
        // Check the reCAPTCHA score (0.0 to 1.0)
        $score_threshold = (float)get_option('wpiko_chatbot_recaptcha_threshold', '0.5');
        $score = isset($verify_response['score']) ? (float)$verify_response['score'] : 0.0;
        
        if ($score < $score_threshold) {
            $error_message = 'Contact Form - Suspicious activity detected. Please try again later.';
            // Save error message to conversation history if thread exists
            if ($save_to_conversation) {
                wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
            }
            // Log the failed attempt for monitoring
            wpiko_chatbot_log(sprintf(
                'Contact form blocked - Low reCAPTCHA score (%.2f, threshold: %.2f) - Email: %s, Name: %s, IP: %s',
                $score,
                $score_threshold,
                $email,
                $name,
                isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown'
            ), 'warning');
            wp_send_json_error(array('message' => $error_message));
            return;
        }
    }
    
    // Validate inputs
    if (empty($name)) {
        $error_message = 'Please enter your name.';
        // Save error message to conversation history if thread exists
        if ($save_to_conversation) {
            wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
        }
        wp_send_json_error(array('message' => $error_message));
        return;
    }
    
    if (empty($email) || !is_email($email)) {
        $error_message = 'Please enter a valid email address.';
        // Save error message to conversation history if thread exists
        if ($save_to_conversation) {
            wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
        }
        wp_send_json_error(array('message' => $error_message));
        return;
    }
    
    if (empty($message)) {
        $error_message = 'Please enter a message.';
        // Save error message to conversation history if thread exists
        if ($save_to_conversation) {
            wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
        }
        wp_send_json_error(array('message' => $error_message));
        return;
    }
    
    // First save the user's contact form submission to the conversation history if thread exists
    if ($save_to_conversation) {
        $user_submission = "Contact Form Submission:\nName: $name\nEmail: $email" . (!empty($category) ? "\nCategory: $category" : "") . "\nMessage: $message";
        wpiko_chatbot_save_message($user_id, $thread_id, 'user', $user_submission, $email);
    }
    
    // Handle file uploads if attachments are enabled
    $attachment_paths = array();
    if (get_option('wpiko_chatbot_contact_form_attachments', '0') === '1') {
        // Get WordPress upload directory
        $upload_dir = wp_upload_dir();
        
        // Process up to 3 attachments
        for ($i = 1; $i <= 3; $i++) {
            $attachment_key = "attachment-{$i}";
            
            if (isset($_FILES[$attachment_key]) && isset($_FILES[$attachment_key]['error']) && $_FILES[$attachment_key]['error'] == 0) {
                // Sanitize the file data
                // Get tmp_name and verify it's a valid uploaded file
                $tmp_name = '';
                // First sanitize the attachment key to ensure safe file reference
                $safe_attachment_key = sanitize_key($attachment_key);
                
                // Get the tmp_name safely by first sanitizing the path
                $uploaded_tmp_name = isset($_FILES[$safe_attachment_key]['tmp_name']) ? sanitize_text_field($_FILES[$safe_attachment_key]['tmp_name']) : '';
                
                // Only proceed if the tmp_name is not empty
                if (!empty($uploaded_tmp_name)) {
                    // Validate that this is actually an uploaded file
                    if (is_uploaded_file($uploaded_tmp_name)) {
                        $tmp_name = $uploaded_tmp_name;
                    }
                }
                
                $file = array(
                    'name'     => isset($_FILES[$attachment_key]['name']) ? sanitize_file_name($_FILES[$attachment_key]['name']) : '',
                    'type'     => isset($_FILES[$attachment_key]['type']) ? sanitize_text_field($_FILES[$attachment_key]['type']) : '',
                    'tmp_name' => $tmp_name, // Using the validated tmp_name
                    'error'    => isset($_FILES[$attachment_key]['error']) ? (int)$_FILES[$attachment_key]['error'] : 0,
                    'size'     => isset($_FILES[$attachment_key]['size']) ? (int)$_FILES[$attachment_key]['size'] : 0
                );
                
                // Verify file type
                $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
                if (!in_array($file['type'], $allowed_types)) {
                    $error_message = "Invalid file type for image {$i}. Only JPG, PNG, and GIF images are allowed.";
                    // Save error message to conversation history if thread exists
                    if ($save_to_conversation) {
                        wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
                    }
                    // Clean up any previously uploaded files
                    foreach ($attachment_paths as $path) {
                        if (file_exists($path)) {
                            wp_delete_file($path);
                        }
                    }
                    wp_send_json_error(array('message' => $error_message));
                    return;
                }
                
                // Verify file size (max 3MB)
                if ($file['size'] > 3 * 1024 * 1024) {
                    $error_message = "File {$i} is too large. Maximum file size is 3MB.";
                    // Save error message to conversation history if thread exists
                    if ($save_to_conversation) {
                        wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
                    }
                    // Clean up any previously uploaded files
                    foreach ($attachment_paths as $path) {
                        if (file_exists($path)) {
                            wp_delete_file($path);
                        }
                    }
                    wp_send_json_error(array('message' => $error_message));
                    return;
                }
                
                // Create unique filename and set upload path
                $unique_filename = wp_unique_filename($upload_dir['path'], $file['name']);
                
                // Use WordPress upload handling instead of move_uploaded_file
                $upload_file = array(
                    'name'     => $unique_filename,
                    'type'     => $file['type'],
                    'tmp_name' => $file['tmp_name'],
                    'error'    => $file['error'],
                    'size'     => $file['size']
                );

                $upload_overrides = array(
                    'test_form' => false,
                    'test_size' => true,
                    'test_upload' => true,
                );
                
                // Move uploaded file using WordPress functions
                $uploaded_file = wp_handle_upload($upload_file, $upload_overrides);
                
                if (!isset($uploaded_file['error']) && isset($uploaded_file['file'])) {
                    $attachment_paths[] = $uploaded_file['file'];
                } else {
                    $error_message = "Failed to upload file {$i}. Please try again.";
                    // Save error message to conversation history if thread exists
                    if ($save_to_conversation) {
                        wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
                    }
                    // Clean up any previously uploaded files
                    foreach ($attachment_paths as $path) {
                        if (file_exists($path)) {
                            wp_delete_file($path);
                        }
                    }
                    wp_send_json_error(array('message' => $error_message));
                    return;
                }
            }
        }
    }
    
    // Get admin email
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    
    // Prepare email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $name . ' <' . $email . '>',
        'Reply-To: ' . $email
    );
    
    // Prepare email content
    $subject = '[' . $site_name . '] New contact form submission';
    
    $body = '<p>You have received a new contact form submission:</p>';
    $body .= '<p><strong>Name:</strong> ' . esc_html($name) . '</p>';
    $body .= '<p><strong>Email:</strong> ' . esc_html($email) . '</p>';
    if (!empty($category)) {
        $body .= '<p><strong>Category:</strong> ' . esc_html($category) . '</p>';
    }
    $body .= '<p><strong>Message:</strong><br>' . nl2br(esc_html($message)) . '</p>';
    $body .= '<hr>';
    $body .= '<p>This message was sent from ' . esc_html($site_name) . '.</p>';
    
    // Add attachments to email if present
    $attachments = $attachment_paths;
    
    // Send email
    $sent = wp_mail($admin_email, $subject, $body, $headers, $attachments);
    
    // Clean up - delete the uploaded files
    foreach ($attachment_paths as $path) {
        if (file_exists($path)) {
            wp_delete_file($path);
        }
    }
    
    if ($sent) {
        // Increment rate limit counter
        $new_count = ($submission_count !== false) ? $submission_count + 1 : 1;
        set_transient($rate_limit_key, $new_count, $rate_limit_window);
        
        // Success message
        $success_message = 'Contact Form - Your message has been sent successfully. We will get back to you as soon as possible.';
        // Save success message to conversation history as an assistant response if thread exists
        if ($save_to_conversation) {
            wpiko_chatbot_save_message($user_id, $thread_id, 'assistant', $success_message, $email);
        }
        wp_send_json_success(array('message' => $success_message));
    } else {
        // Error message
        $error_message = 'Contact Form - Failed to send your message. Please try again later or contact us through another method.';
        // Save error message to conversation history if thread exists
        if ($save_to_conversation) {
            wpiko_chatbot_save_message($user_id, $thread_id, 'error', $error_message, $email);
        }
        wp_send_json_error(array('message' => $error_message));
    }
}
add_action('wp_ajax_wpiko_chatbot_contact_form', 'wpiko_chatbot_pro_contact_form_handler');
add_action('wp_ajax_nopriv_wpiko_chatbot_contact_form', 'wpiko_chatbot_pro_contact_form_handler');
