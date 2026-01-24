<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Function to search pages
if (!function_exists('wpiko_chatbot_pro_search_pages')) {
    add_action('wp_ajax_wpiko_chatbot_search_pages', 'wpiko_chatbot_pro_search_pages');

    function wpiko_chatbot_pro_search_pages() {
        check_ajax_referer('wpiko_chatbot_nonce', 'security');
        
        // Check for premium license
        if (!wpiko_chatbot_is_license_active()) {
            wp_send_json_error(['message' => 'This feature requires a premium license']);
        }

        // Validate, unslash and sanitize the query
        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $args = array(
            'post_type' => 'page',
            'post_status' => array('publish', 'private', 'draft', 'pending'),
            's' => $query,
            'posts_per_page' => 10,
        );

        $pages = get_posts($args);
        $results = array();

        foreach ($pages as $page) {
            // Add status indicator for non-published pages
            $status_label = '';
            if ($page->post_status !== 'publish') {
                $status_label = ' [' . ucfirst($page->post_status) . ']';
            } elseif (!empty($page->post_password)) {
                $status_label = ' [Password Protected]';
            }
            
            $results[] = array(
                'ID' => $page->ID,
                'title' => $page->post_title . $status_label,
                'status' => $page->post_status,
            );
        }

        wp_send_json_success($results);
    }
}

/**
 * Fetch content directly from WordPress database by post ID
 * This bypasses any access restrictions (password protection, membership, etc.)
 * 
 * @param int $post_id The WordPress post/page ID
 * @return array|false Array with title, content, and url or false on failure
 */
function wpiko_chatbot_pro_fetch_page_content($post_id) {
    $post = get_post($post_id);
    
    if (!$post || $post->post_type !== 'page') {
        return false;
    }
    
    // Get the raw content
    $content = $post->post_content;
    
    // Render Gutenberg blocks if present
    if (has_blocks($content)) {
        $content = do_blocks($content);
    }
    
    // Process shortcodes
    $content = do_shortcode($content);
    
    // Convert to plain text while preserving structure
    $content = wpiko_chatbot_pro_html_to_text($content);
    
    return array(
        'title' => $post->post_title,
        'content' => $content,
        'url' => get_permalink($post_id),
        'id' => $post_id
    );
}

/**
 * Convert HTML content to clean text while preserving links
 * 
 * @param string $html The HTML content
 * @return string Clean text with preserved link references
 */
function wpiko_chatbot_pro_html_to_text($html) {
    // First, handle links - convert <a href="url">text</a> to text [url]
    $html = preg_replace_callback(
        '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i',
        function($matches) {
            $url = $matches[1];
            $text = $matches[2];
            // Skip anchor links
            if (strpos($url, '#') === 0) {
                return $text;
            }
            return $text . ' [' . $url . ']';
        },
        $html
    );
    
    // Convert headers to text with newlines
    $html = preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', "\n\n$1\n\n", $html);
    
    // Convert paragraphs and divs to have proper spacing
    $html = preg_replace('/<\/(p|div)>/i', "\n\n", $html);
    
    // Convert line breaks
    $html = preg_replace('/<br[^>]*>/i', "\n", $html);
    
    // Convert list items
    $html = preg_replace('/<li[^>]*>/i', "\n• ", $html);
    
    // Remove all remaining HTML tags
    $text = wp_strip_all_tags($html);
    
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // Clean up whitespace - multiple spaces to single space
    $text = preg_replace('/[ \t]+/', ' ', $text);
    
    // Clean up multiple newlines to max 2
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    
    // Trim each line
    $lines = explode("\n", $text);
    $lines = array_map('trim', $lines);
    $text = implode("\n", $lines);
    
    // Remove empty lines at start and end
    $text = trim($text);
    
    return $text;
}

/**
 * Convert page data to JSON format for OpenAI processing
 * 
 * @param array $page_data Array containing title and content
 * @return string JSON encoded content
 */
function wpiko_chatbot_pro_page_to_json($page_data) {
    $structured_content = "Page Title: " . $page_data['title'] . "\n\n";
    $structured_content .= "Page URL: " . $page_data['url'] . "\n\n";
    $structured_content .= "Content:\n" . $page_data['content'];
    
    return json_encode(['content' => $structured_content]);
}

// Function to send JSON to OpenAI for processing
function wpiko_chatbot_process_with_wpiko($json) {
    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    if (empty($api_key)) {
        wpiko_chatbot_log('OpenAI API key is missing', 'error');
        return false;
    }

    // Decode the JSON to check its size
    $data = json_decode($json, true);
    if (!$data || !isset($data['content'])) {
        wpiko_chatbot_log('Invalid JSON structure for processing', 'error');
        return false;
    }
    
    // Check if content needs to be chunked (approximately 15,000 characters is safe for GPT-4o)
    $content_length = strlen($data['content']);
    if ($content_length > 15000) {
        return wpiko_chatbot_process_large_content($data, $api_key);
    }

    // Regular processing for smaller content
    $prompt = "You will receive a JSON structure representing the content of a web page. Your task is to analyze this content and create a comprehensive Q&A format based on the information provided. Follow these guidelines:

1. Preserve the hierarchical structure of the content where relevant.
2. Create questions that accurately reflect the main points and subpoints of the content.
3. Provide detailed answers that incorporate the information from the content.
4. Maintain any links present in the original content by including them in the answers where appropriate.
5. Organize the Q&A pairs logically, following the structure of the original content.
6. Use bullet points or short paragraphs in answers for clarity when appropriate.
7. Ensure that each Q&A pair is unique and not repeated.

Please return the Q&A pairs in the following format:

Q: [Question derived from the content]
A: [Detailed answer, including relevant links in the format [link text](URL)]

[Leave a blank line between each Q&A pair]

Based on the following JSON structure, generate a comprehensive set of questions and answers:

$json";

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'gpt-4.1',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant that creates Q&A pairs from structured web content.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.7,
        ]),
        'timeout' => 90,
    ]);

    if (is_wp_error($response)) {
        wpiko_chatbot_log('OpenAI API request failed: ' . $response->get_error_message(), 'error');
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($body['choices'][0]['message']['content'])) {
        wpiko_chatbot_log('Unexpected response from OpenAI API: ' . wp_json_encode($body), 'error');
        return false;
    }

    return trim($body['choices'][0]['message']['content']);
}

/**
 * Process large content by breaking it into chunks
 * 
 * @param array $data The decoded JSON content
 * @param string $api_key The OpenAI API key
 * @return string The combined processed content
 */
function wpiko_chatbot_process_large_content($data, $api_key) {
    // Calculate number of chunks needed (aiming for ~10,000 chars per chunk for safe processing)
    $chunk_size = 10000;
    $content = $data['content'];
    $total_length = strlen($content);
    $num_chunks = ceil($total_length / $chunk_size);
    
    wpiko_chatbot_log("Content size: {$total_length} characters, splitting into {$num_chunks} chunks", 'info');
    
    $results = [];
    
    // Process each chunk
    for ($i = 0; $i < $num_chunks; $i++) {
        // Extract chunk with some overlap to avoid cutting sentences
        $start = max(0, $i * $chunk_size - 200);
        $length = min($chunk_size + 400, $total_length - $start);
        $chunk = substr($content, $start, $length);
        
        // If not the first chunk, find the first paragraph or sentence break to start cleanly
        if ($i > 0) {
            // Look for paragraph break
            $clean_start = strpos($chunk, "\n\n");
            if ($clean_start !== false) {
                $chunk = substr($chunk, $clean_start + 2);
            } else {
                // Look for sentence break (period followed by space)
                $clean_start = strpos($chunk, '. ');
                if ($clean_start !== false) {
                    $chunk = substr($chunk, $clean_start + 2);
                }
            }
        }
        
        // Create context for this chunk
        $chunk_context = "This is part " . ($i + 1) . " of " . $num_chunks . " from a larger document.";
        
        // Create JSON for this chunk
        $chunk_json = json_encode(['content' => $chunk]);
        
        // Process this chunk
        $chunk_prompt = "You will receive a portion of a web page content in JSON format. {$chunk_context} Your task is to analyze this content and create Q&A pairs from it.

Guidelines:
1. Focus only on creating Q&A pairs from the content provided.
2. Ensure questions accurately reflect the main points.
3. Provide detailed answers that incorporate the information.
4. Include any links present in the content.
5. Format as Q&A pairs with a blank line between each pair.
6. IMPORTANT: Follow this exact format for each Q&A pair:
   Q: [Your question here]
   A: [Your answer here]

   (single blank line between pairs)

Content:
$chunk_json";

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a specialist in creating Q&A content from web page segments. Always maintain consistent formatting between question and answer pairs.'],
                    ['role' => 'user', 'content' => $chunk_prompt]
                ],
                'max_tokens' => 4000,
                'temperature' => 0.7,
            ]),
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            wpiko_chatbot_log('Chunk ' . ($i + 1) . ' processing failed: ' . $response->get_error_message(), 'error');
            continue;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['choices'][0]['message']['content'])) {
            wpiko_chatbot_log('Unexpected response from OpenAI API for chunk ' . ($i + 1), 'error');
            continue;
        }

        $results[] = trim($body['choices'][0]['message']['content']);
    }
    
    // If no chunks were processed successfully, return false
    if (empty($results)) {
        return false;
    }
    
    // Combine all results
    $combined_content = implode("\n\n", $results);
    
    // Post-processing: Complete reformat of the content for consistent formatting
    // First, normalize basic formatting
    $combined_content = preg_replace('/Q:\s+/', 'Q: ', $combined_content);
    $combined_content = preg_replace('/A:\s+/', 'A: ', $combined_content);
    
    // Split by Q: to get individual QA pairs
    $pattern = '/(?=Q: )/i';
    $pairs = preg_split($pattern, $combined_content, -1, PREG_SPLIT_NO_EMPTY);
    
    $output = '';
    foreach ($pairs as $pair) {
        $pair = trim($pair);
        if (empty($pair)) continue;
        
        // Skip pairs that don't start with Q:
        if (stripos($pair, 'Q: ') !== 0 && stripos($pair, 'Q:') !== 0) continue;
        
        // Find where the answer part starts
        $a_pos = stripos($pair, 'A:');
        
        if ($a_pos !== false) {
            // We have both Q and A
            $question = trim(substr($pair, 0, $a_pos));
            $answer = trim(substr($pair, $a_pos));
            
            // Add to output with consistent formatting - exact format requested
            $output .= $question . "\n" . $answer . "\n\n";
        } else {
            // Just Q with no A (shouldn't happen, but just in case)
            $output .= $pair . "\n\n";
        }
    }
    
    // Remove trailing newlines
    $output = rtrim($output);
    
    return $output;
}

/**
 * Generate filename from page title
 * 
 * @param string $title The page title
 * @return string Sanitized filename
 */
function wpiko_chatbot_pro_get_filename_from_title($title) {
    if (empty($title)) {
        return 'page_Untitled';
    }
    
    // Sanitize the title for use as filename
    $filename = preg_replace('/[^a-zA-Z0-9]+/', '-', $title);
    $filename = trim($filename, '-');
    $filename = ucfirst($filename);
    
    // Limit length to avoid overly long filenames
    if (strlen($filename) > 50) {
        $filename = substr($filename, 0, 50);
        $filename = rtrim($filename, '-');
    }
    
    return 'page_' . $filename;
}

/**
 * Process a page by ID and return Q&A content
 * 
 * @param int $page_id The WordPress page ID
 * @return string|false The processed Q&A content or false on failure
 */
function wpiko_chatbot_pro_process_page_for_download($page_id) {
    $page_data = wpiko_chatbot_pro_fetch_page_content($page_id);
    if (!$page_data) {
        return false;
    }

    $json = wpiko_chatbot_pro_page_to_json($page_data);
    $processed_content = wpiko_chatbot_process_with_wpiko($json);
    if (!$processed_content) {
        return false;
    }

    return $processed_content;
}

// AJAX handler save downloadable content
function wpiko_chatbot_pro_save_qa_download_setting() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Sanitize and validate the input
    $enable_download = isset($_POST['enable_download']) ? sanitize_text_field(wp_unslash($_POST['enable_download'])) : 'false';
    $enable_download = ($enable_download === 'true') ? '1' : '0';
    
    // Save the option
    $updated = update_option('wpiko_chatbot_enable_qa_download', $enable_download);
    
    if ($updated) {
        wp_send_json_success(['message' => 'Setting saved successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to save setting']);
    }
}
add_action('wp_ajax_wpiko_chatbot_save_qa_download_setting', 'wpiko_chatbot_pro_save_qa_download_setting');

// AJAX handler for processing page by ID
function wpiko_chatbot_pro_ajax_process_page() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Check for premium license
    if (!wpiko_chatbot_is_license_active()) {
        wp_send_json_error(['message' => 'This feature requires a premium license']);
    }

    $page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;
    
    if (!$page_id) {
        wp_send_json_error(['message' => 'Invalid page ID']);
    }

    // Step 1: Fetch page content directly from database
    $page_data = wpiko_chatbot_pro_fetch_page_content($page_id);
    if (!$page_data) {
        wp_send_json_error(['message' => 'Failed to fetch page content. Please ensure the page exists.']);
    }

    // Step 2: Convert page data to JSON
    $json = wpiko_chatbot_pro_page_to_json($page_data);
    if (!$json) {
        wp_send_json_error(['message' => 'Failed to prepare content for processing']);
    }

    // Step 3: Process with OpenAI
    $processed_content = wpiko_chatbot_process_with_wpiko($json);
    if (!$processed_content) {
        wp_send_json_error(['message' => 'Failed to process content with OpenAI']);
    }

    // Generate filename from page title
    $filename = wpiko_chatbot_pro_get_filename_from_title($page_data['title']);

    // Enable or disable the download function
    $enable_qa_download = get_option('wpiko_chatbot_enable_qa_download', '0');
    
    wp_send_json_success([
        'content' => $processed_content,
        'filename' => $filename,
        'enable_download' => $enable_qa_download === '1'
    ]);
}
add_action('wp_ajax_wpiko_chatbot_process_page', 'wpiko_chatbot_pro_ajax_process_page');

// Function to handle the file upload to Responses API
function wpiko_chatbot_pro_upload_qa_to_assistant() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Validate, unslash, and sanitize the input data
    $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
    $filename = isset($_POST['filename']) ? sanitize_file_name(wp_unslash($_POST['filename'])) : '';

    // Upload to Responses API
    $file_id = wpiko_chatbot_upload_qa_file_to_responses($filename, $content);
    $success_message = 'File uploaded successfully to the AI Assistant';
    
    if (!$file_id) {
        wp_send_json_error(['message' => 'Failed to upload file to the AI Assistant']);
    }

    wp_send_json_success([
        'file_id' => $file_id,
        'message' => $success_message,
        'cache_cleared' => true // Indicate that cache management was handled
    ]);
}
add_action('wp_ajax_wpiko_chatbot_upload_qa_to_assistant', 'wpiko_chatbot_pro_upload_qa_to_assistant');

// Function to upload the Q&A file to Responses API
function wpiko_chatbot_upload_qa_file_to_responses($filename, $content) {
    $api_key = wpiko_chatbot_decrypt_api_key(get_option('wpiko_chatbot_api_key', ''));

    if (!$api_key) {
        wpiko_chatbot_log_error("API key is missing for Responses API upload.");
        return false;
    }

    // Use WordPress Filesystem API
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    
    // Create a temporary file using wp_tempnam()
    $temp_file_path = wp_tempnam($filename);
    
    // Write content to the file using WP_Filesystem
    $wp_filesystem->put_contents($temp_file_path, $content);

    // Prepare file data for upload
    $file_data = [
        'tmp_name' => $temp_file_path,
        'name' => $filename . '.txt',
        'type' => 'text/plain',
    ];

    // Upload the file to Responses API vector store
    $result = wpiko_chatbot_upload_file_to_responses($file_data);

    // Delete the temporary file when done
    $wp_filesystem->delete($temp_file_path);

    if ($result['success']) {
        $new_file_id = $result['file_id'];

        // Get the old file ID for this URL BEFORE saving the new one
        $old_file_id = get_option('wpiko_chatbot_responses_qa_file_' . md5($filename), '');

        // Delete the old file if it exists
        if ($old_file_id) {
            wpiko_chatbot_delete_responses_file($old_file_id);
        }

        // Store the new file ID for future reference
        update_option('wpiko_chatbot_responses_qa_file_' . md5($filename), $new_file_id);

        // Clear the file cache to ensure fresh data is loaded next time
        wpiko_chatbot_clear_file_cache();

        return $new_file_id;
    } else {
        wpiko_chatbot_log_error("Failed to upload Q&A file to Responses API: " . $result['message']);
        return false;
    }
}
