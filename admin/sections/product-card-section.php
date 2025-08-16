<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    return; // Exit if WooCommerce is not active
}

// Register settings
add_action('admin_init', 'wpiko_chatbot_register_product_card_settings');

function wpiko_chatbot_register_product_card_settings() {
    register_setting(
        'wpiko_chatbot_product_card_settings', 
        'wpiko_chatbot_enable_product_cards', 
        array(
            'sanitize_callback' => 'absint',
            'default' => 0
        )
    );
    register_setting(
        'wpiko_chatbot_product_card_settings', 
        'wpiko_chatbot_show_product_title', 
        array(
            'sanitize_callback' => 'absint',
            'default' => 1
        )
    );
    register_setting(
        'wpiko_chatbot_product_card_settings', 
        'wpiko_chatbot_show_product_description', 
        array(
            'sanitize_callback' => 'absint',
            'default' => 1
        )
    );
    register_setting(
        'wpiko_chatbot_product_card_settings', 
        'wpiko_chatbot_show_product_price', 
        array(
            'sanitize_callback' => 'absint',
            'default' => 1
        )
    );
}

// Add the product card options to the allowed options list - try both filters for compatibility
function wpiko_chatbot_add_product_card_allowed_options($allowed_options) {
    $allowed_options['wpiko_chatbot_product_card_settings'] = array(
        'wpiko_chatbot_enable_product_cards',
        'wpiko_chatbot_show_product_title',
        'wpiko_chatbot_show_product_description',
        'wpiko_chatbot_show_product_price'
    );
    return $allowed_options;
}
add_filter('allowed_options', 'wpiko_chatbot_add_product_card_allowed_options');

// Legacy whitelist options filter (for older WordPress versions)
function wpiko_chatbot_add_product_card_whitelist_options($whitelist_options) {
    $whitelist_options['wpiko_chatbot_product_card_settings'] = array(
        'wpiko_chatbot_enable_product_cards',
        'wpiko_chatbot_show_product_title',
        'wpiko_chatbot_show_product_description',
        'wpiko_chatbot_show_product_price'
    );
    return $whitelist_options;
}
add_filter('whitelist_options', 'wpiko_chatbot_add_product_card_whitelist_options');

function wpiko_chatbot_product_card_section() {
    // Get current product card settings
    $enable_product_cards = get_option('wpiko_chatbot_enable_product_cards', 0);
    ?>
    <div class="product-card-section">
        <div class="product-card-section-header">
            <h2>
                <span class="dashicons dashicons-align-full-width"></span> 
                Product Card
                <?php if (!wpiko_chatbot_is_license_active()): ?>
                    <span class="premium-feature-badge">Premium</span>
                <?php endif; ?>
            </h2>
            <p class="description">Customize WooCommerce product cards in chatbot responses. When enabled, products in chat appear as cards with an image, title, description, and price to enhance shopping experience.</p>
        </div>
        <div class="product-card-section-content">
        <?php 
        // Get current license status
        $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
        $is_license_expired = $license_status === 'expired';
        
        if (wpiko_chatbot_is_license_active()): 
            // Handle direct form submission for product card settings
            if (isset($_POST['wpiko_save_product_card_settings']) && check_admin_referer('wpiko_product_card_settings_nonce')) {
                // Update product card settings
                update_option('wpiko_chatbot_enable_product_cards', isset($_POST['wpiko_chatbot_enable_product_cards']) ? 1 : 0);
                update_option('wpiko_chatbot_show_product_title', isset($_POST['wpiko_chatbot_show_product_title']) ? 1 : 0);
                update_option('wpiko_chatbot_show_product_description', isset($_POST['wpiko_chatbot_show_product_description']) ? 1 : 0);
                update_option('wpiko_chatbot_show_product_price', isset($_POST['wpiko_chatbot_show_product_price']) ? 1 : 0);
                
                echo '<div class="notice notice-success is-dismissible"><p>Product card settings saved successfully.</p></div>';
                
                // Refresh the current settings
                $enable_product_cards = get_option('wpiko_chatbot_enable_product_cards', 0);
            }
        ?>
            <form method="post">
                <?php wp_nonce_field('wpiko_product_card_settings_nonce'); ?>
                <table class="form-table">
                    <tr valign="top" class="enable-product-cards-row">
                        <th scope="row">Enable Product Cards</th>
                        <td>
                            <label class="wpiko-switch">
                                <input type="checkbox" id="wpiko_enable_product_cards" name="wpiko_chatbot_enable_product_cards" value="1" <?php checked(1, $enable_product_cards, true); ?>>
                                <span class="wpiko-slider round"></span>
                            </label>
                            <label for="wpiko_enable_product_cards">Enable the product cards feature in the chatbot</label>
                            <p class="description">When enabled, products in chat appear as cards with an image, title, description, and price to enhance shopping experience.</p>
                        </td>
                    </tr>
                </table>

                <div class="product-card-settings-collapsible <?php echo $enable_product_cards ? 'active' : ''; ?>">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Show Product Title</th>
                            <td>
                                <label class="wpiko-switch">
                                    <input type="checkbox" name="wpiko_chatbot_show_product_title" value="1" <?php checked(1, get_option('wpiko_chatbot_show_product_title', 1), true); ?>>
                                    <span class="wpiko-slider round"></span>
                                </label>
                                <p class="description">Show or hide product titles in product cards.</p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">Show Product Description</th>
                            <td>
                                <label class="wpiko-switch">
                                    <input type="checkbox" name="wpiko_chatbot_show_product_description" value="1" <?php checked(1, get_option('wpiko_chatbot_show_product_description', 1), true); ?>>
                                    <span class="wpiko-slider round"></span>
                                </label>
                                <p class="description">Show or hide product descriptions in product cards.</p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">Show Product Price</th>
                            <td>
                                <label class="wpiko-switch">
                                    <input type="checkbox" name="wpiko_chatbot_show_product_price" value="1" <?php checked(1, get_option('wpiko_chatbot_show_product_price', 1), true); ?>>
                                    <span class="wpiko-slider round"></span>
                                </label>
                                <p class="description">Show or hide product prices in product cards.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="wpiko_save_product_card_settings" class="button button-primary" value="Save Changes">
                </p>
            </form>

            <script>
                jQuery(document).ready(function($) {
                    // Toggle product card settings visibility based on checkbox state
                    $('#wpiko_enable_product_cards').change(function() {
                        if ($(this).is(':checked')) {
                            $('.product-card-settings-collapsible').addClass('active');
                        } else {
                            $('.product-card-settings-collapsible').removeClass('active');
                        }
                    });
                });
            </script>
        <?php elseif ($is_license_expired): ?>
            <div class="premium-feature-notice">
                <h3>🔒 Product Cards Disabled</h3>
                <p>Your license has expired. Product card feature has been disabled.</p>
                <p>Renew your license to regain access to these features:</p>
                <ul>
                    <li>✨ Show product cards in chat responses</li>
                    <li>📝 Display product images, titles, and descriptions</li>
                    <li>💰 Show product prices in chat</li>
                    <li>🛍️ Enhance shopping experience</li>
                    <li>💼 Professional product presentation</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Renew License</a>
            </div>
        <?php else: ?>
            <div class="premium-feature-notice">
                <h3>🛍️ Unlock Product Cards</h3>
                <p>Upgrade to Premium to enhance your chatbot with beautiful product cards:</p>
                <ul>
                    <li>✨ Show product cards in chat responses</li>
                    <li>📝 Display product images, titles, and descriptions</li>
                    <li>💰 Show product prices in chat</li>
                    <li>🛍️ Enhance shopping experience</li>
                    <li>💼 Professional product presentation</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Upgrade to Premium</a>
            </div>
        <?php endif; ?>
        </div>
    </div>
    <?php
}
