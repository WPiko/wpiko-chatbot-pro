<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<!-- WooCommerce Integration - Section -->
<?php if (wpiko_chatbot_is_woocommerce_active()): ?>
<div id="woocommerce-integration-section">
    <h3>
        <span class="dashicons dashicons-update-alt"></span> 
        WooCommerce Integration 
        <?php if (!wpiko_chatbot_is_license_active()): ?>
            <span class="premium-feature-badge">Premium</span>
        <?php endif; ?>
    </h3>
    <div id="woocommerce-integration-content">
        <?php 
        // Get current license status
        $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
        $is_license_expired = $license_status === 'expired';
        
        // Check if there are any existing WooCommerce files
        $has_woo_files = false;
        $responses_vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');
        if ($responses_vector_store_id && function_exists('wpiko_chatbot_list_responses_files')) {
            $files_result = wpiko_chatbot_list_responses_files();
            if ($files_result['success']) {
                foreach ($files_result['files'] as $file) {
                    if (strpos($file['filename'], 'woocommerce_') === 0) {
                        $has_woo_files = true;
                        break;
                    }
                }
            }
        }
        ?>

        <?php if (wpiko_chatbot_is_license_active()): ?>
            <p class="description">Integrate your WooCommerce products and orders with the AI Assistant knowledge base.</p>
        <table class="form-table">
        
        <tr valign="top">
            <th scope="row">
                Enable WooCommerce Integration 
                <?php if (!wpiko_chatbot_is_license_active()): ?>
                    <span class="premium-feature-badge">Premium</span>
                <?php endif; ?>
            </th>
            <td>
                <?php if (wpiko_chatbot_is_license_active()): ?>
                    <label class="toggle-switch">
                        <input type="checkbox" id="woocommerce_integration_enabled" name="woocommerce_integration_enabled" value="1" <?php checked(wpiko_chatbot_is_woocommerce_integration_enabled()); ?>>
                        <span class="slider"></span>
                    </label>
                    <label for="woocommerce_integration_enabled">Enable integration with WooCommerce</label>
                    <div id="woocommerce-integration-status"></div>
                <?php else: ?>
                    <input type="checkbox" disabled>
                    <label>Enable integration with WooCommerce</label>
                    <p class="description">This feature requires a premium license. <a href="?page=ai-chatbot&tab=license_activation">Upgrade now</a> to enable WooCommerce integration.</p>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- Product Data Fields Selection - Collapsible Tab -->
        <tr valign="top">
            <th scope="row">
                <div class="collapsible-header" id="product-fields-toggle">
                    Product Data Fields 
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
            </th>
            <td>
                <div class="collapsible-content" id="product-fields-content" style="display:none;">
                    <p class="description">Choose which product data fields to include when syncing with the <?php echo esc_html($api_display_name); ?>.</p>
                    
                    <?php 
                    $field_options = wpiko_chatbot_get_product_fields_options(); 
                    $fields = array(
                        'id' => 'Product ID',
                        'name' => 'Product Name',
                        'description' => 'Full Description',
                        'short_description' => 'Short Description',
                        'price' => 'Price',
                        'regular_price' => 'Regular Price',
                        'sale_price' => 'Sale Price',
                        'sku' => 'SKU',
                        'stock_status' => 'Stock Status',
                        'categories' => 'Categories',
                        'tags' => 'Tags',
                        'attributes' => 'Attributes',
                        'link' => 'Product URL'
                    );
                    ?>
                    
                    <div class="product-fields-grid">
                        <?php foreach ($fields as $field_key => $field_label): ?>
                            <div class="product-field-option">
                                <input type="checkbox" 
                                       id="product_field_<?php echo esc_attr($field_key); ?>" 
                                       name="product_fields[<?php echo esc_attr($field_key); ?>]" 
                                       value="1" 
                                       <?php checked(isset($field_options[$field_key]) ? $field_options[$field_key] : true); ?>>
                                <label for="product_field_<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field_label); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" id="save_product_fields" class="button button-secondary">Save Field Options</button>
                    <span id="product_fields_status" class="status-message"></span>
                </div>
                <div class="collapsible-summary" id="product-fields-summary">
                    <?php
                    $enabled_count = 0;
                    $total_fields = count($fields);
                    foreach ($field_options as $key => $enabled) {
                        if ($enabled) $enabled_count++;
                    }
                    ?>
                    <span><?php echo sprintf('%d of %d fields selected', esc_html($enabled_count), esc_html($total_fields)); ?></span>
                </div>
                
            </td>
        </tr>
        
        <tr valign="top">
            <th scope="row"><label for="sync_existing_products">Sync Products Manually</label></th>
            <td>
                <button type="button" id="sync_existing_products" class="button button-secondary">Sync Existing Products</button>
                <p class="description">Click to manually synchronize all existing WooCommerce products with the <?php echo esc_html($api_display_name); ?>.</p>
                <span id="sync_status"></span>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Product Catalog Auto-Sync</th>
            <td>
                <select name="products_auto_sync" id="products_auto_sync">
                    <option value="disabled" <?php selected(get_option('wpiko_chatbot_products_auto_sync', 'disabled'), 'disabled'); ?>>Disabled</option>
                    <option value="hourly" <?php selected(get_option('wpiko_chatbot_products_auto_sync', 'disabled'), 'hourly'); ?>>Hourly</option>
                    <option value="twicedaily" <?php selected(get_option('wpiko_chatbot_products_auto_sync', 'disabled'), 'twicedaily'); ?>>Twice Daily</option>
                    <option value="daily" <?php selected(get_option('wpiko_chatbot_products_auto_sync', 'disabled'), 'daily'); ?>>Daily</option>
                    <option value="weekly" <?php selected(get_option('wpiko_chatbot_products_auto_sync', 'disabled'), 'weekly'); ?>>Weekly</option>
                </select>
                <p class="description">Select how often the product sync should occur. For large catalogs (500+ products), we recommend using weekly frequent syncs to optimize performance. More frequent syncs are suitable for smaller catalogs or when rapid product updates are necessary.</p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">
                <div class="collapsible-header" id="order-fields-toggle">
                    Order Data Fields 
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
            </th>
            <td>
                <div class="collapsible-content" id="order-fields-content" style="display:none;">
                    <p class="description">Choose which order data fields to include when syncing with the <?php echo esc_html($api_display_name); ?>.</p>
                    
                    <?php 
                    $field_options = wpiko_chatbot_get_order_fields_options(); 
                    $fields = array(
                        'id' => 'Order ID',
                        'date_created' => 'Date Created',
                        'date_paid' => 'Date Paid',
                        'date_completed' => 'Date Completed',
                        'date_last_status_change' => 'Last Status Change Date',
                        'status' => 'Order Status',
                        'total' => 'Order Total',
                        'customer_id' => 'Customer ID',
                        'first_name' => 'Customer First Name',
                        'billing_email' => 'Customer Email',
                        'order_note' => 'Order Note',
                        'note_to_customer' => 'Note to Customer',
                        'payment_method' => 'Payment Method',
                        'tracking_number' => 'Tracking Number',
                        'tracking_link' => 'Tracking Link',
                        'items' => 'Order Items'
                    );
                    ?>
                    
                    <div class="order-fields-grid">
                        <?php foreach ($fields as $field_key => $field_label): ?>
                            <div class="order-field-option">
                                <input type="checkbox" 
                                       id="order_field_<?php echo esc_attr($field_key); ?>" 
                                       name="order_fields[<?php echo esc_attr($field_key); ?>]" 
                                       value="1" 
                                       <?php checked(isset($field_options[$field_key]) ? $field_options[$field_key] : true); ?>>
                                <label for="order_field_<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field_label); ?></label>
                                <?php 
                                if ($field_key === 'tracking_number') {
                                    echo '<span class="info-icon tracking-number-info dashicons dashicons-info-outline"></span>';
                                } elseif ($field_key === 'billing_email') {
                                    echo '<span class="info-icon customer-email-info dashicons dashicons-info-outline"></span>';
                                }
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" id="save_order_fields" class="button button-secondary">Save Field Options</button>
                    <span id="order_fields_status" class="status-message"></span>
                </div>
                <div class="collapsible-summary" id="order-fields-summary">
                    <?php
                    $enabled_count = 0;
                    $total_fields = count($fields);
                    foreach ($field_options as $key => $enabled) {
                        if ($enabled) $enabled_count++;
                    }
                    ?>
                    <span><?php echo sprintf('%d of %d fields selected', esc_html($enabled_count), esc_html($total_fields)); ?></span>
                </div>
            </td>
        </tr>
        
        <tr valign="top">
            <th scope="row">
                Recent Orders Auto-Sync
            </th>
            <td>
                <select name="orders_auto_sync" id="orders_auto_sync">
                    <option value="disabled" <?php selected(get_option('wpiko_chatbot_orders_auto_sync', 'disabled'), 'disabled'); ?>>Disabled</option>
                    <option value="100" <?php selected(get_option('wpiko_chatbot_orders_auto_sync', 'disabled'), '100'); ?>>Recent 100 Orders</option>
                    <option value="200" <?php selected(get_option('wpiko_chatbot_orders_auto_sync', 'disabled'), '200'); ?>>Recent 200 Orders</option>
                    <option value="300" <?php selected(get_option('wpiko_chatbot_orders_auto_sync', 'disabled'), '300'); ?>>Recent 300 Orders</option>
                </select>
                <p class="description">
                    Configure the number of recent orders to sync with the AI Assistant. Once enabled, orders will automatically sync when they are placed, updated, or their status changes. This ensures the AI can provide up-to-date information about orders.
                    <strong class="note">Note:</strong> At least one order must exist in your WooCommerce store to enable this feature.
                </p>
            </td>
        </tr>

        <tr valign="top" class="download-files-option">
            <th scope="row">Download Files</th>
            <td>
                <button type="button" id="download_products_json" class="button button-secondary" <?php disabled(!wpiko_chatbot_is_woocommerce_integration_enabled()); ?>>Download Products JSON</button>
                <button type="button" id="download_orders_json" class="button button-secondary" <?php disabled(!wpiko_chatbot_is_woocommerce_integration_enabled()); ?>>Download Orders JSON</button>
                <p class="description">
                    Click to download and preview WooCommerce products or orders file without uploading to <?php echo esc_html($api_display_name); ?>. 
                    This is for preview purposes only and does not affect the AI Assistant's knowledge.
                </p>
                <span id="download_status" class="status-message"></span>
                <span id="orders_download_status" class="status-message"></span>
            </td>
        </tr>

        </table>
        
            <div class="woocommerce-files-section">
                <h3>WooCommerce List</h3>
                <p class="description">View and manage the WooCommerce products and orders information you’ve synced with the AI Assistant.</p>
                <ul id="woocommerce-files-list"></ul>
            </div>
            
        <?php elseif ($is_license_expired): ?>
            <!-- Notice for Expired License -->
            <div class="premium-feature-notice">
                <h3>🔒 WooCommerce Integration Disabled</h3>
                <p>Your license has expired. WooCommerce integration has been disabled.</p>
                <p>Renew your license to regain access to these features:</p>
                <ul>
                    <li>✨ Sync product catalog with the chatbot</li>
                    <li>📦 Track order status and provide updates</li>
                    <li>💬 Answer product-specific questions</li>
                    <li>🔄 Automatic product updates</li>
                    <li>📊 Order tracking and management</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Renew License</a>
            </div>
                
            <?php if ($has_woo_files): ?>
                <div class="woocommerce-files-view-only">
                    <div class="notice notice-warning inline">
                        <p>Your premium license has expired. While you can view your existing WooCommerce files, you'll need to <a href="?page=ai-chatbot&tab=license_activation">renew your license</a> to sync new data or manage existing files.</p>
                    </div>
                    
                    <!-- Show WooCommerce Integration Toggle when files exist -->
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Enable WooCommerce Integration</th>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="woocommerce_integration_enabled" name="woocommerce_integration_enabled" value="1" <?php checked(wpiko_chatbot_is_woocommerce_integration_enabled()); ?>>
                                    <span class="slider"></span>
                                </label>
                                <label for="woocommerce_integration_enabled">Enable integration with WooCommerce</label>
                                <div id="woocommerce-integration-status"></div>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="woocommerce-files-section">
                        <h3>WooCommerce List</h3>
                        <p class="description">View the WooCommerce products and orders information previously synced with the AI Assistant.</p>
                        <ul id="woocommerce-files-list"></ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (wpiko_chatbot_is_woocommerce_integration_enabled() && !$has_woo_files): ?>
                <div class="notice notice-warning inline">
                    <p>Your previous WooCommerce integration settings have been disabled. Renew your license to reactivate them.</p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Notice for No License -->
            <div class="premium-feature-notice">
                <h3>🛍️ Unlock WooCommerce Integration</h3>
                <p>Upgrade to Premium to enable powerful WooCommerce integration features:</p>
                <ul>
                    <li>✨ Sync product catalog with the chatbot</li>
                    <li>📦 Track order status and provide updates</li>
                    <li>💬 Answer product-specific questions</li>
                    <li>🔄 Automatic product updates</li>
                    <li>📊 Order tracking and management</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Upgrade to Premium</a>
            </div>
                
            <?php if ($has_woo_files): ?>
                <div class="woocommerce-files-view-only">
                    <div class="notice notice-warning inline">
                        <p>Your premium license has expired. While you can view your existing WooCommerce files, you'll need to <a href="?page=ai-chatbot&tab=license_activation">renew your license</a> to sync new data or manage existing files.</p>
                    </div>
                    
                    <!-- Show WooCommerce Integration Toggle when files exist -->
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Enable WooCommerce Integration</th>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="woocommerce_integration_enabled" name="woocommerce_integration_enabled" value="1" <?php checked(wpiko_chatbot_is_woocommerce_integration_enabled()); ?>>
                                    <span class="slider"></span>
                                </label>
                                <label for="woocommerce_integration_enabled">Enable integration with WooCommerce</label>
                                <div id="woocommerce-integration-status"></div>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="woocommerce-files-section">
                        <h3>WooCommerce List</h3>
                        <p class="description">View the WooCommerce products and orders information previously synced with the AI Assistant.</p>
                        <ul id="woocommerce-files-list"></ul>
                    </div>
                </div>
            <?php endif; ?>
                
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
