<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include WordPress Plugin API
require_once(ABSPATH . 'wp-includes/pluggable.php');

// AJAX handler for sending contact email
function wpiko_chatbot_pro_send_contact_email() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    // Check if POST variables exist and sanitize them properly
    $to = isset($_POST['to_email']) ? sanitize_email(wp_unslash($_POST['to_email'])) : '';
    $subject = isset($_POST['subject']) ? wp_strip_all_tags(wp_unslash($_POST['subject'])) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    
    // Remove any system tags that might have been added
    $message = preg_replace('/<userStyle>.*?<\/userStyle>/', '', $message);
    $message = wp_kses_post($message); // Sanitize the message content
    
    // Create the formatted message with the template after the message is prepared
    $site_name = get_bloginfo('name');
    $formatted_message = wpiko_chatbot_pro_get_email_template($message, $site_name);
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $attachments = array();

    // Handle file attachment
    if (!empty($_FILES['attachment'])) {
        $file = array_map('sanitize_text_field', $_FILES['attachment']);
        // Sanitize filename
        $file['name'] = sanitize_file_name($file['name']);
        $upload_dir = wp_upload_dir();
        
        // Check file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Invalid file type. Only JPG, PNG and GIF images are allowed.');
            return;
        }
        
        // Check file size (5MB limit)
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error('File size too large. Maximum size is 5MB.');
            return;
        }
        
        // Use WordPress upload handling instead of move_uploaded_file
        $upload_overrides = array(
            'test_form' => false,
            'test_size' => true,
            'test_upload' => true,
        );
        
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (!isset($uploaded_file['error']) && isset($uploaded_file['file'])) {
            $attachments[] = $uploaded_file['file'];
        } else {
            $error_message = isset($uploaded_file['error']) ? $uploaded_file['error'] : 'Failed to upload file';
            wp_send_json_error($error_message);
            return;
        }
    }
    
    // Get admin email as from address
    $admin_email = get_option('admin_email');
    $headers[] = 'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>';
    
    if (empty($to)) {
        wp_send_json_error('Email address is required');
        return;
    }

    if (empty($subject)) {
        wp_send_json_error('Subject is required');
        return;
    }

    if (empty($message)) {
        wp_send_json_error('Message is required');
        return;
    }

    $result = wp_mail($to, $subject, $formatted_message, $headers, $attachments);
    
    // Delete the attachment after sending the email
    if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                wp_delete_file($attachment);
            }
        }
    }
    
    if ($result) {
        wp_send_json_success('Email sent successfully');
    } else {
        $error = error_get_last();
        $error_message = $error ? $error['message'] : 'Failed to send email';
        wp_send_json_error($error_message);
    }
}
add_action('wp_ajax_wpiko_chatbot_send_contact_email', 'wpiko_chatbot_pro_send_contact_email');

// AJAX handler for enhancing text with OpenAI
function wpiko_chatbot_pro_enhance_text() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    // Check if text exists in POST and sanitize it
    $text = isset($_POST['text']) ? sanitize_textarea_field(wp_unslash($_POST['text'])) : '';
    
    if (empty($text)) {
        wp_send_json_error('Text is required');
        return;
    }

    $tone = isset($_POST['tone']) ? sanitize_text_field(wp_unslash($_POST['tone'])) : 'professional';

    // Get OpenAI API key from options
    $encrypted_api_key = get_option('wpiko_chatbot_api_key');
    if (empty($encrypted_api_key)) {
        wpiko_chatbot_log('OpenAI API key is not configured', 'error');
        wp_send_json_error('OpenAI API key is not configured');
        return;
    }
    
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    if (empty($api_key)) {
        wpiko_chatbot_log('Failed to decrypt OpenAI API key', 'error');
        wp_send_json_error('Failed to decrypt API key');
        return;
    }

    if (!function_exists('wpiko_chatbot_openai_feature_text_request')) {
        wpiko_chatbot_log('The base plugin does not provide the utility Responses API helper', 'error');
        wp_send_json_error('WPiko Chatbot must be updated before this feature can use AI.');
        return;
    }

    $response = wpiko_chatbot_openai_feature_text_request(
        $api_key,
        'contact_enhancement',
        'Enhance the supplied text so it sounds naturally ' . $tone . ' while preserving its meaning. Do not make it overly formal or informal unless requested. Return only the enhanced text.',
        $text
    );

    if (is_wp_error($response)) {
        wpiko_chatbot_log('Contact text enhancement failed: ' . $response->get_error_message(), 'error');
        wp_send_json_error($response->get_error_message());
        return;
    }

    if (!empty($response['wpiko_output_text'])) {
        wp_send_json_success($response['wpiko_output_text']);
    } else {
        wp_send_json_error('Failed to enhance text');
    }
}
add_action('wp_ajax_wpiko_chatbot_enhance_text', 'wpiko_chatbot_pro_enhance_text');


// Email Template
function wpiko_chatbot_pro_get_email_template($message, $site_name) {

    // Convert line breaks to paragraphs and ensure proper spacing
    $formatted_content = wpiko_chatbot_pro_format_message_content($message);
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                color: #333333;
                line-height: 1.6;
            }
            .email-header {
                background-color: #f8f9fa;
                padding: 30px 40px;
                border-radius: 8px 8px 0 0;
                border-bottom: 3px solid #e9ecef;
            }
            .email-header img {
                max-height: 50px;
                width: auto;
            }
            .email-content {
                background-color: #ffffff;
                font-size: 14px;
                padding: 40px;
                border-radius: 0 0 8px 8px;
            }
            .email-footer {
                text-align: center;
                padding: 20px;
                font-size: 14px;
                color: #6c757d;
            }
            @media only screen and (max-width: 600px) {
                .email-header, .email-content {
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body style="margin: 0; padding: 20px; background-color: #f0f2f5;">
        <div class="email-container">
            <div class="email-header">
                <h2 style="margin: 0; text-align: center; color: #333;">' . esc_html($site_name) . '</h2>
            </div>
            <div class="email-content">
                ' . $formatted_content . '
            </div>
            <div class="email-footer">
                <p>This email was sent from ' . esc_html($site_name) . '</p>
            </div>
        </div>
    </body>
    </html>';
}

function wpiko_chatbot_pro_format_message_content($message) {
    // Clean up the message
    $message = wp_kses_post($message);
    
    // Normalize line endings
    $message = str_replace(["\r\n", "\r"], "\n", $message);
    
    // Ensure consistent paragraph breaks by converting multiple consecutive line breaks to double line breaks
    $message = preg_replace("/\n{2,}/", "\n\n", $message);
    
    // Split into paragraphs and process each one
    $paragraphs = explode("\n\n", $message);
    $formatted_paragraphs = array_map(function($paragraph) {
        $paragraph = trim($paragraph);
        if (!empty($paragraph)) {
            // Convert single line breaks to <br> within paragraphs
            $paragraph = str_replace("\n", "<br>", $paragraph);
            // Use table for consistent spacing in email clients
            return '
            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 20px;">
                <tr>
                    <td>
                        <p style="margin: 0; padding: 0;">' . $paragraph . '</p>
                    </td>
                </tr>
                <tr>
                    <td height="20"></td>
                </tr>
            </table>';
        }
        return '';
    }, $paragraphs);
    
    // Filter out empty paragraphs and join
    $formatted_content = implode("\n", array_filter($formatted_paragraphs));
    
    // If no paragraphs were created, wrap the entire content in a paragraph
    if (empty($formatted_content)) {
        $formatted_content = '
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    <p style="margin: 0; padding: 0;">' . str_replace("\n", "<br>", $message) . '</p>
                </td>
            </tr>
        </table>';
    }
    
    return $formatted_content;
}
