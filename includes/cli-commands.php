<?php
/**
 * WP-CLI commands for WPiko Chatbot Pro WooCommerce sync.
 *
 * Usage:
 *   wp wpiko-chatbot sync products     — Sync all published WooCommerce products to OpenAI
 *   wp wpiko-chatbot sync orders       — Sync recent WooCommerce orders to OpenAI
 *   wp wpiko-chatbot sync status       — Show current sync status
 *
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_CLI')) {
    return;
}

/**
 * Manage WPiko Chatbot WooCommerce synchronization.
 */
class WPiko_Chatbot_CLI_Command {

    /**
     * Sync WooCommerce data to OpenAI.
     *
     * ## OPTIONS
     *
     * <type>
     * : What to sync. Accepted values: products, orders, status.
     *
     * ## EXAMPLES
     *
     *     # Sync all products
     *     wp wpiko-chatbot sync products
     *
     *     # Sync recent orders
     *     wp wpiko-chatbot sync orders
     *
     *     # Check current sync status
     *     wp wpiko-chatbot sync status
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function sync($args, $assoc_args) {
        $type = isset($args[0]) ? $args[0] : '';

        switch ($type) {
            case 'products':
                $this->sync_products();
                break;
            case 'orders':
                $this->sync_orders();
                break;
            case 'status':
                $this->sync_status();
                break;
            default:
                WP_CLI::error("Unknown sync type '{$type}'. Use: products, orders, or status.");
        }
    }

    /**
     * Sync products to OpenAI via CLI (no timeout, real-time progress).
     */
    private function sync_products() {
        // Pre-flight checks
        if (!function_exists('wpiko_chatbot_pro_is_main_wc_active') || !wpiko_chatbot_pro_is_main_wc_active()) {
            WP_CLI::error('WooCommerce is not active. Please activate WooCommerce first.');
        }

        if (!function_exists('wpiko_chatbot_is_woocommerce_integration_enabled') || !wpiko_chatbot_is_woocommerce_integration_enabled()) {
            WP_CLI::error('WooCommerce Integration is not enabled. Enable it in WPiko Chatbot → AI Configuration → WooCommerce Integration.');
        }

        if (!function_exists('wpiko_chatbot_is_license_active') || !wpiko_chatbot_is_license_active()) {
            WP_CLI::error('WPiko Chatbot Pro license is not active.');
        }

        // Acquire lock
        if (!wpiko_chatbot_get_lock('product_sync', 600)) { // 10 minutes for CLI
            WP_CLI::error('Another product sync is already in progress. Use "wp wpiko-chatbot sync status" to check.');
        }

        // Remove PHP time limit in CLI context
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $total_products = wp_count_posts('product')->publish;
        if ($total_products === 0) {
            wpiko_chatbot_release_lock('product_sync');
            WP_CLI::warning('No published products found.');
            return;
        }

        WP_CLI::log("Found {$total_products} published products. Starting sync...");
        WP_CLI::log('');

        update_option('wpiko_chatbot_sync_progress', 0);
        update_option('wpiko_chatbot_sync_status', 'running');
        update_option('wpiko_chatbot_sync_error', '');

        // Build the products file with progress bar
        $progress = \WP_CLI\Utils\make_progress_bar('Processing products', $total_products);

        $products_processed = 0;
        $progress_callback = function($processed, $total, $pct) use ($progress, &$products_processed) {
            $new_ticks = $processed - $products_processed;
            for ($i = 0; $i < $new_ticks; $i++) {
                $progress->tick();
            }
            $products_processed = $processed;
        };

        $temp_file_path = wpiko_chatbot_build_products_file($progress_callback);

        // Finish any remaining ticks
        $remaining = $total_products - $products_processed;
        for ($i = 0; $i < $remaining; $i++) {
            $progress->tick();
        }
        $progress->finish();

        if (!$temp_file_path) {
            update_option('wpiko_chatbot_sync_status', 'failed');
            update_option('wpiko_chatbot_sync_error', 'Failed to build products file.');
            wpiko_chatbot_release_lock('product_sync');
            WP_CLI::error('Failed to build products file.');
        }

        // Show file size
        $file_size = filesize($temp_file_path);
        WP_CLI::log(sprintf('Products file built: %s', size_format($file_size)));
        WP_CLI::log('Uploading to OpenAI...');

        $upload_result = wpiko_chatbot_upload_products_file($temp_file_path);

        if ($upload_result) {
            update_option('wpiko_chatbot_sync_progress', 100);
            update_option('wpiko_chatbot_sync_status', 'completed');
            update_option('wpiko_chatbot_last_sync_time', current_time('mysql'));
            wpiko_chatbot_release_lock('product_sync');

            WP_CLI::success("Successfully synced {$total_products} products to OpenAI.");
        } else {
            update_option('wpiko_chatbot_sync_status', 'failed');
            update_option('wpiko_chatbot_sync_error', 'Upload to OpenAI failed.');
            wpiko_chatbot_release_lock('product_sync');
            WP_CLI::error('Failed to upload products file to OpenAI. Check the debug log for details.');
        }
    }

    /**
     * Sync orders to OpenAI via CLI.
     */
    private function sync_orders() {
        // Pre-flight checks
        if (!function_exists('wpiko_chatbot_pro_is_main_wc_active') || !wpiko_chatbot_pro_is_main_wc_active()) {
            WP_CLI::error('WooCommerce is not active.');
        }

        if (!function_exists('wpiko_chatbot_is_woocommerce_integration_enabled') || !wpiko_chatbot_is_woocommerce_integration_enabled()) {
            WP_CLI::error('WooCommerce Integration is not enabled.');
        }

        if (!function_exists('wpiko_chatbot_is_license_active') || !wpiko_chatbot_is_license_active()) {
            WP_CLI::error('WPiko Chatbot Pro license is not active.');
        }

        $sync_option = get_option('wpiko_chatbot_orders_auto_sync', 'disabled');
        if ($sync_option === 'disabled') {
            WP_CLI::error('Orders auto-sync is disabled. Enable it first in the admin panel (WooCommerce Integration → Recent Orders Auto-Sync).');
        }

        $limit = intval($sync_option);
        WP_CLI::log("Syncing the {$limit} most recent orders...");

        // Remove PHP time limit in CLI context
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $result = wpiko_chatbot_sync_orders();

        if ($result) {
            WP_CLI::success("Successfully synced orders to OpenAI.");
        } else {
            WP_CLI::error('Orders sync failed. Check the debug log for details.');
        }
    }

    /**
     * Display current sync status.
     */
    private function sync_status() {
        WP_CLI::log('--- Product Sync Status ---');
        $product_status = wpiko_chatbot_get_sync_status();
        
        $status_map = array(
            'running'   => 'Running',
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'completed_fallback' => 'Completed (fallback)',
            'failed'    => 'Failed',
        );

        $status_label = isset($status_map[$product_status['status']]) ? $status_map[$product_status['status']] : ($product_status['status'] ?: 'None');
        
        WP_CLI::log("  Status:    {$status_label}");
        WP_CLI::log("  Progress:  {$product_status['progress']}%");
        
        if ($product_status['last_sync']) {
            WP_CLI::log("  Last sync: {$product_status['last_sync']}");
        }
        if ($product_status['error']) {
            WP_CLI::log("  Error:     {$product_status['error']}");
        }
        if ($product_status['lock_active']) {
            WP_CLI::log("  Lock:      Active (sync in progress)");
        }

        $products_file_id = get_option('wpiko_chatbot_responses_woo_file_id', '');
        WP_CLI::log("  File ID:   " . ($products_file_id ?: 'None'));
        
        WP_CLI::log('');
        WP_CLI::log('--- Orders Sync Status ---');
        
        $orders_status = get_option('wpiko_chatbot_orders_sync_status', '');
        $orders_last = get_option('wpiko_chatbot_orders_last_sync_time', '');
        $orders_auto = get_option('wpiko_chatbot_orders_auto_sync', 'disabled');
        $orders_file_id = get_option('wpiko_chatbot_responses_orders_file_id', '');
        
        WP_CLI::log("  Auto-sync: {$orders_auto}");
        WP_CLI::log("  Status:    " . ($orders_status ?: 'None'));
        if ($orders_last) {
            WP_CLI::log("  Last sync: {$orders_last}");
        }
        WP_CLI::log("  File ID:   " . ($orders_file_id ?: 'None'));
        
        WP_CLI::log('');
        
        $total_products = wp_count_posts('product')->publish;
        WP_CLI::log("Total published products: {$total_products}");
        
        $wc_integration = wpiko_chatbot_is_woocommerce_integration_enabled() ? 'Enabled' : 'Disabled';
        WP_CLI::log("WooCommerce Integration:  {$wc_integration}");
    }
}

WP_CLI::add_command('wpiko-chatbot', 'WPiko_Chatbot_CLI_Command');
