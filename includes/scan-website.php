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
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => 10,
        );

        $pages = get_posts($args);
        $results = array();

        foreach ($pages as $page) {
            $results[] = array(
                'ID' => $page->ID,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
            );
        }

        wp_send_json_success($results);
    }
}

// Function to fetch content from a given URL
function wpiko_chatbot_pro_fetch_url_content($url) {
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return false;
    }
    return wp_remote_retrieve_body($response);
}

// Function to convert HTML content to JSON
function wpiko_chatbot_pro_html_to_json($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);

    // Function to extract text and links from a node
    function extractContent($node) {
        $content = '';
        if ($node->nodeType === XML_TEXT_NODE) {
            $content .= trim($node->textContent);
        } elseif ($node->nodeName === 'a') {
            $href = $node->getAttribute('href');
            if ($href && !str_starts_with($href, '#')) {
                $content .= $node->textContent . ' [' . $href . ']';
            } else {
                $content .= $node->textContent;
            }
        } elseif ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $content .= extractContent($child);
            }
        }
        return $content;
    }

    // Try to find main content area, excluding specific elements
    $contentNodes = $xpath->query("
        //main[not(ancestor-or-self::header) and not(ancestor-or-self::footer) and not(ancestor-or-self::nav)] | 
        //article[not(ancestor-or-self::header) and not(ancestor-or-self::footer) and not(ancestor-or-self::nav)] | 
        //div[contains(@class, 'content') and not(ancestor-or-self::header) and not(ancestor-or-self::footer) and not(ancestor-or-self::nav)] | 
        //div[contains(@class, 'main') and not(ancestor-or-self::header) and not(ancestor-or-self::footer) and not(ancestor-or-self::nav)]
    ");
    
    $textContent = [];
    if ($contentNodes->length > 0) {
        foreach ($contentNodes as $contentNode) {
            $content = trim(extractContent($contentNode));
            if (!empty($content)) {
                $textContent[] = $content;
            }
        }
    } else {
        // Fallback: if no specific content area is found, extract from body but exclude common non-content areas
        $bodyContent = $xpath->query("
            //body//*[
                not(self::script) and 
                not(self::style) and 
                not(self::noscript) and 
                not(ancestor-or-self::header) and 
                not(ancestor-or-self::footer) and 
                not(ancestor-or-self::nav) and
                not(contains(@class, 'header')) and
                not(contains(@class, 'footer')) and
                not(contains(@class, 'menu')) and
                not(contains(@class, 'nav'))
            ]
        ");
        foreach ($bodyContent as $node) {
            $content = trim(extractContent($node));
            if (!empty($content)) {
                $textContent[] = $content;
            }
        }
    }

    // Convert to JSON
    return json_encode(['content' => implode("\n", $textContent)]);
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
                'model' => 'gpt-4o',
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

// Get file name from URL
function wpiko_chatbot_pro_get_filename_from_url($url) {   
    $parsed_url = wp_parse_url($url);    
    $path = trim($parsed_url['path'], '/');
    $segments = explode('/', $path);
    $last_segment = end($segments);
    
    if (empty($last_segment)) {
        return 'page_Homepage';
    }
    
    $filename = preg_replace('/[^a-zA-Z0-9]+/', '-', $last_segment);
    $filename = trim($filename, '-');
    $filename = ucfirst($filename);
    
    return 'page_' . $filename;
}

// Main function to process URL and return downloadable content
function wpiko_chatbot_pro_process_url_for_download($url) {
    $html = wpiko_chatbot_pro_fetch_url_content($url);
    if (!$html) {
        return false;
    }

    $json = wpiko_chatbot_pro_html_to_json($html);
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

// AJAX handler for processing URL
function wpiko_chatbot_pro_ajax_process_url() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Check for premium license
    if (!wpiko_chatbot_is_license_active()) {
        wp_send_json_error(['message' => 'This feature requires a premium license']);
    }

    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';

    // Step 1: Fetch URL content
    $html = wpiko_chatbot_pro_fetch_url_content($url);
    if (!$html) {
        wp_send_json_error(['message' => 'Failed to fetch URL content']);
    }

    // Step 2: Convert HTML to JSON
    $json = wpiko_chatbot_pro_html_to_json($html);
    if (!$json) {
        wp_send_json_error(['message' => 'Failed to convert HTML to JSON']);
    }

    // Step 3: Process with OpenAI
    $processed_content = wpiko_chatbot_process_with_wpiko($json);
    if (!$processed_content) {
        wp_send_json_error(['message' => 'Failed to process content with OpenAI']);
    }

    // Generate filename from URL
    $filename = wpiko_chatbot_pro_get_filename_from_url($url);

    // Enable or disable the download function
    $enable_qa_download = get_option('wpiko_chatbot_enable_qa_download', '0');
    
    wp_send_json_success([
        'content' => $processed_content,
        'filename' => $filename,
        'enable_download' => $enable_qa_download === '1'
    ]);
}
add_action('wp_ajax_wpiko_chatbot_process_url', 'wpiko_chatbot_pro_ajax_process_url');

// Function to handle the file upload to Assistant API
function wpiko_chatbot_pro_upload_qa_to_assistant() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Validate, unslash, and sanitize the input data
    $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
    $filename = isset($_POST['filename']) ? sanitize_file_name(wp_unslash($_POST['filename'])) : '';

    $file_id = wpiko_chatbot_upload_qa_file($filename, $content);
    if (!$file_id) {
        wp_send_json_error(['message' => 'Failed to upload file to Assistant API']);
    }

    wp_send_json_success([
        'file_id' => $file_id,
        'message' => 'File uploaded successfully to Assistant API',
        'cache_cleared' => true // Indicate that cache management was handled
    ]);
}
add_action('wp_ajax_wpiko_chatbot_upload_qa_to_assistant', 'wpiko_chatbot_pro_upload_qa_to_assistant');

// Function to upload the Q&A file
function wpiko_chatbot_upload_qa_file($filename, $content) {
    $assistant_id = get_option('wpiko_chatbot_assistant_id');
    $api_key = wpiko_chatbot_decrypt_api_key(get_option('wpiko_chatbot_api_key', ''));

    if (!$assistant_id || !$api_key) {
        wpiko_chatbot_log_error("Assistant ID or API key is missing.");
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

    // Upload the file
    $result = wpiko_chatbot_upload_file($file_data, $assistant_id);

    // Delete the temporary file when done
    $wp_filesystem->delete($temp_file_path);

    if ($result['success']) {
        $new_file_id = $result['file_id'];

        // Get the old file ID for this URL BEFORE saving the new one
        $old_file_id = get_option('wpiko_chatbot_qa_file_' . md5($filename), '');

        // Delete the old file if it exists
        if ($old_file_id) {
            // Delete from OpenAI API
            if (wpiko_chatbot_delete_file_from_wpiko($old_file_id)) {
                // Remove the old file from cache to ensure list displays correctly
                if (function_exists('wpiko_chatbot_remove_cached_file')) {
                    wpiko_chatbot_remove_cached_file($old_file_id);
                }
            }
        }

        // Save the new file ID AFTER cleaning up the old one
        update_option('wpiko_chatbot_qa_file_' . md5($filename), $new_file_id);

        return $new_file_id;
    } else {
        wpiko_chatbot_log_error('Failed to upload file to OpenAI: ' . $result['message']);
        return false;
    }
}