<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Log error
function wpiko_chatbot_log_error($message) {
    wpiko_chatbot_log('OpenAI Chatbot Error: ' . $message, 'error');
}

// Function save_woocommerce_settings (moved from free plugin)
function wpiko_chatbot_save_woocommerce_settings() {
    // Verify nonce before processing any POST data
    if (!isset($_POST['wpiko_chatbot_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_nonce'])), 'wpiko_chatbot_nonce')) {
        // If nonce verification fails, log and skip processing
        wpiko_chatbot_log('Nonce verification failed in wpiko_chatbot_save_woocommerce_settings', 'warning');
        return false;
    }
    
    // Only proceed if user has correct permissions
    if (!current_user_can('manage_options')) {
        wpiko_chatbot_log('Insufficient permissions in wpiko_chatbot_save_woocommerce_settings', 'warning');
        return false;
    }
    
    if (isset($_POST['cron_frequency'])) {
        $frequency = sanitize_text_field(wp_unslash($_POST['cron_frequency']));
        if (function_exists('wpiko_chatbot_set_cron_frequency')) {
            wpiko_chatbot_set_cron_frequency($frequency);
        }
        if (function_exists('wpiko_chatbot_schedule_product_sync')) {
            wpiko_chatbot_schedule_product_sync(); // Reschedule with new frequency
        }
    }

    if (isset($_POST['woocommerce_integration_enabled'])) {
        update_option('wpiko_chatbot_woocommerce_integration_enabled', true);
    } else {
        update_option('wpiko_chatbot_woocommerce_integration_enabled', false);
    }

    if (isset($_POST['woocommerce_auto_update_enabled'])) {
        update_option('wpiko_chatbot_woocommerce_auto_update_enabled', true);
    } else {
        update_option('wpiko_chatbot_woocommerce_auto_update_enabled', false);
    }
    
    return true;
}

// Function to check if WooCommerce integration is enabled
function wpiko_chatbot_is_woocommerce_integration_enabled() {
    return get_option('wpiko_chatbot_woocommerce_integration_enabled', false);
}

// Function to locking mechanism to prevent simultaneous syncs - Woo files
// Uses transients instead of options for reliable expiration on live/shared hosting
function wpiko_chatbot_get_lock($lock_name, $timeout = 60, $check_only = false) {
    $lock_key = 'wpiko_lock_' . $lock_name;
    $existing = get_transient($lock_key);
    
    if ($existing !== false) {
        return false;
    }
    
    if (!$check_only) {
        set_transient($lock_key, time(), $timeout);
    }
    return true;
}

function wpiko_chatbot_release_lock($lock_name) {
    $lock_key = 'wpiko_lock_' . $lock_name;
    delete_transient($lock_key);
}

/**
 * Helper: Write data to a temp file with WP_Filesystem fallback to native PHP.
 * Returns the temp file path on success, or false on failure.
 */
function wpiko_chatbot_write_temp_file($prefix, $data) {
    // Try WP_Filesystem first
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    
    $temp_file_path = wp_tempnam($prefix);
    if (!$temp_file_path) {
        wpiko_chatbot_log_error('Failed to create temp file for: ' . $prefix);
        return false;
    }
    
    // Try WP_Filesystem
    $fs_init = WP_Filesystem();
    if ($fs_init && $wp_filesystem && $wp_filesystem->put_contents($temp_file_path, $data)) {
        return $temp_file_path;
    }
    
    // Fallback to native PHP file_put_contents
    wpiko_chatbot_log_error('WP_Filesystem unavailable for ' . $prefix . ', using native fallback');
    $written = @file_put_contents($temp_file_path, $data);
    if ($written !== false) {
        return $temp_file_path;
    }
    
    // Clean up on failure
    @unlink($temp_file_path);
    wpiko_chatbot_log_error('Failed to write temp file for: ' . $prefix);
    return false;
}

/**
 * Helper: Delete a temp file with WP_Filesystem fallback to native PHP.
 */
function wpiko_chatbot_delete_temp_file($file_path) {
    if (empty($file_path) || !file_exists($file_path)) {
        return;
    }
    global $wp_filesystem;
    if ($wp_filesystem && $wp_filesystem->exists($file_path)) {
        $wp_filesystem->delete($file_path);
    } else {
        @unlink($file_path);
    }
}


// Function to check license status
function wpiko_chatbot_check_woocommerce_license_status() {
    if (!wpiko_chatbot_is_license_active()) {
        // Save current auto-sync settings before disabling
        $current_products_sync = get_option('wpiko_chatbot_products_auto_sync', 'disabled');
        $current_orders_sync = get_option('wpiko_chatbot_orders_auto_sync', 'disabled');
        
        if ($current_products_sync !== 'disabled') {
            update_option('wpiko_chatbot_previous_products_auto_sync', $current_products_sync);
        }
        if ($current_orders_sync !== 'disabled') {
            update_option('wpiko_chatbot_previous_orders_auto_sync', $current_orders_sync);
        }
        
        // Disable auto-syncs
        update_option('wpiko_chatbot_products_auto_sync', 'disabled');
        update_option('wpiko_chatbot_orders_auto_sync', 'disabled');
        
        // Clear scheduled syncs
        wp_clear_scheduled_hook('wpiko_chatbot_sync_products');
        wp_clear_scheduled_hook('wpiko_chatbot_sync_orders');
        
    } else {
        // License is active, restore previous settings if they exist and WooCommerce integration is enabled
        $previous_products_sync = get_option('wpiko_chatbot_previous_products_auto_sync', false);
        $previous_orders_sync = get_option('wpiko_chatbot_previous_orders_auto_sync', false);
        
        // Only restore settings if WooCommerce integration is currently enabled
        if (wpiko_chatbot_is_woocommerce_integration_enabled()) {
            if ($previous_products_sync) {
                update_option('wpiko_chatbot_products_auto_sync', $previous_products_sync);
                delete_option('wpiko_chatbot_previous_products_auto_sync');
                
                // Reschedule product sync if needed
                if ($previous_products_sync !== 'disabled') {
                    wp_clear_scheduled_hook('wpiko_chatbot_sync_products');
                    wp_schedule_event(time(), $previous_products_sync, 'wpiko_chatbot_sync_products');
                }
            }
            
            if ($previous_orders_sync) {
                update_option('wpiko_chatbot_orders_auto_sync', $previous_orders_sync);
                delete_option('wpiko_chatbot_previous_orders_auto_sync');
                
                // Trigger orders sync if needed
                if ($previous_orders_sync !== 'disabled') {
                    wpiko_chatbot_sync_orders();
                }
            }
        } else {
            // If WooCommerce integration is disabled, clean up the previous settings without restoring them
            if ($previous_products_sync) {
                delete_option('wpiko_chatbot_previous_products_auto_sync');
            }
            if ($previous_orders_sync) {
                delete_option('wpiko_chatbot_previous_orders_auto_sync');
            }
        }
    }
}

// Add hook to check license status on admin init
add_action('admin_init', 'wpiko_chatbot_check_woocommerce_license_status');


// Function to enable/disable WooCommerce integration - Only for Responses API
function wpiko_chatbot_toggle_woocommerce_integration($enable) {
    $current_state = get_option('wpiko_chatbot_woocommerce_integration_enabled', false);
    $new_state = $enable ? true : false;

    if ($current_state !== $new_state) {
        update_option('wpiko_chatbot_woocommerce_integration_enabled', $new_state);
        if ($new_state) {
            if (wpiko_chatbot_pro_is_main_wc_active()) {
                // Update Responses API instructions
                wpiko_chatbot_update_responses_woo_instructions(true);
            }
        } else {
            // Disable auto-sync options
            update_option('wpiko_chatbot_products_auto_sync', 'disabled');
            update_option('wpiko_chatbot_orders_auto_sync', 'disabled');

            // Get current system instructions
            $instructions = wpiko_chatbot_get_system_instructions();
            
            // Clear both products and orders instructions
            $instructions['products'] = '';
            $instructions['orders'] = '';
            
            // Update system instructions
            wpiko_chatbot_update_system_instructions(
                $instructions['main'],
                $instructions['specific'],
                $instructions['knowledge'],
                $instructions['products'],
                $instructions['orders']
            );
            
            // Delete Responses API files
            $responses_products_file_id = get_option('wpiko_chatbot_responses_woo_file_id', '');
            if ($responses_products_file_id && function_exists('wpiko_chatbot_delete_responses_file')) {
                $result = wpiko_chatbot_delete_responses_file($responses_products_file_id);
                if ($result['success']) {
                    delete_option('wpiko_chatbot_responses_woo_file_id');
                    $messages[] = 'WooCommerce products file deleted successfully from Responses API.';
                } else {
                    wpiko_chatbot_log_error("Failed to delete WooCommerce products file from Responses API: " . $responses_products_file_id);
                }
            }

            $responses_orders_file_id = get_option('wpiko_chatbot_responses_orders_file_id', '');
            if ($responses_orders_file_id && function_exists('wpiko_chatbot_delete_responses_file')) {
                $result = wpiko_chatbot_delete_responses_file($responses_orders_file_id);
                if ($result['success']) {
                    delete_option('wpiko_chatbot_responses_orders_file_id');
                } else {
                    wpiko_chatbot_log_error("Failed to delete WooCommerce orders file from Responses API: " . $responses_orders_file_id);
                }
            }

            // Clear the scheduled cron job
            wp_clear_scheduled_hook('wpiko_chatbot_sync_products');

            // Remove instructions from Responses API
            wpiko_chatbot_update_responses_woo_instructions(false);
        }
    }

    return $new_state;
}

// Function to update assistant with woo products instructions
// Helper function to get woo products instructions template
function wpiko_chatbot_get_woo_products_instructions_template() {
    return "Find products data with these key fields:
- id, name, description, short_description, price, regular_price, sale_price, sku, stock_status, categories, tags, link
- attributes: An object with product-specific details like Color, Size, Material

Each attribute (e.g., Color) is a key in the attributes object, with an array of available options as its value.

To find products with specific attributes, search the relevant array in the attributes object. For example, to find a red product, look for \"red\" in the attributes.Color array.";
}

// Helper function to get woo orders instructions template
function wpiko_chatbot_get_woo_orders_instructions_template() {
    return "Find orders data with these key fields:
- id, date_created, date_paid, date_completed, date_last_status_change, status, total, first_name, billing_email, order_note, tracking_number, tracking_link, items, total

Provide orders data only if customer provide you with Order number, or Email address.
Never provide orders email.";
}

// Function to update woo instructions - Only for Responses API
function wpiko_chatbot_update_responses_woo_instructions($add_instructions) {
    // Get system instructions
    $instructions = wpiko_chatbot_get_system_instructions();
    
    // Prepare products instructions
    $woo_products_instructions = wpiko_chatbot_get_woo_products_instructions_template();
    
    // Prepare orders instructions
    $woo_orders_instructions = wpiko_chatbot_get_woo_orders_instructions_template();
    
    if ($add_instructions) {
        $instructions['products'] = $woo_products_instructions;
        $instructions['orders'] = $woo_orders_instructions;
    } else {
        $instructions['products'] = '';
        $instructions['orders'] = '';
    }
    
    // Update system instructions in database
    $result = wpiko_chatbot_update_system_instructions(
        $instructions['main'],
        $instructions['specific'],
        $instructions['knowledge'],
        $instructions['products'],
        $instructions['orders']
    );
    
    if (!$result) {
        wpiko_chatbot_log_error("Failed to update system instructions in database for Responses API");
        return false;
    }
    
    return true;
}

// Function to get product field preferences
function wpiko_chatbot_get_product_fields_options() {
    $defaults = array(
        'id' => true,
        'name' => true,
        'description' => false,
        'short_description' => true,
        'price' => true,
        'regular_price' => true,
        'sale_price' => true,
        'sku' => true,
        'stock_status' => true,
        'categories' => true,
        'tags' => true,
        'attributes' => true,
        'link' => true,
    );
    
    $options = get_option('wpiko_chatbot_product_fields_options', array());
    return wp_parse_args($options, $defaults);
}

// Function to update product field preferences
function wpiko_chatbot_update_product_fields_options($options) {
    return update_option('wpiko_chatbot_product_fields_options', $options);
}

// Function to format product data
function wpiko_chatbot_format_product_data($product) {
    $categories = wp_list_pluck(get_the_terms($product->get_id(), 'product_cat') ?: array(), 'name');
    $tags = wp_list_pluck(get_the_terms($product->get_id(), 'product_tag') ?: array(), 'name');
    
    // Format attributes
    $formatted_attributes = array();
    $attributes = $product->get_attributes();
    foreach ($attributes as $attribute) {
        if ($attribute->is_taxonomy()) {
            $attribute_name = wc_attribute_label($attribute->get_name());
            $attribute_terms = wp_list_pluck(wc_get_product_terms($product->get_id(), $attribute->get_name()), 'name');
            $formatted_attributes[$attribute_name] = $attribute_terms;
        } else {
            $attribute_name = $attribute->get_name();
            $attribute_value = $attribute->get_options();
            $formatted_attributes[$attribute_name] = $attribute_value;
        }
    }
    
    // Get field preferences
    $field_options = wpiko_chatbot_get_product_fields_options();
    
    // Create the base product data array
    $product_data = array();
    
    // Only include fields that are enabled
    if ($field_options['id']) {
        $product_data['id'] = $product->get_id();
    }
    
    if ($field_options['name']) {
        $product_data['name'] = $product->get_name();
    }
    
    if ($field_options['description']) {
        $product_data['description'] = $product->get_description();
    }
    
    if ($field_options['short_description']) {
        $product_data['short_description'] = $product->get_short_description();
    }
    
    if ($field_options['price']) {
        $product_data['price'] = $product->get_price();
    }
    
    if ($field_options['regular_price']) {
        $product_data['regular_price'] = $product->get_regular_price();
    }
    
    if ($field_options['sale_price']) {
        $product_data['sale_price'] = $product->get_sale_price();
    }
    
    if ($field_options['sku']) {
        $product_data['sku'] = $product->get_sku();
    }
    
    if ($field_options['stock_status']) {
        $product_data['stock_status'] = $product->get_stock_status();
    }
    
    if ($field_options['categories']) {
        $product_data['categories'] = $categories;
    }
    
    if ($field_options['tags']) {
        $product_data['tags'] = $tags;
    }
    
    if ($field_options['attributes']) {
        $product_data['attributes'] = $formatted_attributes;
    }
    
    if ($field_options['link']) {
        $product_data['link'] = get_permalink($product->get_id());
    }
    
    return $product_data;
}

// Store the current WooCommerce file ID
function wpiko_chatbot_get_woo_file_id() {
    return get_option('wpiko_chatbot_woo_file_id', '');
}

function wpiko_chatbot_set_woo_file_id($file_id) {
    update_option('wpiko_chatbot_woo_file_id', $file_id);
}

// Function to sync existing products
function wpiko_chatbot_get_formatted_products_data($offset = 0, $limit = 100) {
    $args = array(
        'status' => 'publish',
        'limit' => $limit,
        'offset' => $offset,
    );
    $products = wc_get_products($args);
    $formatted_data = array();
    foreach ($products as $product) {
        $formatted_data[] = wpiko_chatbot_format_product_data($product);
    }
    return $formatted_data;
}

/**
 * Stream product batches directly to a temp JSON file instead of accumulating in memory.
 * Returns the temp file path on success, or false on failure.
 * Accepts an optional callback for progress output (used by WP-CLI).
 */
function wpiko_chatbot_build_products_file($progress_callback = null) {
    $batch_size = 100;
    $offset = 0;
    $total_products = wp_count_posts('product')->publish;
    $processed_products = 0;

    if ($total_products === 0) {
        return false;
    }

    // Create temp file and open a file handle for streaming
    $temp_file_path = wp_tempnam('woocommerce_products');
    if (!$temp_file_path) {
        wpiko_chatbot_log_error('Failed to create temp file for products.');
        return false;
    }

    $fh = @fopen($temp_file_path, 'w');
    if (!$fh) {
        wpiko_chatbot_log_error('Failed to open temp file for writing.');
        @unlink($temp_file_path);
        return false;
    }

    // Write JSON array opening bracket
    fwrite($fh, '[');
    $first = true;

    do {
        $formatted_data = wpiko_chatbot_get_formatted_products_data($offset, $batch_size);
        
        foreach ($formatted_data as $product_data) {
            if (!$first) {
                fwrite($fh, ',');
            }
            $json_item = json_encode($product_data);
            if ($json_item === false) {
                continue; // Skip items that can't be encoded
            }
            fwrite($fh, $json_item);
            $first = false;
        }
        
        $processed_products += count($formatted_data);
        $offset += $batch_size;

        // Update progress
        $progress = min(99, round(($processed_products / max(1, $total_products)) * 100));
        update_option('wpiko_chatbot_sync_progress', $progress);

        // Call progress callback if provided (WP-CLI)
        if (is_callable($progress_callback)) {
            call_user_func($progress_callback, $processed_products, $total_products, $progress);
        }

        // Free memory between batches
        unset($formatted_data);
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

    } while ($processed_products < $total_products && $offset < 50000); // Safety limit

    // Write JSON array closing bracket
    fwrite($fh, ']');
    fclose($fh);

    wpiko_chatbot_log_error("Built products file with {$processed_products} products.");
    return $temp_file_path;
}

/**
 * Upload a temp product JSON file to OpenAI and handle old file cleanup.
 * Returns true on success, false on failure.
 */
function wpiko_chatbot_upload_products_file($temp_file_path) {
    $file_data = [
        'tmp_name' => $temp_file_path,
        'name' => 'woocommerce_products.json',
        'type' => 'application/json',
    ];

    // Upload to Responses API
    $result = wpiko_chatbot_upload_file_to_responses($file_data);

    // Update Responses API instructions if needed
    if (!get_option('wpiko_chatbot_responses_woo_file_id', '')) {
        wpiko_chatbot_update_responses_woo_instructions(true);
    }

    if ($result['success']) {
        $new_file_id = $result['file_id'];

        // Delete the old file if it exists
        $old_file_id = get_option('wpiko_chatbot_responses_woo_file_id', '');
        if ($old_file_id && function_exists('wpiko_chatbot_delete_responses_file')) {
            $delete_result = wpiko_chatbot_delete_responses_file($old_file_id);
            wpiko_chatbot_log_error("Attempted to delete old Responses file. Result: " . ($delete_result['success'] ? 'Success' : 'Failed'));
        }
        update_option('wpiko_chatbot_responses_woo_file_id', $new_file_id);

        // Clean up the temporary file
        wpiko_chatbot_delete_temp_file($temp_file_path);

        // Clear file cache to ensure fresh data is loaded
        if (function_exists('wpiko_chatbot_clear_file_cache')) {
            wpiko_chatbot_clear_file_cache();
        }

        wpiko_chatbot_log_error("Upload completed successfully. New file ID: " . $new_file_id);
        return true;
    } else {
        wpiko_chatbot_delete_temp_file($temp_file_path);
        wpiko_chatbot_log_error('Failed to upload products file to OpenAI: ' . $result['message']);
        return false;
    }
}

// Improved function to sync existing products (memory-efficient streaming)
function wpiko_chatbot_sync_existing_products() {
    if (!wpiko_chatbot_get_lock('product_sync', 300)) { // 5 minutes timeout
        wpiko_chatbot_log_error("Product sync is already in progress.");
        return false;
    }

    // Extend PHP execution time for large catalogs on shared hosting
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    try {
        wpiko_chatbot_log_error("Starting sync of existing products");
        
        // Set initial progress
        update_option('wpiko_chatbot_sync_progress', 0);
        update_option('wpiko_chatbot_sync_status', 'running');
        
        $total_products = wp_count_posts('product')->publish;
        if ($total_products === 0) {
            wpiko_chatbot_log_error("No products found to sync");
            update_option('wpiko_chatbot_sync_status', 'completed');
            update_option('wpiko_chatbot_sync_progress', 100);
            wpiko_chatbot_release_lock('product_sync');
            return true;
        }

        // Build products file using memory-efficient streaming
        $temp_file_path = wpiko_chatbot_build_products_file();
        if (!$temp_file_path) {
            throw new Exception('Failed to build products file');
        }

        // Upload to OpenAI
        $upload_result = wpiko_chatbot_upload_products_file($temp_file_path);
        if (!$upload_result) {
            throw new Exception('Failed to upload products file to OpenAI');
        }

        // Reset progress
        update_option('wpiko_chatbot_sync_progress', 100);
        update_option('wpiko_chatbot_sync_status', 'completed');
        update_option('wpiko_chatbot_last_sync_time', current_time('mysql'));

        wpiko_chatbot_log_error("Sync completed successfully.");
        wpiko_chatbot_release_lock('product_sync');
        return true;
        
    } catch (Exception $e) {
        wpiko_chatbot_log_error('Error in sync products: ' . $e->getMessage());
        update_option('wpiko_chatbot_sync_error', $e->getMessage());
        update_option('wpiko_chatbot_sync_status', 'failed');
        
        // Reset progress
        update_option('wpiko_chatbot_sync_progress', 0);
        wpiko_chatbot_release_lock('product_sync');
        return false;
    }
}

// Fallback sync is no longer needed — the main sync now uses memory-efficient streaming.
// Kept as a thin wrapper for backward compatibility.
function wpiko_chatbot_fallback_sync_products() {
    wpiko_chatbot_log_error("Fallback sync called — delegating to main streaming sync.");
    // The main sync already uses streaming; just return false to let the caller handle it.
    return false;
}

// Function to get the sync progress
function wpiko_chatbot_get_sync_progress() {
    return get_option('wpiko_chatbot_sync_progress', 0);
}

// Function to get the sync status
function wpiko_chatbot_get_sync_status() {
    $status = array(
        'progress' => intval(get_option('wpiko_chatbot_sync_progress', 0)),
        'status' => get_option('wpiko_chatbot_sync_status', ''),
        'error' => get_option('wpiko_chatbot_sync_error', ''),
        'last_sync' => get_option('wpiko_chatbot_last_sync_time', ''),
        'lock_active' => !wpiko_chatbot_get_lock('product_sync', 0, true)
    );
    
    return $status;
}

// Function to download JSON file
function wpiko_chatbot_download_products_json() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    if (!wpiko_chatbot_pro_is_main_wc_active()) {
        wp_send_json_error(array('message' => 'WooCommerce is not active'));
    }

    $batch_size = 10; // Adjust this value based on your needs
    $offset = 0;
    $all_formatted_data = array();
    $total_products = wp_count_posts('product')->publish;
    $processed_products = 0;

    try {
        do {
            $formatted_data = wpiko_chatbot_get_formatted_products_data($offset, $batch_size);
            $all_formatted_data = array_merge($all_formatted_data, $formatted_data);
            $processed_products += count($formatted_data);
            $offset += $batch_size;

            $progress = round(($processed_products / $total_products) * 100);
            update_option('wpiko_chatbot_download_progress', $progress);

        } while (count($formatted_data) == $batch_size);

        update_option('wpiko_chatbot_download_progress', 100);
        wp_send_json_success(array('products' => $all_formatted_data));
    } catch (Exception $e) {
        wpiko_chatbot_log('Error in wpiko_chatbot_download_products_json: ' . $e->getMessage(), 'error');
        wp_send_json_error(array('message' => 'An error occurred while generating the JSON.'));
    }
}
add_action('wp_ajax_download_products_json', 'wpiko_chatbot_download_products_json');

// Function to perform the product sync
function wpiko_chatbot_perform_product_sync() {
    $auto_update = get_option('wpiko_chatbot_products_auto_sync', 'disabled');
    if ($auto_update !== 'disabled') {
        wpiko_chatbot_log_error("Auto-update cron job triggered. Syncing products...");
        $sync_result = wpiko_chatbot_sync_existing_products();
        if ($sync_result) {
            wpiko_chatbot_log_error("Products synced successfully via cron job.");
        } else {
            wpiko_chatbot_log_error("Failed to sync products via cron job.");
        }
    } else {
        wpiko_chatbot_log_error("Auto-update is disabled. Skipping cron job sync.");
    }
}
add_action('wpiko_chatbot_sync_products', 'wpiko_chatbot_perform_product_sync');

// Function to clean up the scheduled event when the plugin is deactivated
function wpiko_chatbot_deactivation() {
    $timestamp = wp_next_scheduled('wpiko_chatbot_sync_products');
    wp_unschedule_event($timestamp, 'wpiko_chatbot_sync_products');
    wp_clear_scheduled_hook('wpiko_chatbot_sync_orders');
}
register_deactivation_hook(__FILE__, 'wpiko_chatbot_deactivation');

// AJAX handler for toggling WooCommerce integration
function wpiko_chatbot_toggle_woocommerce_integration_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $enabled = isset($_POST['enabled']) ? filter_var(wp_unslash($_POST['enabled']), FILTER_VALIDATE_BOOLEAN) : false;

    try {
        $result = wpiko_chatbot_toggle_woocommerce_integration($enabled);
        $current_state = wpiko_chatbot_is_woocommerce_integration_enabled();
        
        if ($current_state) {
            $message = 'WooCommerce Integration enabled successfully.';
        } else {
            $message = 'WooCommerce Integration disabled successfully.';
        }
        
        wp_send_json_success(array(
            'enabled' => $current_state, 
            'message' => $message
        ));
    } catch (Exception $e) {
        wpiko_chatbot_log_error('Error toggling WooCommerce integration - ' . $e->getMessage());
        wp_send_json_error(array('message' => 'An error occurred: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_toggle_woocommerce_integration', 'wpiko_chatbot_toggle_woocommerce_integration_ajax');

// AJAX handler for syncing existing products - Button
function wpiko_chatbot_sync_existing_products_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Reset any previous errors
    update_option('wpiko_chatbot_sync_error', '');
    
    // Check if another sync is in progress
    if (!wpiko_chatbot_get_lock('product_sync', 300, true)) {
        wp_send_json_error(array(
            'message' => 'Another sync process is already running. Please wait for it to complete.',
            'status' => wpiko_chatbot_get_sync_status()
        ));
        return;
    }
    
    // Set background processing if supported
    if (function_exists('wp_schedule_single_event')) {
        update_option('wpiko_chatbot_sync_status', 'scheduled');
        update_option('wpiko_chatbot_sync_progress', 0);
        
        wp_schedule_single_event(time(), 'wpiko_chatbot_background_sync');
        
        // Trigger cron immediately — prevents stalling when WP-Cron is deferred
        if (function_exists('spawn_cron')) {
            spawn_cron();
        }
        
        wp_send_json_success(array(
            'message' => 'Sync process scheduled in background.',
            'status' => wpiko_chatbot_get_sync_status()
        ));
    } else {
        // Direct sync (may timeout on large catalogs)
        $result = wpiko_chatbot_sync_existing_products();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Sync completed successfully.',
                'status' => wpiko_chatbot_get_sync_status()
            ));
        } else {
            $error = get_option('wpiko_chatbot_sync_error', 'Unknown error occurred');
            wp_send_json_error(array(
                'message' => 'Sync failed: ' . $error,
                'status' => wpiko_chatbot_get_sync_status()
            ));
        }
    }
}
add_action('wp_ajax_sync_existing_products', 'wpiko_chatbot_sync_existing_products_ajax');

// Function to handle the sync process
function wpiko_chatbot_handle_sync_process() {
    if (get_option('wpiko_chatbot_sync_in_progress', false)) {
        $result = wpiko_chatbot_sync_existing_products();
        update_option('wpiko_chatbot_sync_in_progress', false);
        if ($result) {
            wpiko_chatbot_log_error("Sync process completed successfully.");
        } else {
            wpiko_chatbot_log_error("Sync process failed.");
        }
    }
}
add_action('wpiko_chatbot_sync_products_event', 'wpiko_chatbot_handle_sync_process');

// Background processing hook
function wpiko_chatbot_run_background_sync() {
    wpiko_chatbot_sync_existing_products();
}
add_action('wpiko_chatbot_background_sync', 'wpiko_chatbot_run_background_sync');

// Enhanced check sync progress AJAX handler
function wpiko_chatbot_check_sync_progress_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $status = wpiko_chatbot_get_sync_status();
    
    wp_send_json_success($status);
}
add_action('wp_ajax_check_sync_progress', 'wpiko_chatbot_check_sync_progress_ajax');

// AJAX action to check the download progress
function wpiko_chatbot_check_download_progress() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $progress = get_option('wpiko_chatbot_download_progress', 0);
    wp_send_json_success(array('progress' => $progress));
}
add_action('wp_ajax_check_download_progress', 'wpiko_chatbot_check_download_progress');

// Function products auto update
function wpiko_chatbot_update_products_auto_sync() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $frequency = isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : 'disabled';
    update_option('wpiko_chatbot_products_auto_sync', $frequency);

    if ($frequency !== 'disabled') {
        wp_clear_scheduled_hook('wpiko_chatbot_sync_products');
        wp_schedule_event(time(), $frequency, 'wpiko_chatbot_sync_products');
        
        // Trigger an immediate sync
        wpiko_chatbot_perform_product_sync();
    } else {
        wp_clear_scheduled_hook('wpiko_chatbot_sync_products');
    }

    wp_send_json_success(array('message' => 'Products auto-update setting updated successfully.'));
}
add_action('wp_ajax_update_products_auto_sync', 'wpiko_chatbot_update_products_auto_sync');

// Initialize the WooCommerce integration when the plugin is loaded
function wpiko_chatbot_init_woocommerce_integration() {
    static $initialized = false;

    if ($initialized) {
        return;
    }

    if (wpiko_chatbot_pro_is_main_wc_active() && wpiko_chatbot_is_woocommerce_integration_enabled()) {
        $auto_update = get_option('wpiko_chatbot_products_auto_sync', 'disabled');
        if ($auto_update !== 'disabled' && !wp_next_scheduled('wpiko_chatbot_sync_products')) {
            wp_schedule_event(time(), $auto_update, 'wpiko_chatbot_sync_products');
        }
    } else {
        wp_clear_scheduled_hook('wpiko_chatbot_sync_products');
    }

    $initialized = true;
}
add_action('plugins_loaded', 'wpiko_chatbot_init_woocommerce_integration');

add_action('plugins_loaded', function() {
    static $initialized = false;

    if ($initialized) {
        return;
    }

    if (class_exists('WooCommerce')) {
        wpiko_chatbot_init_woocommerce_integration();
    } else {
        add_action('woocommerce_loaded', function() {
            wpiko_chatbot_init_woocommerce_integration();
        });
    }

    $initialized = true;
});

// WooCommerce Orders Integration
function wpiko_chatbot_get_formatted_orders_data($limit = 100) {
    $orders = wc_get_orders(array(
        'limit' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
        'type' => 'shop_order', // Exclude refunds (OrderRefund objects lack standard order methods)
    ));

    // Get field preferences
    $field_options = wpiko_chatbot_get_order_fields_options();

    $formatted_data = array();
    foreach ($orders as $order) {
        // Safety check: skip any non-standard order objects (e.g. refunds)
        if (!($order instanceof \WC_Order) || ($order instanceof \WC_Order_Refund)) {
            continue;
        }
        $tracking_number = wpiko_chatbot_get_tracking_number($order);
        $tracking_link = wpiko_chatbot_generate_aftership_link($tracking_number);
        
        // Get the date of the last status change
        $status_changes = wc_get_order_notes(array(
            'order_id' => $order->get_id(),
            'type' => 'order_status_change'
        ));
        $last_status_change_date = !empty($status_changes) ? $status_changes[0]->date_created->format('Y-m-d H:i:s') : null;
        
        // Get the note to customer
        $customer_notes = wc_get_order_notes(array(
            'order_id' => $order->get_id(),
            'type' => 'customer'
        ));
        $note_to_customer = !empty($customer_notes) ? $customer_notes[0]->content : '';
        
        // Create order data array with only selected fields
        $order_data = array();
        
        if ($field_options['id']) {
            $order_data['id'] = $order->get_id();
        }
        
        if ($field_options['date_created']) {
            $order_data['date_created'] = $order->get_date_created()->format('Y-m-d H:i:s');
        }
        
        if ($field_options['date_paid']) {
            $order_data['date_paid'] = $order->get_date_paid() ? $order->get_date_paid()->format('Y-m-d H:i:s') : null;
        }
        
        if ($field_options['date_completed']) {
            $order_data['date_completed'] = $order->get_date_completed() ? $order->get_date_completed()->format('Y-m-d H:i:s') : null;
        }
        
        if ($field_options['date_last_status_change']) {
            $order_data['date_last_status_change'] = $last_status_change_date;
        }
        
        if ($field_options['status']) {
            $order_data['status'] = $order->get_status();
        }
        
        if ($field_options['total']) {
            $order_data['total'] = $order->get_total();
        }
        
        if ($field_options['customer_id']) {
            $order_data['customer_id'] = $order->get_customer_id();
        }
        
        if ($field_options['first_name']) {
            $order_data['first_name'] = $order->get_billing_first_name();
        }
        
        if ($field_options['billing_email']) {
            $order_data['billing_email'] = $order->get_billing_email();
        }
        
        if ($field_options['order_note']) {
            $order_data['order_note'] = $order->get_customer_note();
        }
        
        if ($field_options['note_to_customer']) {
            $order_data['note_to_customer'] = $note_to_customer;
        }
        
        if ($field_options['payment_method']) {
            $order_data['payment_method'] = $order->get_payment_method_title();
        }
        
        if ($field_options['tracking_number']) {
            $order_data['tracking_number'] = $tracking_number;
        }
        
        if ($field_options['tracking_link']) {
            $order_data['tracking_link'] = $tracking_link;
        }
        
        if ($field_options['items']) {
            $order_data['items'] = array_map(function($item) {
                return array(
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total()
                );
            }, $order->get_items());
        }
        
        $formatted_data[] = $order_data;
    }
    return $formatted_data;
}

// Function to get tracking number
function wpiko_chatbot_get_tracking_number($order) {
    $tracking_number = '';

    // Check for AfterShip plugin
    $aftership_tracking_items = $order->get_meta('_aftership_tracking_items');
    if (!empty($aftership_tracking_items) && is_array($aftership_tracking_items)) {
        $tracking_numbers = array();
        foreach ($aftership_tracking_items as $tracking_item) {
            if (isset($tracking_item['tracking_number'])) {
                $tracking_numbers[] = $tracking_item['tracking_number'];
            }
        }
        $tracking_number = implode(', ', $tracking_numbers);
    }

    // If no tracking number found, proceed with other checks
    if (empty($tracking_number)) {
        
        // Check for Advanced Shipment Tracking plugin
        if (class_exists('WC_Advanced_Shipment_Tracking_Actions')) {
            $ast = new WC_Advanced_Shipment_Tracking_Actions();
            $tracking_items = $ast->get_tracking_items($order->get_id());
            if (!empty($tracking_items)) {
                $tracking_numbers = array();
                foreach ($tracking_items as $tracking_item) {
                    $tracking_numbers[] = $tracking_item['tracking_number'];
                }
                $tracking_number = implode(', ', $tracking_numbers);
            }
        }
       
        if (empty($tracking_number)) {
            
            // Check for WooCommerce Shipment Tracking plugin
            $wc_shipment_tracking = $order->get_meta('_wc_shipment_tracking_items');
            if (!empty($wc_shipment_tracking) && is_array($wc_shipment_tracking)) {
                $tracking_numbers = array();
                foreach ($wc_shipment_tracking as $tracking_item) {
                    if (isset($tracking_item['tracking_number'])) {
                        $tracking_numbers[] = $tracking_item['tracking_number'];
                    }
                }
                $tracking_number = implode(', ', $tracking_numbers);
            }
        }

        // Check common meta keys
        $common_meta_keys = array('tracking_number');
        foreach ($common_meta_keys as $meta_key) {
            if (empty($tracking_number)) {
                $tracking_number = $order->get_meta($meta_key);
                if (!empty($tracking_number)) break;
            }
        }
    }

    return $tracking_number;
}

function wpiko_chatbot_generate_aftership_link($tracking_number) {
    if (empty($tracking_number)) {
        return '';
    }
    return 'https://track.aftership.com/' . urlencode($tracking_number);
}

// Function to sync orders with retry and backoff
function wpiko_chatbot_sync_orders($retry_count = 0) {
    wpiko_chatbot_log_error('[Orders Sync] Starting orders sync process...');

    if (!wpiko_chatbot_get_lock('order_sync', 120)) { // 2 minutes timeout
        wpiko_chatbot_log_error('[Orders Sync] FAILED: Lock could not be acquired — another sync is already in progress or a stale lock exists.');
        return false;
    }

    // Extend PHP execution time for shared hosting
    if (function_exists('set_time_limit')) {
        @set_time_limit(120);
    }

    $max_retries = 3;
    $sync_option = get_option('wpiko_chatbot_orders_auto_sync', 'disabled');
    wpiko_chatbot_log_error('[Orders Sync] Current sync option value: ' . $sync_option);
    if ($sync_option === 'disabled') {
        wpiko_chatbot_log_error('[Orders Sync] FAILED: Sync option is disabled. This can happen if the license was deactivated or the option was reset between scheduling and execution.');
        wpiko_chatbot_release_lock('order_sync');
        return false;
    }

    $limit = intval($sync_option);
    
    try {
        wpiko_chatbot_log_error('[Orders Sync] Fetching formatted orders data (limit: ' . $limit . ')...');
        $orders_data = wpiko_chatbot_get_formatted_orders_data($limit);
        
        if (empty($orders_data)) {
            wpiko_chatbot_log_error('[Orders Sync] FAILED: No orders found to sync (0 orders returned from WooCommerce).');
            wpiko_chatbot_release_lock('order_sync');
            return false;
        }
        wpiko_chatbot_log_error('[Orders Sync] Retrieved ' . count($orders_data) . ' orders.');

        // Write data to temp file using helper (handles WP_Filesystem fallback)
        $json_data = json_encode($orders_data);
        if ($json_data === false) {
            wpiko_chatbot_log_error('[Orders Sync] FAILED: json_encode failed. JSON error: ' . json_last_error_msg());
            wpiko_chatbot_release_lock('order_sync');
            return false;
        }
        wpiko_chatbot_log_error('[Orders Sync] JSON encoded successfully (' . strlen($json_data) . ' bytes).');
        
        $temp_file_path = wpiko_chatbot_write_temp_file('woocommerce_orders', $json_data);
        if (!$temp_file_path) {
            wpiko_chatbot_log_error('[Orders Sync] FAILED: Could not write temp file. Check PHP temp directory permissions.');
            wpiko_chatbot_release_lock('order_sync');
            return false;
        }
        wpiko_chatbot_log_error('[Orders Sync] Temp file written: ' . $temp_file_path);

        $file_data = [
            'tmp_name' => $temp_file_path,
            'name' => 'woocommerce_orders.json',
            'type' => 'application/json',
        ];

        // Retry upload loop with exponential backoff (no recursion)
        $upload_success = false;
        $last_error = '';
        
        wpiko_chatbot_log_error('[Orders Sync] Starting upload to OpenAI (max retries: ' . $max_retries . ')...');
        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            if ($attempt > 0) {
                $backoff = min(30, pow(2, $attempt)); // 2s, 4s, 8s... max 30s
                wpiko_chatbot_log_error("[Orders Sync] Upload retry #{$attempt}, waiting {$backoff}s...");
                sleep($backoff);
            }
            
            wpiko_chatbot_log_error('[Orders Sync] Upload attempt #' . $attempt . '...');
            $result = wpiko_chatbot_upload_file_to_responses($file_data);
            
            if ($result['success']) {
                $upload_success = true;
                wpiko_chatbot_log_error('[Orders Sync] Upload succeeded on attempt #' . $attempt . '.');
                break;
            }
            
            $last_error = isset($result['message']) ? $result['message'] : 'Unknown upload error';
            wpiko_chatbot_log_error("[Orders Sync] Upload attempt #{$attempt} failed: " . $last_error);
        }
        
        // Clean up the temporary file regardless of result
        wpiko_chatbot_delete_temp_file($temp_file_path);

        if ($upload_success) {
            $new_file_id = $result['file_id'];
            
            // Delete the old file if it exists
            $old_file_id = get_option('wpiko_chatbot_responses_orders_file_id', '');
            if ($old_file_id && function_exists('wpiko_chatbot_delete_responses_file')) {
                wpiko_chatbot_log_error('[Orders Sync] Deleting old file: ' . $old_file_id);
                wpiko_chatbot_delete_responses_file($old_file_id);
            }
            
            // Update Responses API instructions if this is the first sync (no previous file)
            $had_previous_file = !empty($old_file_id);
            
            update_option('wpiko_chatbot_responses_orders_file_id', $new_file_id);
            
            if (!$had_previous_file) {
                wpiko_chatbot_log_error('[Orders Sync] First sync — updating Responses API instructions.');
                wpiko_chatbot_update_responses_woo_instructions(true);
            }
            
            // Clear file cache to ensure fresh data is loaded
            if (function_exists('wpiko_chatbot_clear_file_cache')) {
                wpiko_chatbot_clear_file_cache();
            }
            
            wpiko_chatbot_log_error('[Orders Sync] SUCCESS. New File ID: ' . $new_file_id);
            wpiko_chatbot_release_lock('order_sync');
            return true;
        } else {
            wpiko_chatbot_log_error('[Orders Sync] FAILED: Upload failed after ' . ($max_retries + 1) . ' attempts. Last error: ' . $last_error);
            wpiko_chatbot_release_lock('order_sync');
            return false;
        }
        
    } catch (Exception $e) {
        wpiko_chatbot_log_error('[Orders Sync] EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        wpiko_chatbot_release_lock('order_sync');
        return false;
    }
}

function wpiko_chatbot_update_orders_auto_sync() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $sync_option = isset($_POST['sync_option']) ? sanitize_text_field(wp_unslash($_POST['sync_option'])) : 'disabled';
    update_option('wpiko_chatbot_orders_auto_sync', $sync_option);

    $instructions_updated = wpiko_chatbot_update_responses_woo_instructions($sync_option !== 'disabled');

    if ($sync_option !== 'disabled') {
        // Schedule orders sync in background to avoid AJAX timeout on live/shared hosting
        update_option('wpiko_chatbot_orders_sync_status', 'scheduled');
        wp_schedule_single_event(time(), 'wpiko_chatbot_background_orders_sync');
        
        // Attempt to trigger cron immediately via spawn_cron
        if (function_exists('spawn_cron')) {
            spawn_cron();
        }
        
        wp_send_json_success(array(
            'message' => 'Orders sync setting updated. Sync is running in the background. ' . ($instructions_updated ? 'Instructions updated.' : 'Failed to update instructions.'),
            'sync_option' => $sync_option,
            'sync_status' => 'scheduled'
        ));
    } else {
        // Delete the woocommerce_orders.json file
        update_option('wpiko_chatbot_orders_sync_status', 'disabled');
        $file_id = get_option('wpiko_chatbot_responses_orders_file_id', '');
        if ($file_id && function_exists('wpiko_chatbot_delete_responses_file')) {
            $delete_result = wpiko_chatbot_delete_responses_file($file_id);
            if ($delete_result['success']) {
                delete_option('wpiko_chatbot_responses_orders_file_id');
                
                wp_send_json_success(array(
                    'message' => 'Orders sync disabled and file deleted successfully. ' . ($instructions_updated ? 'Instructions updated.' : 'Failed to update instructions.'),
                    'sync_option' => 'disabled'
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Orders sync disabled, but failed to delete the file. ' . ($instructions_updated ? 'Instructions updated.' : 'Failed to update instructions.'),
                    'sync_option' => 'disabled'
                ));
            }
        } else {
            wp_send_json_success(array(
                'message' => 'Orders sync disabled successfully. ' . ($instructions_updated ? 'Instructions updated.' : 'Failed to update instructions.'),
                'sync_option' => 'disabled'
            ));
        }
    }
}

// Background orders sync handler
function wpiko_chatbot_run_background_orders_sync() {
    wpiko_chatbot_log_error('[Background Sync] Orders background sync event fired.');
    
    // Extend execution time for background processing
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    
    update_option('wpiko_chatbot_orders_sync_status', 'running');
    
    // Register shutdown function to catch fatal errors
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE))) {
            $status = get_option('wpiko_chatbot_orders_sync_status', '');
            if ($status === 'running') {
                update_option('wpiko_chatbot_orders_sync_status', 'failed');
                update_option('wpiko_chatbot_orders_sync_error', 'PHP Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
                if (function_exists('wpiko_chatbot_log_error')) {
                    wpiko_chatbot_log_error('[Background Sync] FATAL ERROR: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
                }
            }
        }
    });
    
    $result = wpiko_chatbot_sync_orders();
    
    if ($result) {
        update_option('wpiko_chatbot_orders_sync_status', 'completed');
        update_option('wpiko_chatbot_orders_last_sync_time', current_time('mysql'));
        delete_option('wpiko_chatbot_orders_sync_error');
        wpiko_chatbot_log_error('[Background Sync] Orders sync completed successfully.');
    } else {
        update_option('wpiko_chatbot_orders_sync_status', 'failed');
        // Capture the last logged error for the UI
        $sync_option = get_option('wpiko_chatbot_orders_auto_sync', 'disabled');
        $error_detail = 'Sync returned false. Check the debug log for [Orders Sync] entries. Current sync option: ' . $sync_option;
        update_option('wpiko_chatbot_orders_sync_error', $error_detail);
        wpiko_chatbot_log_error('[Background Sync] Orders sync failed. Sync option at time of failure: ' . $sync_option);
    }
}
add_action('wpiko_chatbot_background_orders_sync', 'wpiko_chatbot_run_background_orders_sync');

// AJAX handler to check orders sync status (used by JS polling)
function wpiko_chatbot_check_orders_sync_status_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $status = get_option('wpiko_chatbot_orders_sync_status', '');
    $last_sync = get_option('wpiko_chatbot_orders_last_sync_time', '');
    $error = get_option('wpiko_chatbot_orders_sync_error', '');
    
    wp_send_json_success(array(
        'status' => $status,
        'last_sync' => $last_sync,
        'error' => $error
    ));
}
add_action('wp_ajax_check_orders_sync_status', 'wpiko_chatbot_check_orders_sync_status_ajax');

// Function debounced sync orders
function wpiko_chatbot_debounced_sync_orders() {
    if (wpiko_chatbot_get_lock('order_sync_debounce', 30)) { // 30 seconds debounce
        wp_schedule_single_event(time() + 60, 'wpiko_chatbot_delayed_sync_orders');
    }
}

function wpiko_chatbot_delayed_sync_orders() {
    wpiko_chatbot_sync_orders();
}

// Debounced hooks
add_action('woocommerce_new_order', 'wpiko_chatbot_debounced_sync_orders');
add_action('woocommerce_order_status_changed', 'wpiko_chatbot_debounced_sync_orders');
add_action('woocommerce_update_order', 'wpiko_chatbot_debounced_sync_orders');
add_action('wpiko_chatbot_delayed_sync_orders', 'wpiko_chatbot_delayed_sync_orders');

// Orders auto sync hook
add_action('wp_ajax_update_orders_auto_sync', 'wpiko_chatbot_update_orders_auto_sync');

// Function to update assistant with woo orders instructions
function wpiko_chatbot_update_assistant_order_instructions($add_instructions) {
    return wpiko_chatbot_update_responses_woo_instructions($add_instructions);
}

// Download Orders JSON" button
function wpiko_chatbot_download_orders_json() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    if (!wpiko_chatbot_pro_is_main_wc_active()) {
        wp_send_json_error(array('message' => 'WooCommerce is not active'));
    }

    $sync_option = get_option('wpiko_chatbot_orders_auto_sync', 'disabled');
    $limit = ($sync_option === 'disabled') ? 100 : intval($sync_option);

    try {
        $orders_data = wpiko_chatbot_get_formatted_orders_data($limit);
        wp_send_json_success(array('orders' => $orders_data));
    } catch (Exception $e) {
        wpiko_chatbot_log('Error in wpiko_chatbot_download_orders_json: ' . $e->getMessage(), 'error');
        wp_send_json_error(array('message' => 'An error occurred while generating the JSON.'));
    }
}
add_action('wp_ajax_download_orders_json', 'wpiko_chatbot_download_orders_json');

// AJAX handler to get system instructions
function wpiko_chatbot_get_system_instructions_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Get the system instructions from the database
    $instructions = wpiko_chatbot_get_system_instructions();
    
    wp_send_json_success($instructions);
}
add_action('wp_ajax_get_system_instructions', 'wpiko_chatbot_get_system_instructions_ajax');

// AJAX handler for updating product field options
function wpiko_chatbot_update_product_fields() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    // Properly unslash, sanitize and validate the fields array
    $fields = isset($_POST['fields']) ? map_deep(wp_unslash($_POST['fields']), 'sanitize_text_field') : array();
    // Ensure $fields is an array
    $fields = is_array($fields) ? $fields : array();
    $field_options = array();
    
    // Process each field option
    $valid_fields = array('id', 'name', 'description', 'short_description', 'price', 'regular_price', 'sale_price', 'sku', 'stock_status', 'categories', 'tags', 'attributes', 'link');
    
    foreach ($valid_fields as $field) {
        $field_options[$field] = isset($fields[$field]) && filter_var($fields[$field], FILTER_VALIDATE_BOOLEAN);
    }
    
    // Update options in database
    $result = wpiko_chatbot_update_product_fields_options($field_options);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Product field options updated successfully.',
            'options' => $field_options
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to update product field options.'));
    }
}
add_action('wp_ajax_update_product_fields', 'wpiko_chatbot_update_product_fields');

// Function to get order field preferences
function wpiko_chatbot_get_order_fields_options() {
    $defaults = array(
        'id' => true,
        'date_created' => true,
        'date_paid' => true,
        'date_completed' => true,
        'date_last_status_change' => true,
        'status' => true,
        'total' => true,
        'customer_id' => true,
        'first_name' => true,
        'billing_email' => true, 
        'order_note' => true,
        'note_to_customer' => true,
        'payment_method' => true,
        'tracking_number' => true,
        'tracking_link' => true,
        'items' => true,
    );
    
    $options = get_option('wpiko_chatbot_order_fields_options', array());
    return wp_parse_args($options, $defaults);
}

// Function to update order field preferences
function wpiko_chatbot_update_order_fields_options($options) {
    return update_option('wpiko_chatbot_order_fields_options', $options);
}

// AJAX handler for updating order field options
function wpiko_chatbot_update_order_fields() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    // Properly unslash, sanitize and validate the fields array
    $fields = isset($_POST['fields']) ? map_deep(wp_unslash($_POST['fields']), 'sanitize_text_field') : array();
    // Ensure $fields is an array
    $fields = is_array($fields) ? $fields : array();
    $field_options = array();
    
    // Process each field option
    $valid_fields = array('id', 'date_created', 'date_paid', 'date_completed', 'date_last_status_change', 'status', 'total', 'customer_id', 'first_name', 'billing_email', 'order_note', 'note_to_customer', 'payment_method', 'tracking_number', 'tracking_link', 'items');
    
    foreach ($valid_fields as $field) {
        $field_options[$field] = isset($fields[$field]) && filter_var($fields[$field], FILTER_VALIDATE_BOOLEAN);
    }
    
    // Update options in database
    $result = wpiko_chatbot_update_order_fields_options($field_options);
    
    if ($result) {
        // Force a sync of orders with new field settings
        if (get_option('wpiko_chatbot_orders_auto_sync', 'disabled') !== 'disabled') {
            wpiko_chatbot_sync_orders();
        }
        
        wp_send_json_success(array(
            'message' => 'Order field options updated successfully.',
            'options' => $field_options
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to update order field options.'));
    }
}
add_action('wp_ajax_update_order_fields', 'wpiko_chatbot_update_order_fields');

// Helper: Check if main plugin function exists before using
function wpiko_chatbot_pro_is_main_wc_active() {
    return function_exists('wpiko_chatbot_is_woocommerce_active') && wpiko_chatbot_is_woocommerce_active();
}
