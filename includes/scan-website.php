<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Docs, posts and other article types are uploaded as Markdown, not Q&A.
require_once __DIR__ . '/scan-markdown.php';
// Optional automatic re-sync of scanned posts and docs after they are edited.
require_once __DIR__ . '/scan-auto-sync.php';

/**
 * Content types Scan Website can turn into Q&A.
 *
 * Every public type that has an editor in wp-admin: pages, posts, and the types
 * registered by documentation or knowledge-base plugins. No plugin-specific
 * code is needed, because those plugins store articles as normal posts.
 * WooCommerce products have their own integration, and media files belong in
 * File Management, so both are left out.
 *
 * @return string[] Post type names.
 */
function wpiko_chatbot_pro_scan_post_types() {
    static $types = null;
    if ($types !== null) {
        return $types;
    }

    $excluded = array(
        'attachment',
        'product',
        'product_variation',
        // Page builder and theme template types: layout, not content.
        'elementor_library',
        'e-landing-page',
        'et_pb_layout',
        'fl-builder-template',
        'oxy_user_library',
        'ct_template',
        'brizy_template',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_navigation',
    );

    $types = array();
    foreach (get_post_types(array('public' => true), 'objects') as $name => $object) {
        if (in_array($name, $excluded, true) || empty($object->show_ui)) {
            continue;
        }
        // The text must live in the editor content to be read.
        if (!post_type_supports($name, 'editor') && $name !== 'page') {
            continue;
        }
        $types[] = $name;
    }

    /**
     * Filter the content types Scan Website lists and processes.
     *
     * @param string[] $types Post type names.
     */
    $types = array_values(array_unique(array_filter((array) apply_filters('wpiko_chatbot_pro_scan_post_types', $types), 'post_type_exists')));
    if (!$types) {
        $types = array('page');
    }
    // Pages first, then docs and other custom types, then blog posts.
    usort($types, function ($a, $b) {
        $rank = function ($type) {
            return $type === 'page' ? 0 : ($type === 'post' ? 2 : 1);
        };
        return $rank($a) - $rank($b);
    });
    return $types;
}

/**
 * Items Scan Website never lists.
 *
 * WooCommerce's Cart, Checkout and My account pages only show each visitor's
 * own cart, form or account, so they have no text to learn from. The Shop page
 * lists products, which the WooCommerce integration already keeps in sync.
 *
 * @return int[] Post IDs.
 */
function wpiko_chatbot_pro_scan_excluded_ids() {
    $ids = array();
    foreach (array('cart', 'checkout', 'myaccount', 'shop') as $woo_page) {
        $id = function_exists('wc_get_page_id')
            ? (int) wc_get_page_id($woo_page)
            : (int) get_option('woocommerce_' . $woo_page . '_page_id');
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    /**
     * Filter the items Scan Website hides, for example other plugins'
     * account or checkout pages.
     *
     * @param int[] $ids Post IDs. Default: WooCommerce Cart, Checkout, My account and Shop pages.
     */
    $ids = (array) apply_filters('wpiko_chatbot_pro_scan_excluded_ids', $ids);
    return array_values(array_unique(array_filter(array_map('absint', $ids))));
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
    
    if (!$post || !in_array($post->post_type, wpiko_chatbot_pro_scan_post_types(), true)) {
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
    
    if (!function_exists('wpiko_chatbot_openai_feature_text_request')) {
        wpiko_chatbot_log('The base plugin does not provide the utility Responses API helper', 'error');
        return false;
    }

    // Keep normal pages in one request. Chunk only unusually large pages to limit
    // request duration and allow enough output space for comprehensive Q&A content.
    $content_length = strlen($data['content']);
    $chunk_threshold = max(1000, (int) apply_filters('wpiko_chatbot_qa_chunk_threshold', 60000));
    if ($content_length > $chunk_threshold) {
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

    $response = wpiko_chatbot_openai_feature_text_request(
        $api_key,
        'qa_generation',
        'You create accurate, comprehensive Q&A pairs from structured web content. Preserve source links and follow the requested output format exactly.',
        $prompt
    );

    if (is_wp_error($response)) {
        wpiko_chatbot_log('Q&A generation failed: ' . $response->get_error_message(), 'error');
        return false;
    }

    return trim($response['wpiko_output_text']);
}

/**
 * Process large content by breaking it into chunks
 * 
 * @param array $data The decoded JSON content
 * @param string $api_key The OpenAI API key
 * @return string The combined processed content
 */
function wpiko_chatbot_process_large_content($data, $api_key) {
    // The GPT-6 context window can handle much larger inputs than the previous
    // implementation. This conservative operational limit avoids excessive calls.
    $chunk_size = max(1000, (int) apply_filters('wpiko_chatbot_qa_chunk_size', 45000));
    $chunk_overlap = max(0, (int) apply_filters('wpiko_chatbot_qa_chunk_overlap', 500));
    $chunk_overlap = min($chunk_overlap, (int) floor($chunk_size / 2));
    $content = $data['content'];
    $total_length = strlen($content);
    $num_chunks = ceil($total_length / $chunk_size);
    
    wpiko_chatbot_log("Content size: {$total_length} characters, splitting into {$num_chunks} chunks", 'info');
    
    $results = [];
    
    // Process each chunk
    for ($i = 0; $i < $num_chunks; $i++) {
        // Extract chunk with some overlap to avoid cutting sentences
        $start = max(0, $i * $chunk_size - $chunk_overlap);
        $length = min($chunk_size + ($chunk_overlap * 2), $total_length - $start);
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

        $response = wpiko_chatbot_openai_feature_text_request(
            $api_key,
            'qa_generation',
            'You create accurate Q&A content from web page segments. Preserve source links and maintain identical Q: and A: formatting across every segment.',
            $chunk_prompt
        );

        if (is_wp_error($response)) {
            wpiko_chatbot_log(
                'Chunk ' . ($i + 1) . ' of ' . $num_chunks . ' failed; discarding the incomplete Q&A result: ' . $response->get_error_message(),
                'error'
            );
            return false;
        }

        $results[] = trim($response['wpiko_output_text']);
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

    $enable_qa_download = get_option('wpiko_chatbot_enable_qa_download', '0');

    // Docs, posts and other articles: clean Markdown, no AI request.
    if (wpiko_chatbot_pro_scan_mode($page_id) === 'markdown') {
        $markdown = wpiko_chatbot_pro_build_markdown($page_id);
        if (!$markdown) {
            wp_send_json_error(['message' => 'Failed to fetch the content. Please ensure it exists.']);
        }
        if ($markdown['content'] === '') {
            wp_send_json_error(['message' => 'This item has no text to add.']);
        }
        wp_send_json_success([
            'content' => $markdown['content'],
            'filename' => wpiko_chatbot_pro_get_filename_from_title($markdown['title']) . '-' . $page_id,
            'page_id' => $page_id,
            'mode' => 'markdown',
            'extension' => 'md',
            'enable_download' => $enable_qa_download === '1'
        ]);
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

    // Generate filename from page title. The page ID keeps it unique when
    // two pages share a title (older scans used the title only).
    $filename = wpiko_chatbot_pro_get_filename_from_title($page_data['title']) . '-' . $page_id;

    // Keep the generated Q&A on the server for the upload step, so it is
    // uploaded exactly as written instead of round-tripping through the browser.
    set_transient(wpiko_chatbot_pro_scan_qa_key($page_id), array(
        'content' => $processed_content,
        'filename' => $filename,
    ), HOUR_IN_SECONDS);

    wp_send_json_success([
        'content' => $processed_content,
        'filename' => $filename,
        'page_id' => $page_id,
        'mode' => 'qa',
        'extension' => 'txt',
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
    // Plain-text clean-up only: sanitize_textarea_field() would remove
    // percent-encoded characters from links (e.g. non-Latin URLs) and any
    // text that looks like an HTML tag. The file is never rendered as HTML.
    $content = isset($_POST['content']) ? wpiko_chatbot_pro_clean_scan_text(wp_unslash($_POST['content'])) : '';
    $filename = isset($_POST['filename']) ? sanitize_file_name(wp_unslash($_POST['filename'])) : '';
    $page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;
    $mode = $page_id ? wpiko_chatbot_pro_scan_mode($page_id) : 'qa';

    if ($mode === 'markdown') {
        // Rebuilt here rather than taken from the browser: text sanitising
        // would strip code samples and encoded characters from the Markdown.
        $markdown = wpiko_chatbot_pro_build_markdown($page_id);
        if (!$markdown || $markdown['content'] === '') {
            wp_send_json_error(['message' => 'This item has no text to add.']);
        }
        $content = $markdown['content'];
        $filename = wpiko_chatbot_pro_get_filename_from_title($markdown['title']) . '-' . $page_id;
    } elseif ($page_id) {
        // Q&A generated by the process step for this user and page.
        $stored = get_transient(wpiko_chatbot_pro_scan_qa_key($page_id));
        if (is_array($stored) && !empty($stored['content']) && !empty($stored['filename'])) {
            $content = (string) $stored['content'];
            $filename = sanitize_file_name($stored['filename']);
        }
    }

    if ($content === '' || $filename === '') {
        wp_send_json_error(['message' => 'Nothing to upload.']);
    }

    $stored = wpiko_chatbot_pro_store_scan_file($page_id, $filename, $content, $mode);
    if (empty($stored['success'])) {
        wp_send_json_error(['message' => 'Failed to upload file to the AI Assistant']);
    }
    if ($page_id) {
        delete_transient(wpiko_chatbot_pro_scan_qa_key($page_id));
    }

    $success_message = 'File uploaded successfully to the AI Assistant';
    if (!empty($stored['quick_learn_removed'])) {
        $success_message .= '. This page was also removed from "Quick learn from your pages" so it is not stored twice.';
    }

    wp_send_json_success([
        'file_id' => $stored['file_id'],
        'message' => $success_message,
        'cache_cleared' => true // Indicate that cache management was handled
    ]);
}
add_action('wp_ajax_wpiko_chatbot_upload_qa_to_assistant', 'wpiko_chatbot_pro_upload_qa_to_assistant');

/**
 * Upload a scanned item's file and record it.
 *
 * Shared by manual scans and automatic sync: replaces the item's previous
 * file (also when it had another name), remembers which item the file covers
 * and takes the item out of the free "Quick learn" file.
 *
 * @param int    $page_id  Post ID (0 for a file that is not tied to an item).
 * @param string $filename File name without extension.
 * @param string $content  File content.
 * @param string $mode     'qa' or 'markdown'.
 * @return array{success: bool, file_id?: string, quick_learn_removed?: bool}
 */
function wpiko_chatbot_pro_store_scan_file($page_id, $filename, $content, $mode) {
    $page_id = absint($page_id);
    $file_id = wpiko_chatbot_upload_qa_file_to_responses($filename, $content, $mode);
    if (!$file_id) {
        return array('success' => false);
    }

    $quick_learn_removed = false;
    if ($page_id) {
        // Rescan: if the previous file for this page had another name (the page
        // was renamed, or it was scanned by an older version), remove it so the
        // page is not stored twice. Skip it if another page still uses that file.
        $previous_scans = get_option('wpiko_chatbot_pro_scanned_pages', array());
        $previous = (is_array($previous_scans) && isset($previous_scans[$page_id])) ? $previous_scans[$page_id] : null;
        if ($previous && !empty($previous['file_id']) && $previous['file_id'] !== $file_id) {
            $shared = false;
            foreach ($previous_scans as $other_page_id => $other_entry) {
                if ((int) $other_page_id !== $page_id && isset($other_entry['file_id']) && $other_entry['file_id'] === $previous['file_id']) {
                    $shared = true;
                    break;
                }
            }
            if (!$shared) {
                $removed = wpiko_chatbot_delete_responses_file($previous['file_id']);
                if (is_array($removed) && empty($removed['success'])) {
                    wpiko_chatbot_log('Rescan of page ' . $page_id . ' could not remove its previous scan file: ' . (isset($removed['message']) ? $removed['message'] : 'unknown error'), 'warning');
                }
                if (!empty($previous['filename']) && get_option('wpiko_chatbot_responses_qa_file_' . md5($previous['filename']), '') === $previous['file_id']) {
                    delete_option('wpiko_chatbot_responses_qa_file_' . md5($previous['filename']));
                }
            }
        }

        // Remember which page this file covers, how it was made and what it held.
        wpiko_chatbot_pro_remember_scanned_page($page_id, $filename, $file_id, $mode, md5($content));

        // The page is now in the knowledge base: take it out of the free
        // "Quick learn from your pages" file so it is not stored twice.
        if (function_exists('wpiko_chatbot_site_knowledge_remove_pages')) {
            $cleanup = wpiko_chatbot_site_knowledge_remove_pages(array($page_id));
            if (is_array($cleanup) && !empty($cleanup['success'])) {
                $quick_learn_removed = true;
            } elseif (is_array($cleanup)) {
                wpiko_chatbot_log('Could not remove scanned page ' . $page_id . ' from quick learn: ' . (isset($cleanup['message']) ? $cleanup['message'] : 'unknown error'), 'warning');
            }
        }
    }

    return array('success' => true, 'file_id' => $file_id, 'quick_learn_removed' => $quick_learn_removed);
}

/**
 * Transient key holding the Q&A generated for a page, per admin user.
 *
 * @param int $page_id Page ID.
 * @return string
 */
function wpiko_chatbot_pro_scan_qa_key($page_id) {
    return 'wpiko_chatbot_pro_scan_qa_' . get_current_user_id() . '_' . absint($page_id);
}

/**
 * Clean plain text for a knowledge file without changing its wording.
 *
 * Keeps links (including percent-encoded ones), code and angle brackets;
 * removes invalid UTF-8 and control characters and normalises line endings.
 *
 * @param string $text Text.
 * @return string
 */
function wpiko_chatbot_pro_clean_scan_text($text) {
    $text = wp_check_invalid_utf8((string) $text, true);
    $text = str_replace(array("\r\n", "\r"), "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    return trim($text);
}

/**
 * Upload a Scan Website file to the Responses API vector store.
 *
 * Replaces the previous file stored under the same name.
 *
 * @param string $filename File name without extension.
 * @param string $content  File content.
 * @param string $mode     'qa' (plain-text Q&A) or 'markdown'.
 * @return string|false New file ID, or false on failure.
 */
function wpiko_chatbot_upload_qa_file_to_responses($filename, $content, $mode = 'qa') {
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
    $is_markdown = $mode === 'markdown';
    $file_data = [
        'tmp_name' => $temp_file_path,
        'name' => $filename . ($is_markdown ? '.md' : '.txt'),
        'type' => $is_markdown ? 'text/markdown' : 'text/plain',
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
        wpiko_chatbot_log_error("Failed to upload scanned file to Responses API: " . $result['message']);
        return false;
    }
}

/**
 * Remember which WordPress page a Scan Website Q&A file was generated from.
 *
 * @param int    $page_id  Page ID.
 * @param string $filename File name (without extension).
 * @param string $file_id  OpenAI file ID.
 * @param string $mode     'qa' or 'markdown'.
 * @param string $hash     md5 of the uploaded content, so unchanged items can be skipped.
 * @return void
 */
function wpiko_chatbot_pro_remember_scanned_page($page_id, $filename, $file_id, $mode = 'qa', $hash = '') {
    $scanned = get_option('wpiko_chatbot_pro_scanned_pages', array());
    $scanned = is_array($scanned) ? $scanned : array();
    $scanned[(int) $page_id] = array(
        'filename' => $filename,
        'file_id' => $file_id,
        'time' => time(),
        'mode' => $mode === 'markdown' ? 'markdown' : 'qa',
        'hash' => (string) $hash,
    );
    update_option('wpiko_chatbot_pro_scanned_pages', $scanned, false);
}

/**
 * Read the filename => file ID options also written by pre-2.1.0 scans.
 *
 * @return array Option name => file ID.
 */
function wpiko_chatbot_pro_scan_file_options() {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('wpiko_chatbot_responses_qa_file_') . '%'
    ));
    $options = array();
    foreach ((array) $rows as $row) {
        $options[$row->option_name] = $row->option_value;
    }
    return $options;
}

/**
 * Recover page associations for scans uploaded before page IDs were recorded.
 * Runs on demand from Quick learn, once per store after a successful lookup.
 * Old filenames came from page titles; ambiguous or renamed pages cannot be
 * identified reliably and must not be guessed. Explicit page IDs always win.
 *
 * @return void
 */
function wpiko_chatbot_pro_migrate_scan_coverage() {
    $vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');
    if ($vector_store_id === '' || get_option('wpiko_chatbot_pro_scan_coverage_migrated', '') === $vector_store_id) {
        return;
    }
    // Also bounds retries when the remote service is temporarily unavailable.
    if (get_transient('wpiko_chatbot_pro_scan_coverage_retry')) {
        return;
    }
    set_transient('wpiko_chatbot_pro_scan_coverage_retry', 1, MINUTE_IN_SECONDS);

    $legacy_options = wpiko_chatbot_pro_scan_file_options();
    $scanned = get_option('wpiko_chatbot_pro_scanned_pages', array());
    $scanned = is_array($scanned) ? $scanned : array();
    foreach ($scanned as $entry) {
        if (!empty($entry['filename']) && !empty($entry['file_id'])) {
            $key = 'wpiko_chatbot_responses_qa_file_' . md5($entry['filename']);
            if (isset($legacy_options[$key]) && $legacy_options[$key] === $entry['file_id']) {
                unset($legacy_options[$key]);
            }
        }
    }

    $matches = array();
    if ($legacy_options) {
        global $wpdb;
        // Only titles and IDs are needed. Include non-public pages to detect
        // filename collisions, without loading every page's content into memory.
        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s",
            'page'
        ));
        $filenames = array();
        foreach ((array) $pages as $page) {
            $filename = sanitize_file_name(wpiko_chatbot_pro_get_filename_from_title($page->post_title));
            $filenames[$filename][] = (int) $page->ID;
        }
        foreach ($filenames as $filename => $page_ids) {
            $key = 'wpiko_chatbot_responses_qa_file_' . md5($filename);
            if (count($page_ids) === 1 && !isset($scanned[$page_ids[0]]) && !empty($legacy_options[$key])) {
                $matches[$page_ids[0]] = array('filename' => $filename, 'file_id' => $legacy_options[$key]);
            }
        }
    }

    if ($matches) {
        // Old releases could leave options behind after a file was deleted.
        // Verify membership before treating any of those options as coverage.
        $result = wpiko_chatbot_list_responses_files();
        if (empty($result['success']) || empty($result['complete']) || !isset($result['files']) || !empty($result['source'])) {
            return;
        }
        if (get_option('wpiko_chatbot_responses_vector_store_id', '') !== $vector_store_id) {
            return;
        }
        $active_files = array_column($result['files'], null, 'id');
        // Reload after the remote request so a concurrent new scan is preserved.
        $scanned = get_option('wpiko_chatbot_pro_scanned_pages', array());
        $scanned = is_array($scanned) ? $scanned : array();
        foreach ($matches as $page_id => $entry) {
            $key = 'wpiko_chatbot_responses_qa_file_' . md5($entry['filename']);
            if (!isset($scanned[$page_id]) && isset($active_files[$entry['file_id']])
                && get_option($key, '') === $entry['file_id']) {
                $scanned[$page_id] = $entry + array('time' => 0);
            }
        }
        update_option('wpiko_chatbot_pro_scanned_pages', $scanned, false);
    }

    update_option('wpiko_chatbot_pro_scan_coverage_migrated', $vector_store_id, false);
    delete_transient('wpiko_chatbot_pro_scan_coverage_retry');
}

/**
 * Pages whose Scan Website Q&A file is still in the knowledge base.
 *
 * @param array $covered Page ID => label.
 * @return array
 */
function wpiko_chatbot_pro_site_knowledge_covered_pages($covered) {
    wpiko_chatbot_pro_migrate_scan_coverage();
    $scanned = get_option('wpiko_chatbot_pro_scanned_pages', array());
    if (!is_array($scanned)) {
        return $covered;
    }

    foreach ($scanned as $page_id => $entry) {
        if (empty($entry['filename']) || empty($entry['file_id'])) {
            continue;
        }
        // Only count it while this Q&A file is still the current one for the page.
        $current_file_id = get_option('wpiko_chatbot_responses_qa_file_' . md5($entry['filename']), '');
        if ($current_file_id !== '' && $current_file_id === $entry['file_id']) {
            $covered[(int) $page_id] = 'Covered by Scan Website';
        }
    }

    return $covered;
}
add_filter('wpiko_chatbot_site_knowledge_covered_pages', 'wpiko_chatbot_pro_site_knowledge_covered_pages');

/**
 * Forget a scanned page when its Q&A file is deleted (for example in File Management),
 * so it can be added to "Quick learn from your pages" again.
 *
 * @param string $file_id Deleted file ID.
 * @return void
 */
function wpiko_chatbot_pro_forget_deleted_scan_file($file_id) {
    // Clean legacy records even if they have not acquired a page ID yet.
    foreach (wpiko_chatbot_pro_scan_file_options() as $key => $stored_file_id) {
        if ($stored_file_id === $file_id) {
            delete_option($key);
        }
    }
    $scanned = get_option('wpiko_chatbot_pro_scanned_pages', array());
    if (!is_array($scanned) || empty($scanned)) {
        return;
    }

    $changed = false;
    foreach ($scanned as $page_id => $entry) {
        if (isset($entry['file_id']) && $entry['file_id'] === $file_id) {
            unset($scanned[$page_id]);
            if (!empty($entry['filename']) && get_option('wpiko_chatbot_responses_qa_file_' . md5($entry['filename']), '') === $file_id) {
                delete_option('wpiko_chatbot_responses_qa_file_' . md5($entry['filename']));
            }
            $changed = true;
        }
    }

    if ($changed) {
        update_option('wpiko_chatbot_pro_scanned_pages', $scanned, false);
    }
}
add_action('wpiko_chatbot_responses_file_deleted', 'wpiko_chatbot_pro_forget_deleted_scan_file');

/**
 * The whole knowledge store was deleted: no page is covered any more.
 *
 * @return void
 */
function wpiko_chatbot_pro_forget_all_scanned_pages() {
    foreach (wpiko_chatbot_pro_scan_file_options() as $key => $file_id) {
        delete_option($key);
    }
    delete_option('wpiko_chatbot_pro_scanned_pages');
    delete_option('wpiko_chatbot_pro_scan_coverage_migrated');
    delete_transient('wpiko_chatbot_pro_scan_coverage_retry');
}
add_action('wpiko_chatbot_responses_vector_store_deleted', 'wpiko_chatbot_pro_forget_all_scanned_pages');

/**
 * Dashicon for a content type: the plugin's own menu icon when it uses one.
 *
 * @param WP_Post_Type $type_object Post type object.
 * @return string Dashicons class.
 */
function wpiko_chatbot_pro_scan_type_icon($type_object) {
    if ($type_object->name === 'page') {
        return 'dashicons-admin-page';
    }
    if ($type_object->name === 'post') {
        return 'dashicons-admin-post';
    }
    $icon = is_string($type_object->menu_icon) ? $type_object->menu_icon : '';
    // Image URLs and inline SVGs cannot be shown as a font icon.
    return strpos($icon, 'dashicons-') === 0 ? sanitize_html_class($icon) : 'dashicons-media-document';
}

/**
 * AJAX: every page the Scan Website tool can process, with its scan state,
 * plus scanned Q&A files that are no longer linked to a page.
 *
 * Response: {
 *   types:   [{name, label, singular, icon, count}],
 *   pages:   [{id, type, title, status, protected, url, edit_url, modified, scanned, scanned_at,
 *             file_id, mode, scanned_mode, format_changed, changed}],
 *   orphans: [{id, filename, bytes, created_at}],
 *   truncated: bool
 * }
 */
function wpiko_chatbot_pro_ajax_get_scan_pages() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to do this.'), 403);
    }
    if (!wpiko_chatbot_is_license_active()) {
        wp_send_json_error(array('message' => 'This feature requires a premium license'));
    }

    $limit = (int) apply_filters('wpiko_chatbot_pro_scan_pages_limit', 500);

    wpiko_chatbot_pro_migrate_scan_coverage();
    $scanned = get_option('wpiko_chatbot_pro_scanned_pages', array());
    $scanned = is_array($scanned) ? $scanned : array();

    // Files currently in the knowledge base (cached by core). When the list
    // cannot be read, trust the stored records instead of hiding every scan.
    $files_result = function_exists('wpiko_chatbot_list_responses_files') ? wpiko_chatbot_list_responses_files() : array('success' => false);
    $active_files = null;
    $page_files = array();
    if (!empty($files_result['success']) && isset($files_result['files'])) {
        $active_files = array();
        foreach ($files_result['files'] as $file) {
            $active_files[$file['id']] = true;
            if (isset($file['filename']) && strpos($file['filename'], 'page_') === 0) {
                $page_files[$file['id']] = $file;
            }
        }
    }

    $types = array();
    $posts = array();
    $truncated = false;
    $excluded = wpiko_chatbot_pro_scan_excluded_ids();
    foreach (wpiko_chatbot_pro_scan_post_types() as $type_name) {
        $type_object = get_post_type_object($type_name);
        if (!$type_object) {
            continue;
        }
        // The limit applies per type so a big blog cannot hide the docs.
        $query = new WP_Query(array(
            'post_type' => $type_name,
            'post_status' => array('publish', 'private', 'draft', 'pending'),
            'posts_per_page' => $limit,
            'post__not_in' => $excluded,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'ignore_sticky_posts' => true,
            'suppress_filters' => true,
        ));
        if (!$query->posts) {
            continue;
        }
        if (count($query->posts) >= $limit) {
            $truncated = true;
        }
        $types[] = array(
            'name' => $type_name,
            'label' => $type_object->labels->name,
            'singular' => $type_object->labels->singular_name,
            'icon' => wpiko_chatbot_pro_scan_type_icon($type_object),
            'count' => count($query->posts),
        );
        $posts = array_merge($posts, $query->posts);
    }

    $pages = array();
    $linked_file_ids = array();
    foreach ($posts as $post) {
        $entry = isset($scanned[$post->ID]) ? $scanned[$post->ID] : null;
        $file_id = '';
        if ($entry && !empty($entry['file_id']) && !empty($entry['filename'])) {
            $current = get_option('wpiko_chatbot_responses_qa_file_' . md5($entry['filename']), '');
            $in_store = ($active_files === null) || isset($active_files[$entry['file_id']]);
            if ($current === $entry['file_id'] && $in_store) {
                $file_id = $entry['file_id'];
                $linked_file_ids[$file_id] = true;
            }
        }

        // Some pages (e.g. created by plugins) have an empty GMT date; fall back to local time.
        $modified_gmt = $post->post_modified_gmt;
        if (empty($modified_gmt) || strpos($modified_gmt, '0000-00-00') === 0) {
            $modified_gmt = get_gmt_from_date($post->post_modified);
        }
        $modified = max(0, (int) mysql2date('U', $modified_gmt, false));
        $scanned_at = $file_id !== '' ? (int) $entry['time'] : 0;

        // Scans made before Markdown support were all Q&A. An item whose file
        // was made the other way is offered for a rescan.
        $mode = wpiko_chatbot_pro_scan_mode($post);
        $scanned_mode = ($file_id !== '' && isset($entry['mode']) && $entry['mode'] === 'markdown') ? 'markdown' : 'qa';
        $format_changed = $file_id !== '' && $scanned_mode !== $mode;

        $pages[] = array(
            'id' => (int) $post->ID,
            'type' => $post->post_type,
            'title' => $post->post_title !== '' ? $post->post_title : '(no title)',
            'status' => $post->post_status,
            'protected' => $post->post_password !== '',
            'url' => get_permalink($post),
            'edit_url' => get_edit_post_link($post->ID, 'raw'),
            'modified' => $modified,
            'scanned' => $file_id !== '',
            'scanned_at' => $scanned_at,
            'file_id' => $file_id,
            'mode' => $mode,
            'scanned_mode' => $file_id !== '' ? $scanned_mode : '',
            'format_changed' => $format_changed,
            // Unknown scan dates (older versions) are never flagged for edits.
            'changed' => $format_changed || ($file_id !== '' && $scanned_at > 0 && $modified > $scanned_at),
        );
    }

    // Pages beyond the list limit still own their files. Files of deleted or
    // trashed pages are listed as "other scanned files" so they can be removed.
    foreach ($scanned as $scanned_page_id => $entry) {
        if (empty($entry['file_id']) || isset($linked_file_ids[$entry['file_id']])) {
            continue;
        }
        // Hidden items (e.g. WooCommerce Cart) scanned before they were hidden
        // are listed under "Other scanned files" so they can still be removed.
        if (in_array((int) $scanned_page_id, $excluded, true)) {
            continue;
        }
        $status = get_post_status((int) $scanned_page_id);
        if ($status && $status !== 'trash') {
            $linked_file_ids[$entry['file_id']] = true;
        }
    }

    $orphans = array();
    foreach ($page_files as $file_id => $file) {
        if (!isset($linked_file_ids[$file_id])) {
            $orphans[] = array(
                'id' => $file_id,
                'filename' => $file['filename'],
                'bytes' => isset($file['bytes']) ? (int) $file['bytes'] : 0,
                'created_at' => isset($file['created_at']) ? (int) $file['created_at'] : 0,
            );
        }
    }

    wp_send_json_success(array(
        'types' => $types,
        'pages' => $pages,
        'orphans' => $orphans,
        'truncated' => $truncated,
        'limit' => $limit,
    ));
}
add_action('wp_ajax_wpiko_chatbot_get_scan_pages', 'wpiko_chatbot_pro_ajax_get_scan_pages');
