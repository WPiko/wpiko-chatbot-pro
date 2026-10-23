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


function wpiko_chatbot_product_card_section() {
    $enable_product_cards = get_option('wpiko_chatbot_enable_product_cards', 0);
    $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
    $is_license_expired = $license_status === 'expired';
    $settings_saved = false;

    if (wpiko_chatbot_is_license_active()) {
        // Handle direct form submission for product card settings
        if (isset($_POST['wpiko_save_product_card_settings']) && check_admin_referer('wpiko_product_card_settings_nonce')) {
            // Update product card settings
            update_option('wpiko_chatbot_enable_product_cards', isset($_POST['wpiko_chatbot_enable_product_cards']) ? 1 : 0);
            update_option('wpiko_chatbot_show_product_title', isset($_POST['wpiko_chatbot_show_product_title']) ? 1 : 0);
            update_option('wpiko_chatbot_show_product_description', isset($_POST['wpiko_chatbot_show_product_description']) ? 1 : 0);
            update_option('wpiko_chatbot_show_product_price', isset($_POST['wpiko_chatbot_show_product_price']) ? 1 : 0);

            $settings_saved = true;

            // Refresh the current settings
            $enable_product_cards = get_option('wpiko_chatbot_enable_product_cards', 0);
        }
    }
    ?>
    <div class="wpiko-chatbot-settings-page wpiko-product-card-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>Product Card<?php if (!wpiko_chatbot_is_license_active()): ?> <span class="product-card-pro-badge">PRO</span><?php endif; ?></h1>
            <p class="wpiko-chatbot-settings-subtitle">Show WooCommerce products as cards in chatbot responses and choose which product details visitors see.</p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p>Product card settings saved successfully.</p></div>
        <?php endif; ?>
        <?php if (wpiko_chatbot_is_license_active()): ?>
            <form method="post" class="wpiko-chatbot-settings-form">
                <?php wp_nonce_field('wpiko_product_card_settings_nonce'); ?>
                <section class="wpiko-chatbot-settings-card" aria-labelledby="product-card-settings-title">
                    <h2 class="wpiko-chatbot-settings-card-title" id="product-card-settings-title">Product card settings</h2>
                    <div class="wpiko-chatbot-settings-field-row">
                        <div class="wpiko-chatbot-settings-field-label">Product cards</div>
                        <div class="wpiko-chatbot-settings-field-control">
                            <label class="wpiko-chatbot-settings-switch" for="wpiko_enable_product_cards">
                                <input type="checkbox" role="switch" id="wpiko_enable_product_cards" name="wpiko_chatbot_enable_product_cards" value="1" aria-controls="product-card-content" aria-describedby="product-cards-description" <?php checked(1, $enable_product_cards, true); ?>>
                                <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                                <span class="wpiko-chatbot-settings-switch__label">Enable product cards</span>
                            </label>
                            <p class="wpiko-chatbot-settings-field-description" id="product-cards-description">Display products as cards with an image and the product details selected below.</p>
                        </div>
                    </div>
                </section>
                <section class="wpiko-chatbot-settings-card" id="product-card-content" aria-labelledby="product-card-content-title" <?php if (!$enable_product_cards): ?>hidden<?php endif; ?>>
                    <h2 class="wpiko-chatbot-settings-card-title" id="product-card-content-title">Card content</h2>
                    <div class="wpiko-chatbot-settings-field-row">
                        <div class="wpiko-chatbot-settings-field-label">Product title</div>
                        <div class="wpiko-chatbot-settings-field-control">
                            <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_show_product_title">
                                <input type="checkbox" role="switch" id="wpiko_chatbot_show_product_title" name="wpiko_chatbot_show_product_title" value="1" aria-describedby="wpiko_chatbot_show_product_title-description" <?php checked(1, get_option('wpiko_chatbot_show_product_title', 1), true); ?>>
                                <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                                <span class="wpiko-chatbot-settings-switch__label">Show product titles</span>
                            </label>
                            <p class="wpiko-chatbot-settings-field-description" id="wpiko_chatbot_show_product_title-description">Display the product name in each card.</p>
                        </div>
                    </div>
                    <div class="wpiko-chatbot-settings-field-row">
                        <div class="wpiko-chatbot-settings-field-label">Product description</div>
                        <div class="wpiko-chatbot-settings-field-control">
                            <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_show_product_description">
                                <input type="checkbox" role="switch" id="wpiko_chatbot_show_product_description" name="wpiko_chatbot_show_product_description" value="1" aria-describedby="wpiko_chatbot_show_product_description-description" <?php checked(1, get_option('wpiko_chatbot_show_product_description', 1), true); ?>>
                                <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                                <span class="wpiko-chatbot-settings-switch__label">Show product descriptions</span>
                            </label>
                            <p class="wpiko-chatbot-settings-field-description" id="wpiko_chatbot_show_product_description-description">Include the product description in each card.</p>
                        </div>
                    </div>
                    <div class="wpiko-chatbot-settings-field-row">
                        <div class="wpiko-chatbot-settings-field-label">Product price</div>
                        <div class="wpiko-chatbot-settings-field-control">
                            <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_show_product_price">
                                <input type="checkbox" role="switch" id="wpiko_chatbot_show_product_price" name="wpiko_chatbot_show_product_price" value="1" aria-describedby="wpiko_chatbot_show_product_price-description" <?php checked(1, get_option('wpiko_chatbot_show_product_price', 1), true); ?>>
                                <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                                <span class="wpiko-chatbot-settings-switch__label">Show product prices</span>
                            </label>
                            <p class="wpiko-chatbot-settings-field-description" id="wpiko_chatbot_show_product_price-description">Display the WooCommerce product price in each card.</p>
                        </div>
                    </div>
                </section>
                <div class="wpiko-chatbot-settings-actions">
                    <input type="submit" name="wpiko_save_product_card_settings" class="button wpiko-chatbot-settings-save" value="Save Changes">
                </div>
            </form>
            <script>
                jQuery(function($) {
                    $('#wpiko_enable_product_cards').on('change', function() {
                        $('#product-card-content').prop('hidden', !this.checked);
                    });
                });
            </script>
        <?php elseif ($is_license_expired): ?>
            <section class="wpiko-chatbot-settings-card wpiko-chatbot-settings-upgrade" aria-labelledby="product-card-expired-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="product-card-expired-title">Product Cards Disabled</h2>
                <div class="wpiko-chatbot-settings-notice is-warning"><p>Your license has expired. Product card feature has been disabled.</p></div>
                <p class="wpiko-chatbot-settings-field-description">Get a lifetime license to display WooCommerce product cards in chat responses, including images, titles, descriptions, and prices.</p>
                <ul class="wpiko-chatbot-settings-benefits">
                    <li>Show WooCommerce products in chat responses</li>
                    <li>Display product images and titles</li>
                    <li>Choose descriptions and prices</li>
                    <li>Help visitors explore your products</li>
                </ul>
                <a href="https://wpiko.com/chatbot-pricing/" target="_blank" rel="noopener" class="button wpiko-chatbot-settings-save">Get Lifetime License</a>
            </section>
        <?php else: ?>
            <section class="wpiko-chatbot-settings-card wpiko-chatbot-settings-upgrade" aria-labelledby="product-card-unlock-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="product-card-unlock-title">Unlock Product Cards</h2>
                <p class="wpiko-chatbot-settings-field-description">Activate Pro to display WooCommerce products in chatbot responses.</p>
                <ul class="wpiko-chatbot-settings-benefits">
                    <li>Show WooCommerce products in chat responses</li>
                    <li>Display product images and titles</li>
                    <li>Choose descriptions and prices</li>
                    <li>Help visitors explore your products</li>
                </ul>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-chatbot&tab=license_activation')); ?>" class="button wpiko-chatbot-settings-save">Activate Pro</a>
            </section>
        <?php endif; ?>
    </div>
    <?php
}
