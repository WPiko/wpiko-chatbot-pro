<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<!-- WooCommerce Integration content (loaded into the shared popup shell) -->
<?php if (wpiko_chatbot_is_woocommerce_active()): ?>
<div id="woocommerce-integration-section">
    <div id="woocommerce-integration-content" class="wpiko-modal-stack">
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

        <?php if (wpiko_chatbot_is_license_active()):
            $integration_enabled = wpiko_chatbot_is_woocommerce_integration_enabled();
            $product_field_options = wpiko_chatbot_get_product_fields_options();
            $product_fields = array(
                'name' => 'Product name',
                'id' => 'Product ID',
                'sku' => 'SKU',
                'link' => 'Product URL',
                'price' => 'Price',
                'regular_price' => 'Regular price',
                'sale_price' => 'Sale price',
                'stock_status' => 'Stock status',
                'short_description' => 'Short description',
                'description' => 'Full description',
                'categories' => 'Categories',
                'tags' => 'Tags',
                'attributes' => 'Attributes',
            );
            $product_enabled_count = 0;
            foreach ($product_fields as $key => $label) {
                if (isset($product_field_options[$key]) ? $product_field_options[$key] : true) {
                    $product_enabled_count++;
                }
            }

            $order_field_options = wpiko_chatbot_get_order_fields_options();
            $order_fields = array(
                'date_created' => 'Date created',
                'date_paid' => 'Date paid',
                'date_completed' => 'Date completed',
                'total' => 'Order total',
                'items' => 'Order items',
                'tracking_number' => 'Tracking number',
                'tracking_link' => 'Tracking link',
            );
            $order_enabled_count = 0;
            foreach ($order_fields as $key => $label) {
                if (isset($order_field_options[$key]) ? $order_field_options[$key] : true) {
                    $order_enabled_count++;
                }
            }

            $auto_sync = get_option('wpiko_chatbot_products_auto_sync', 'disabled');
            $order_lookup = (bool) get_option('wpiko_chatbot_order_lookup_enabled', false);
        ?>
            <section class="wpiko-modal-section wpiko-woo-enable" aria-label="Integration">
                <label class="wpiko-chatbot-settings-switch" for="woocommerce_integration_enabled">
                    <input type="checkbox" id="woocommerce_integration_enabled" name="woocommerce_integration_enabled" value="1" <?php checked($integration_enabled); ?>>
                    <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                    <span class="wpiko-chatbot-settings-switch__label"><strong>Use WooCommerce data in the chatbot</strong></span>
                </label>
                <p class="description">The chatbot can recommend products, show product cards and answer questions about orders.</p>
                <div id="woocommerce-integration-status"></div>
            </section>

            <section class="wpiko-modal-section wpiko-woo-requires-enabled" aria-labelledby="wpiko-woo-catalog-title">
                <div class="wpiko-modal-section-header">
                    <h3 id="wpiko-woo-catalog-title" class="wpiko-modal-section-title">Product catalog</h3>
                </div>

                <div class="wpiko-chatbot-settings-field-row">
                    <p class="wpiko-chatbot-settings-field-label">Sync products</p>
                    <div class="wpiko-chatbot-settings-field-control">
                        <button type="button" id="sync_existing_products" class="button wpiko-chatbot-settings-save">
                            <span class="dashicons dashicons-update" aria-hidden="true"></span>
                            Sync now
                        </button>
                        <p class="wpiko-chatbot-settings-field-description">Sends your current catalog to the AI assistant's knowledge base.</p>
                        <span id="sync_status" role="status" aria-live="polite"></span>
                    </div>
                </div>

                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="products_auto_sync">Auto-sync</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <select name="products_auto_sync" id="products_auto_sync">
                            <option value="disabled" <?php selected($auto_sync, 'disabled'); ?>>Off</option>
                            <option value="hourly" <?php selected($auto_sync, 'hourly'); ?>>Hourly</option>
                            <option value="twicedaily" <?php selected($auto_sync, 'twicedaily'); ?>>Twice daily</option>
                            <option value="daily" <?php selected($auto_sync, 'daily'); ?>>Daily</option>
                            <option value="weekly" <?php selected($auto_sync, 'weekly'); ?>>Weekly</option>
                        </select>
                        <p class="wpiko-chatbot-settings-field-description">For catalogs with 500+ products, weekly is recommended.</p>
                    </div>
                </div>

                <div class="wpiko-chatbot-settings-field-row">
                    <p class="wpiko-chatbot-settings-field-label">Product data</p>
                    <div class="wpiko-chatbot-settings-field-control">
                        <div class="wpiko-woo-disclosure-row">
                            <span class="collapsible-summary" id="product-fields-summary"><span><?php echo esc_html(sprintf('%d of %d fields selected', $product_enabled_count, count($product_fields))); ?></span></span>
                            <button type="button" class="wpiko-disclosure-button collapsible-header" id="product-fields-toggle" aria-expanded="false" aria-controls="product-fields-content">Choose fields</button>
                        </div>
                        <div class="collapsible-content" id="product-fields-content" style="display:none;">
                            <div class="wpiko-chatbot-settings-checkbox-group product-fields-grid">
                                <?php foreach ($product_fields as $field_key => $field_label): ?>
                                    <label class="product-field-option" for="product_field_<?php echo esc_attr($field_key); ?>">
                                        <input type="checkbox"
                                               id="product_field_<?php echo esc_attr($field_key); ?>"
                                               name="product_fields[<?php echo esc_attr($field_key); ?>]"
                                               value="1"
                                               <?php checked(isset($product_field_options[$field_key]) ? $product_field_options[$field_key] : true); ?>>
                                        <?php echo esc_html($field_label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="wpiko-chatbot-settings-field-description">Changes save right away and apply on the next sync. Full descriptions make the product file much larger.</p>
                            <button type="button" id="save_product_fields" class="button" hidden>Save Field Options</button>
                        </div>
                        <span id="product_fields_status" class="wpiko-inline-status" role="status" aria-live="polite"></span>
                    </div>
                </div>

                <div class="wpiko-chatbot-settings-field-row download-files-option">
                    <p class="wpiko-chatbot-settings-field-label">Preview</p>
                    <div class="wpiko-chatbot-settings-field-control">
                        <button type="button" id="download_products_json" class="button" <?php disabled(!$integration_enabled); ?>>
                            <span class="dashicons dashicons-download" aria-hidden="true"></span>
                            Download products JSON
                        </button>
                        <p class="wpiko-chatbot-settings-field-description">See exactly what is sent. This does not change the assistant's knowledge.</p>
                        <span id="download_status" class="status-message" role="status" aria-live="polite"></span>
                    </div>
                </div>
            </section>

            <section class="wpiko-modal-section wpiko-woo-requires-enabled" aria-labelledby="wpiko-woo-orders-title">
                <div class="wpiko-modal-section-header">
                    <h3 id="wpiko-woo-orders-title" class="wpiko-modal-section-title">Order assistance</h3>
                </div>

                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="order_lookup_enabled">Order lookup</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <select name="order_lookup_enabled" id="order_lookup_enabled">
                            <option value="0" <?php selected($order_lookup, false); ?>>Off</option>
                            <option value="1" <?php selected($order_lookup, true); ?>>On</option>
                        </select>
                        <ul class="wpiko-woo-facts">
                            <li>Orders are looked up live in WooCommerce and are never uploaded to the knowledge base.</li>
                            <li>Signed-in customers can see the details you choose below for their own orders.</li>
                            <li>Guests must give the order number and checkout email, and only see the order status.</li>
                        </ul>
                        <p class="wpiko-woo-warning"><span class="dashicons dashicons-warning" aria-hidden="true"></span> Guest matching does not verify identity. Lookup attempts are rate limited.</p>
                    </div>
                </div>

                <div class="wpiko-chatbot-settings-field-row">
                    <p class="wpiko-chatbot-settings-field-label">Signed-in order details</p>
                    <div class="wpiko-chatbot-settings-field-control">
                        <div class="wpiko-woo-disclosure-row">
                            <span class="collapsible-summary" id="order-fields-summary"><span><?php echo esc_html(sprintf('%d of %d fields selected', $order_enabled_count, count($order_fields))); ?></span></span>
                            <button type="button" class="wpiko-disclosure-button collapsible-header" id="order-fields-toggle" aria-expanded="false" aria-controls="order-fields-content">Choose fields</button>
                        </div>
                        <div class="collapsible-content" id="order-fields-content" style="display:none;">
                            <div class="wpiko-chatbot-settings-checkbox-group order-fields-grid">
                                <?php foreach ($order_fields as $field_key => $field_label): ?>
                                    <label class="order-field-option" for="order_field_<?php echo esc_attr($field_key); ?>">
                                        <input type="checkbox"
                                               id="order_field_<?php echo esc_attr($field_key); ?>"
                                               name="order_fields[<?php echo esc_attr($field_key); ?>]"
                                               value="1"
                                               <?php checked(isset($order_field_options[$field_key]) ? $order_field_options[$field_key] : true); ?>>
                                        <?php echo esc_html($field_label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="wpiko-chatbot-settings-field-description">Order number and status are always included. Tracking numbers are read from AfterShip, Advanced Shipment Tracking, WooCommerce Shipment Tracking, or a custom field named <code>tracking_number</code>.</p>
                            <button type="button" id="save_order_fields" class="button" hidden>Save Field Options</button>
                        </div>
                        <span id="order_fields_status" class="wpiko-inline-status" role="status" aria-live="polite"></span>
                    </div>
                </div>
            </section>

            <section class="wpiko-modal-section woocommerce-files-section" aria-labelledby="wpiko-woo-files-title">
                <div class="wpiko-modal-section-header">
                    <h3 id="wpiko-woo-files-title" class="wpiko-modal-section-title">Synced product files <span class="wpiko-count" id="woocommerce-files-count" hidden></span></h3>
                </div>
                <ul id="woocommerce-files-list" class="wpiko-file-list"></ul>
            </section>

        <?php elseif ($is_license_expired): ?>
            <!-- Notice for Expired License -->
            <div class="premium-feature-notice">
                <h3>WooCommerce Integration Disabled</h3>
                <p>Your license has expired. WooCommerce integration has been disabled.</p>
                <p>Get a lifetime license to regain access to these features:</p>
                <ul>
                    <li>Sync product catalog with the chatbot</li>
                    <li>Track order status and provide updates</li>
                    <li>Answer product-specific questions</li>
                    <li>Automatic product updates</li>
                    <li>Controlled order status lookup</li>
                </ul>
                <a href="https://wpiko.com/chatbot-pricing/" target="_blank" rel="noopener" class="button button-primary">Get Lifetime License</a>
            </div>
                
            <?php if ($has_woo_files): ?>
                <div class="woocommerce-files-view-only wpiko-modal-section">
                    <div class="notice notice-warning inline">
                        <p>Your premium license has expired. While you can view your existing WooCommerce files, you'll need to <a href="?page=ai-chatbot&tab=license_activation">get a lifetime license</a> to sync new data or manage existing files.</p>
                    </div>
                    
                    <div class="wpiko-woo-enable">
                        <label class="wpiko-chatbot-settings-switch" for="woocommerce_integration_enabled">
                            <input type="checkbox" id="woocommerce_integration_enabled" name="woocommerce_integration_enabled" value="1" <?php checked(wpiko_chatbot_is_woocommerce_integration_enabled()); ?>>
                            <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                            <span class="wpiko-chatbot-settings-switch__label">Use WooCommerce data in the chatbot</span>
                        </label>
                        <div id="woocommerce-integration-status"></div>
                    </div>
                    
                    <div class="woocommerce-files-section">
                        <h3 class="wpiko-modal-section-title">Synced product files</h3>
                        <ul id="woocommerce-files-list" class="wpiko-file-list"></ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (wpiko_chatbot_is_woocommerce_integration_enabled() && !$has_woo_files): ?>
                <div class="notice notice-warning inline">
                    <p>Your previous WooCommerce integration settings have been disabled. Get a lifetime license to reactivate them.</p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Notice for No License -->
            <div class="premium-feature-notice">
                <h3>Unlock WooCommerce Integration</h3>
                <p>Activate Pro to enable powerful WooCommerce integration features:</p>
                <ul>
                    <li>Sync product catalog with the chatbot</li>
                    <li>Track order status and provide updates</li>
                    <li>Answer product-specific questions</li>
                    <li>Automatic product updates</li>
                    <li>Controlled order status lookup</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Activate Pro</a>
            </div>
                
            <?php if ($has_woo_files): ?>
                <div class="woocommerce-files-view-only wpiko-modal-section">
                    <div class="notice notice-warning inline">
                        <p>Your premium license has expired. While you can view your existing WooCommerce files, you'll need to <a href="?page=ai-chatbot&tab=license_activation">get a lifetime license</a> to sync new data or manage existing files.</p>
                    </div>
                    
                    <div class="wpiko-woo-enable">
                        <label class="wpiko-chatbot-settings-switch" for="woocommerce_integration_enabled">
                            <input type="checkbox" id="woocommerce_integration_enabled" name="woocommerce_integration_enabled" value="1" <?php checked(wpiko_chatbot_is_woocommerce_integration_enabled()); ?>>
                            <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                            <span class="wpiko-chatbot-settings-switch__label">Use WooCommerce data in the chatbot</span>
                        </label>
                        <div id="woocommerce-integration-status"></div>
                    </div>
                    
                    <div class="woocommerce-files-section">
                        <h3 class="wpiko-modal-section-title">Synced product files</h3>
                        <ul id="woocommerce-files-list" class="wpiko-file-list"></ul>
                    </div>
                </div>
            <?php endif; ?>
                
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
