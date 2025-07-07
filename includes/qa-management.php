<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Create the Q&A table
function wpiko_chatbot_pro_create_qa_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_qa';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        question text NOT NULL,
        answer text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add or update Q&A pair
function wpiko_chatbot_pro_add_update_qa($question, $answer, $id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_qa';

    // Check if table exists and create it if it doesn't
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        wpiko_chatbot_pro_create_qa_table();
    }

    if ($id) {
        $result = $wpdb->update(
            $table_name,
            array('question' => $question, 'answer' => $answer),
            array('id' => $id)
        );
    } else {
        $result = $wpdb->insert(
            $table_name,
            array('question' => $question, 'answer' => $answer)
        );
        $id = $wpdb->insert_id;
    }

    if ($result === false) {
        return false;
    }

    wpiko_chatbot_pro_generate_qa_file();
    return $id;
}

// Delete Q&A pair
function wpiko_chatbot_pro_delete_qa($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_qa';

    $result = $wpdb->delete($table_name, array('id' => $id));

    if ($result === false) {
        return false;
    }

    return true;
}

// Function to delete the Q&A file
function wpiko_chatbot_pro_delete_qa_file() {
    $file_id = get_option('wpiko_chatbot_qa_file_id', '');
    if ($file_id) {
        wpiko_chatbot_delete_file_from_wpiko($file_id);
        delete_option('wpiko_chatbot_qa_file_id');
    }
}

// Locking function
function wpiko_chatbot_pro_get_qa_lock($timeout = 60) {
    $lock_key = 'wpiko_chatbot_qa_sync_lock';
    $lock_expiration = get_option($lock_key);
    
    if ($lock_expiration && $lock_expiration > time()) {
        return false;
    }
    
    update_option($lock_key, time() + $timeout);
    return true;
}

function wpiko_chatbot_pro_release_qa_lock() {
    $lock_key = 'wpiko_chatbot_qa_sync_lock';
    delete_option($lock_key);
}

// Generate Q&A file and upload to Assistant API
function wpiko_chatbot_pro_cleanup_and_generate_qa_file($qa_pairs = null) {
    global $wpdb;
    
    if (!wpiko_chatbot_pro_get_qa_lock(90)) {
        wpiko_chatbot_log_error("Q&A sync is already in progress.");
        return false;
    }

    try {
        // Clean up duplicates when explicitly asked to through the AJAX call
        // This function will focus just on generating the file
        
        // If no Q&A pairs provided, fetch from database
        if ($qa_pairs === null) {
            $table_name = $wpdb->prefix . 'wpiko_chatbot_qa';
            // Check if table exists and create it if it doesn't
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
                wpiko_chatbot_pro_create_qa_table();
            }
            $qa_pairs = $wpdb->get_results(
                "SELECT question, answer FROM `{$wpdb->prefix}wpiko_chatbot_qa`"
            , ARRAY_A);
        }

        if (empty($qa_pairs)) {
            wpiko_chatbot_pro_delete_qa_file();
            return false;
        }

        $content = '';
        foreach ($qa_pairs as $pair) {
            $content .= "Q: " . $pair['question'] . "\n";
            $content .= "A: " . $pair['answer'] . "\n\n";
        }

        $filename = 'qa_data_questions-and-answers';
        $file_id = wpiko_chatbot_upload_qa_file($filename, $content);

        if ($file_id) {
            $old_file_id = get_option('wpiko_chatbot_qa_file_id', '');
            if ($old_file_id) {
                if (wpiko_chatbot_delete_file_from_wpiko($old_file_id)) {
                    // Remove the old file from cache to ensure list displays correctly
                    if (function_exists('wpiko_chatbot_remove_cached_file')) {
                        wpiko_chatbot_remove_cached_file($old_file_id);
                    }
                }
            }

            wpiko_chatbot_log('New Q&A file created with ID: ' . $file_id, 'info');
            update_option('wpiko_chatbot_qa_file_id', $file_id);
            return true;
        }

        wpiko_chatbot_log('Failed to create Q&A file', 'error');
        return false;

    } catch (Exception $e) {
        wpiko_chatbot_log('Error in cleanup_and_generate_qa_file: ' . $e->getMessage(), 'error');
        return false;

    } finally {
        wpiko_chatbot_pro_release_qa_lock();
    }
}

function wpiko_chatbot_pro_generate_qa_file() {
    return wpiko_chatbot_pro_cleanup_and_generate_qa_file();
}

// AJAX handler for adding/updating Q&A
function wpiko_chatbot_pro_ajax_add_update_qa() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    // Check for premium license
    if (!wpiko_chatbot_is_license_active()) {
        wp_send_json_error(array('message' => 'This feature requires a premium license'));
    }

    // Validate POST data before processing
    if (!isset($_POST['question']) || !isset($_POST['answer'])) {
        wp_send_json_error(array('message' => 'Missing required fields'));
        return;
    }

    $question = sanitize_text_field(wp_unslash($_POST['question']));
    $answer = sanitize_textarea_field(wp_unslash($_POST['answer']));
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;

    $result = wpiko_chatbot_pro_add_update_qa($question, $answer, $id);

    if ($result === false) {
        wp_send_json_error(array('message' => 'Failed to save Q&A'));
    } else {
        // Generate Q&A file with locking mechanism
        $file_generated = wpiko_chatbot_pro_generate_qa_file();
        if ($file_generated) {
            wp_send_json_success(array('message' => 'Q&A saved and file generated successfully', 'id' => $result));
        } else {
            wp_send_json_error(array('message' => 'Q&A saved but file generation failed'));
        }
    }
}
add_action('wp_ajax_wpiko_chatbot_add_update_qa', 'wpiko_chatbot_pro_ajax_add_update_qa');

// AJAX handler for saving all Q&A pairs
function wpiko_chatbot_pro_ajax_save_all_qa() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    // Check for premium license
    if (!wpiko_chatbot_is_license_active()) {
        wp_send_json_error(array('message' => 'This feature requires a premium license'));
    }

    // Validate and unslash the POST data
    if (!isset($_POST['qa_pairs']) || empty($_POST['qa_pairs'])) {
        wp_send_json_error(array('message' => 'No data provided'));
    }
    
    // Sanitize raw input before processing
    $qa_pairs_raw = sanitize_text_field(wp_unslash($_POST['qa_pairs']));
    $qa_pairs = json_decode($qa_pairs_raw, true);
    
    if (!is_array($qa_pairs)) {
        wp_send_json_error(array('message' => 'Invalid data format'));
    }
    
    // Sanitize each Q&A pair individually after JSON decoding
    foreach ($qa_pairs as $key => $qa) {
        if (isset($qa['question'])) {
            $qa_pairs[$key]['question'] = sanitize_text_field($qa['question']);
        }
        if (isset($qa['answer'])) {
            $qa_pairs[$key]['answer'] = sanitize_textarea_field($qa['answer']);
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_qa';

    // Start transaction
    $wpdb->query('START TRANSACTION');

    // Delete all existing Q&A pairs
    $wpdb->query("TRUNCATE TABLE `{$wpdb->prefix}wpiko_chatbot_qa`");

    $success = true;
    foreach ($qa_pairs as $qa) {
        // Only insert if both question and answer are not empty
        if (!empty($qa['question']) && !empty($qa['answer'])) {
            $result = $wpdb->insert(
                $table_name,
                array('question' => $qa['question'], 'answer' => $qa['answer'])
            );
            if ($result === false) {
                $success = false;
                break;
            }
        }
    }

    if ($success) {
        $wpdb->query('COMMIT');
        // Generate and upload the new file
        $file_generated = wpiko_chatbot_pro_generate_qa_file();
        if ($file_generated) {
            wp_send_json_success(array('message' => 'All Q&A pairs saved and file generated successfully'));
        } else {
            wp_send_json_error(array('message' => 'Q&A pairs saved but file generation failed'));
        }
    } else {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(array('message' => 'Failed to save Q&A pairs'));
    }
}
add_action('wp_ajax_wpiko_chatbot_save_all_qa', 'wpiko_chatbot_pro_ajax_save_all_qa');

// AJAX handler for deleting Q&A
function wpiko_chatbot_pro_ajax_delete_qa() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    if (!isset($_POST['id'])) {
        wp_send_json_error(array('message' => 'Missing ID parameter'));
        return;
    }

    $id = intval($_POST['id']);

    $result = wpiko_chatbot_delete_qa($id);

    if ($result) {
        wp_send_json_success(array('message' => 'Q&A deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete Q&A'));
    }
}
add_action('wp_ajax_wpiko_chatbot_delete_qa', 'wpiko_chatbot_pro_ajax_delete_qa');

// Get all Q&A pairs
function wpiko_chatbot_pro_get_all_qa() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_qa';

    // Check if table exists and create it if it doesn't
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        wpiko_chatbot_pro_create_qa_table();
    }

    return $wpdb->get_results(
        "SELECT * FROM `{$wpdb->prefix}wpiko_chatbot_qa` ORDER BY id DESC"
    , ARRAY_A);
}

// AJAX handler for getting all Q&A pairs
function wpiko_chatbot_pro_ajax_get_qa_pairs() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $qa_pairs = wpiko_chatbot_pro_get_all_qa();
    wp_send_json_success($qa_pairs);
}
add_action('wp_ajax_wpiko_chatbot_get_qa_pairs', 'wpiko_chatbot_pro_ajax_get_qa_pairs');

// AJAX handler for downloading Q&A file
function wpiko_chatbot_pro_ajax_download_qa_file() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_qa';

    $qa_pairs = $wpdb->get_results(
        "SELECT question, answer FROM `{$wpdb->prefix}wpiko_chatbot_qa`"
    , ARRAY_A);

    if (empty($qa_pairs)) {
        wp_send_json_error(array('message' => 'No Q&A pairs available to download.'));
        return;
    }

    $content = '';
    foreach ($qa_pairs as $pair) {
        $content .= "Q: " . $pair['question'] . "\n";
        $content .= "A: " . $pair['answer'] . "\n\n";
    }

    $filename = 'Questions-Answers-' . gmdate('Y-m-d') . '.txt';

    wp_send_json_success(array(
        'filename' => $filename,
        'content' => base64_encode($content)
    ));
}
add_action('wp_ajax_wpiko_chatbot_download_qa_file', 'wpiko_chatbot_pro_ajax_download_qa_file');

// Function to delete all Q&A pairs
function wpiko_chatbot_pro_delete_all_qa() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_qa';

    $result = $wpdb->query("TRUNCATE TABLE `{$wpdb->prefix}wpiko_chatbot_qa`");

    if ($result !== false) {
        wpiko_chatbot_pro_delete_qa_file();
        return true;
    }

    return false;
}

// Function to check and clean up duplicate Q&A files
function wpiko_chatbot_pro_validate_directory($path) {
    wpiko_chatbot_log('Validating directory: ' . $path, 'info');
    
    // Initialize the WordPress Filesystem
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    // Initialize WP_Filesystem
    if (!WP_Filesystem()) {
        wpiko_chatbot_log('Failed to initialize WP_Filesystem', 'error');
        return false;
    }
    
    if (!$wp_filesystem->exists($path)) {
        wpiko_chatbot_log('Directory does not exist, creating: ' . $path, 'info');
        if (!wp_mkdir_p($path)) {
            wpiko_chatbot_log('Failed to create directory: ' . $path, 'error');
            return false;
        }
    }
    
    if (!$wp_filesystem->is_writable($path)) {
        wpiko_chatbot_log('Directory not writable: ' . $path, 'error');
        // Try to set permissions
        if (!$wp_filesystem->chmod($path, FS_CHMOD_DIR)) {
            wpiko_chatbot_log('Failed to set directory permissions: ' . $path, 'error');
            return false;
        }
    }
    
    return true;
}

function wpiko_chatbot_pro_check_duplicate_qa_files() {
    $upload_dir = wp_upload_dir();
    $assistant_id = get_option('wpiko_chatbot_assistant_id', '');
    
    wpiko_chatbot_log('Starting duplicate Q&A files check...', 'info');
    wpiko_chatbot_log('Upload base directory: ' . $upload_dir['basedir'], 'info');
    wpiko_chatbot_log('Assistant ID: ' . ($assistant_id ? $assistant_id : 'Not found'), 'info');
    
    // Initialize the WordPress Filesystem
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    // Initialize WP_Filesystem
    if (!WP_Filesystem()) {
        wpiko_chatbot_log('Failed to initialize WP_Filesystem', 'error');
        return false;
    }
    
    // Ensure proper directory structure
    $base_dir = $upload_dir['basedir'] . '/wpiko-chatbot';
    $assistant_dir = $base_dir . '/assistants/' . $assistant_id;
    
    // Validate directories
    if (!wpiko_chatbot_pro_validate_directory($base_dir) || 
        (!empty($assistant_id) && !wpiko_chatbot_pro_validate_directory($assistant_dir))) {
        wpiko_chatbot_log('Failed to validate directories', 'error');
        return false;
    }
    
    // Check for duplicate files in OpenAI assistant
    $api_duplicates_cleaned = false;
    if (!empty($assistant_id)) {
        wpiko_chatbot_log('Checking OpenAI assistant files...', 'info');
        $api_result = wpiko_chatbot_list_assistant_files($assistant_id);
        if ($api_result['success'] && !empty($api_result['files'])) {
            $qa_files = array_filter($api_result['files'], function($file) {
                return strpos($file['filename'], 'qa_data_questions-and-answers') !== false;
            });
            
            if (count($qa_files) > 1) {
                wpiko_chatbot_log('Found ' . count($qa_files) . ' duplicate files in OpenAI assistant', 'warning');
                // Sort by creation time, newest first
                usort($qa_files, function($a, $b) {
                    return $b['created_at'] - $a['created_at'];
                });
                
                // Keep the newest file, delete others
                $newest_file = array_shift($qa_files);
                $delete_success = true;
                foreach ($qa_files as $file) {
                    $delete_result = wpiko_chatbot_delete_assistant_file($assistant_id, $file['id']);
                    if ($delete_result['success']) {
                        wpiko_chatbot_log('Successfully deleted OpenAI file: ' . $file['filename'], 'info');
                    } else {
                        wpiko_chatbot_log('Failed to delete OpenAI file: ' . $file['filename'], 'error');
                        $delete_success = false;
                    }
                }
                $api_duplicates_cleaned = $delete_success;
            }
        }
    }
    
    // Check local files
    wpiko_chatbot_log('Checking local files...', 'info');
    $paths = array(
        $upload_dir['basedir'] . '/wpiko-chatbot/assistants/' . $assistant_id,
        $upload_dir['basedir'] . '/wpiko-chatbot'
    );
    
    $qa_files = array();
    foreach ($paths as $path) {
        if (!$wp_filesystem->exists($path)) {
            wpiko_chatbot_log('Creating directory: ' . $path, 'info');
            wp_mkdir_p($path);
            continue;
        }
        
        wpiko_chatbot_log('Scanning directory: ' . $path, 'info');
        $pattern = $path . '/*qa_data_questions-and-answers*.txt';
        $found = glob($pattern);
        
        if ($found) {
            foreach ($found as $file) {
                $perms = $wp_filesystem->getchmod($file);
                // Check if file is writable (permissions allow writing)
                if ($perms && intval($perms, 8) & 0200) {
                    wpiko_chatbot_log('Found writable local file: ' . $file, 'info');
                    $qa_files[] = $file;
                } else {
                    wpiko_chatbot_log('Found non-writable local file: ' . $file, 'warning');
                    // Try to make file writable
                    if ($wp_filesystem->chmod($file, FS_CHMOD_FILE)) {
                        wpiko_chatbot_log('Made file writable: ' . $file, 'info');
                        $qa_files[] = $file;
                    }
                }
            }
        }
    }
    
    if (count($qa_files) > 1) {
        // Sort files by modification time, newest first
        usort($qa_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Keep the newest file
        $newest_file = array_shift($qa_files);
        wpiko_chatbot_log('Keeping newest file: ' . $newest_file, 'info');
        
        $deleted_count = 0;
        foreach ($qa_files as $file) {
            wpiko_chatbot_log('Attempting to delete: ' . $file, 'info');
            if ($wp_filesystem->is_writable($file) && $wp_filesystem->delete($file)) {
                $deleted_count++;
                wpiko_chatbot_log('Successfully deleted: ' . $file, 'info');
            } else {
                wpiko_chatbot_log('Failed to delete file: ' . $file, 'error');
                wpiko_chatbot_log('File writable: ' . ($wp_filesystem->is_writable($file) ? 'yes' : 'no'), 'error');
                wpiko_chatbot_log('File exists: ' . ($wp_filesystem->exists($file) ? 'yes' : 'no'), 'error');
            }
        }
        
        wpiko_chatbot_log('Deleted ' . $deleted_count . ' duplicate files', 'info');
        return $deleted_count > 0;
    }
    
    wpiko_chatbot_log('No duplicate Q&A files found', 'info');
    return false;
}

// AJAX handler for checking duplicate Q&A files
function wpiko_chatbot_pro_ajax_check_duplicate_qa_files() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $duplicateFound = wpiko_chatbot_pro_check_duplicate_qa_files();
    wp_send_json_success(array('duplicateFound' => $duplicateFound));
}
add_action('wp_ajax_wpiko_chatbot_check_duplicate_qa_files', 'wpiko_chatbot_pro_ajax_check_duplicate_qa_files');

// AJAX handler for deleting all Q&A pairs
function wpiko_chatbot_pro_ajax_delete_all_qa() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $result = wpiko_chatbot_pro_delete_all_qa();

    if ($result) {
        wp_send_json_success(array('message' => 'All Q&A pairs deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete all Q&A pairs'));
    }
}
add_action('wp_ajax_wpiko_chatbot_delete_all_qa', 'wpiko_chatbot_pro_ajax_delete_all_qa');
