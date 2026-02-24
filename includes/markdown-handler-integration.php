<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Pro plugin markdown handler for contact forms and product cards
 */

/**
 * Get WooCommerce product image and details from URL
 * 
 * @param string $url The product URL
 * @return array|null Product details or null if not a valid product URL
 */
function wpiko_chatbot_pro_get_product_details($url) {
    if (!class_exists('WooCommerce')) {
        return null;
    }

    // Extract product ID from URL
    $product_id = url_to_postid($url);
    if (!$product_id) {
        return null;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return null;
    }

    // Get product image URL
    $image_url = wp_get_attachment_url($product->get_image_id());
    
    // Only return product details if an image exists
    if (!$image_url) {
        return null;
    }

    // Get product description
    $description = $product->get_short_description();
    if (empty($description)) {
        // If short description is empty, get the regular description and limit it
        $description = $product->get_description();
        if (!empty($description)) {
            // Strip tags and limit to a reasonable length
            $description = wp_strip_all_tags($description);
            if (strlen($description) > 80) {
                $description = substr($description, 0, 77) . '...';
            }
        }
    } else {
        // Strip tags from short description too
        $description = wp_strip_all_tags($description);
        // Also limit short description to 80 characters
        if (strlen($description) > 80) {
            $description = substr($description, 0, 77) . '...';
        }
    }

    return array(
        'id' => $product_id,
        'name' => $product->get_name(),
        'image' => $image_url,
        'price' => $product->get_price_html(),
        'url' => $url,
        'description' => $description
    );
}

/**
 * Format product link with image
 * 
 * @param array $product_details Product details from wpiko_chatbot_pro_get_product_details
 * @param bool $use_wp_functions Whether to use WordPress escaping functions
 * @return string Formatted HTML for product link with image
 */
function wpiko_chatbot_pro_format_product_link($product_details, $use_wp_functions = true) {
    if (!$product_details) {
        return '';
    }

    // Check if license is active and product cards are enabled
    if (!wpiko_chatbot_is_license_active() || !get_option('wpiko_chatbot_enable_product_cards', 0)) {
        // If license is not active or feature is disabled, return nothing
        return '';
    }

    // Check if all product info elements are disabled
    $show_title = get_option('wpiko_chatbot_show_product_title', 1);
    $show_description = get_option('wpiko_chatbot_show_product_description', 1);
    $show_price = get_option('wpiko_chatbot_show_product_price', 1);
    
    $all_disabled = !$show_title && !$show_description && !$show_price;
    $product_info_style = $all_disabled ? ' style="display:none;"' : '';
    $product_info_class = 'product-info';
    
    // Build product info HTML based on settings
    $info_html = '';
    
    // Show title if enabled
    if (get_option('wpiko_chatbot_show_product_title', 1)) {
        if ($use_wp_functions) {
            $info_html .= '<span class="product-name">' . esc_html($product_details['name']) . '</span>';
        } else {
            $info_html .= '<span class="product-name">' . $product_details['name'] . '</span>';
        }
    }
    
    // Show description if enabled
    if (get_option('wpiko_chatbot_show_product_description', 1) && !empty($product_details['description'])) {
        if ($use_wp_functions) {
            $info_html .= '<span class="product-description">' . esc_html($product_details['description']) . '</span>';
        } else {
            $info_html .= '<span class="product-description">' . $product_details['description'] . '</span>';
        }
    }
    
    // Show price if enabled
    if (get_option('wpiko_chatbot_show_product_price', 1)) {
        if ($use_wp_functions) {
            $info_html .= '<span class="product-price">' . $product_details['price'] . '</span>';
        } else {
            $info_html .= '<span class="product-price">' . $product_details['price'] . '</span>';
        }
    }

    if ($use_wp_functions) {
        $html = sprintf(
            '<div class="product-link-container">
                <a href="%s" class="product-link">
                    <img src="%s" alt="%s" class="product-image" />
                    <div class="%s"%s>%s</div>
                </a>
            </div>',
            esc_url($product_details['url']),
            esc_url($product_details['image']),
            esc_attr($product_details['name']),
            esc_attr($product_info_class),
            $product_info_style,
            $info_html
        );
    } else {
        $html = "<div class=\"product-link-container\">
            <a href=\"{$product_details['url']}\" class=\"product-link\">
                <img src=\"{$product_details['image']}\" alt=\"{$product_details['name']}\" class=\"product-image\" />
                <div class=\"{$product_info_class}\"{$product_info_style}>{$info_html}</div>
            </a>
        </div>";
    }

    return $html;
}

/**
 * Process product links and add product cards
 */
function wpiko_chatbot_pro_process_product_links($text) {
    // Check if license is active and product cards are enabled
    if (!wpiko_chatbot_is_license_active() || !get_option('wpiko_chatbot_enable_product_cards', 0)) {
        return $text;
    }

    if (!class_exists('WooCommerce')) {
        return $text;
    }

    $site_url = get_site_url();
    $site_domain = wp_parse_url($site_url, PHP_URL_HOST);

    // Handle markdown-style links
    $text = preg_replace_callback('/\[([^\]]+)\]\s*\(?\s*((?:https?:\/\/|www\.)[^\s\)]+)\s*\)?/', function($matches) use ($site_domain) {
        $linkText = $matches[1];
        $url = trim($matches[2], '()*');
        if (strpos($url, 'www.') === 0) {
            $url = 'http://' . $url;
        }
        
        // Check if this is a product URL and format accordingly
        $product_details = wpiko_chatbot_pro_get_product_details($url);
        
        $link_domain = wp_parse_url($url, PHP_URL_HOST);
        $target = ($link_domain === $site_domain) ? '' : ' target="_blank"';
        
        $link = sprintf('<a href="%s"%s rel="noopener noreferrer">%s</a>', esc_url($url), $target, esc_html($linkText));
        
        // If product details exist, add product card after the link
        if ($product_details) {
            $link .= '<br>' . wpiko_chatbot_pro_format_product_link($product_details, true);
        }
        
        return $link;
    }, $text);

    // Handle plain URLs
    $urlPattern = '/((?:https?:\/\/|www\.)[^\s<>"]+)(?![^<>]*>|[^<>]*<\/a>)/i';
    $text = preg_replace_callback($urlPattern, function($matches) use ($site_domain) {
        $url = rtrim($matches[1], '.,:;!?*');
        if (strpos($url, 'www.') === 0) {
            $url = 'http://' . $url;
        }
        
        $link_domain = wp_parse_url($url, PHP_URL_HOST);
        $target = ($link_domain === $site_domain) ? '' : ' target="_blank"';
        
        $link = sprintf('<a href="%s"%s rel="noopener noreferrer">%s</a>', esc_url($url), $target, esc_html($url));
        
        // Check if this is a product URL and format accordingly
        $product_details = wpiko_chatbot_pro_get_product_details($url);
        if ($product_details) {
            $link .= '<br>' . wpiko_chatbot_pro_format_product_link($product_details, true);
        }
        
        return $link;
    }, $text);

    return $text;
}

/**
 * Extract and protect contact form markers BEFORE markdown processing.
 * 
 * This prevents markdown italic/bold regexes (e.g. _..._) from mangling
 * JSON content inside the markers. Uses the same placeholder approach as URLs.
 * 
 * Hooked into 'wpiko_chatbot_before_markdown' (runs before markdown processing).
 */
function wpiko_chatbot_pro_protect_contact_markers($text) {
    // Check if license is active and contact form is enabled
    if (!wpiko_chatbot_is_license_active() || get_option('wpiko_chatbot_enable_contact_form', '0') !== '1') {
        return $text;
    }

    global $wpiko_contact_form_placeholders;
    $wpiko_contact_form_placeholders = array();

    // Protect [wpiko-contact-form:...] markers (new AI format)
    // NOTE: Placeholder must NOT contain underscores — markdown italic regex (_..._) would mangle them
    $text = preg_replace_callback(
        '/\[wpiko-contact-form:(.*?)\]/is',
        function($matches) {
            global $wpiko_contact_form_placeholders;
            $placeholder = '%%WPIKOCF' . count($wpiko_contact_form_placeholders) . '%%';
            $wpiko_contact_form_placeholders[] = $matches[0];
            return $placeholder;
        },
        $text
    );

    // Also protect <!--WPIKO_CONTACT_FORM:...--> markers (legacy format, kept for backward compatibility)
    $text = preg_replace_callback(
        '/<!--WPIKO_CONTACT_FORM:(.*?)-->/is',
        function($matches) {
            global $wpiko_contact_form_placeholders;
            $placeholder = '%%WPIKOCF' . count($wpiko_contact_form_placeholders) . '%%';
            $wpiko_contact_form_placeholders[] = $matches[0];
            return $placeholder;
        },
        $text
    );

    return $text;
}

/**
 * Build the contact form <a> tag from marker data
 * 
 * @param array $json_data Parsed JSON data from the marker
 * @return string HTML <a> tag with prefill data
 */
function wpiko_chatbot_pro_build_contact_form_link($json_data) {
    if (!is_array($json_data)) {
        $json_data = array();
    }

    // Sanitize the data
    $safe_data = array();
    if (!empty($json_data['message'])) {
        $safe_data['message'] = sanitize_textarea_field($json_data['message']);
    }
    if (!empty($json_data['category'])) {
        $safe_data['category'] = sanitize_text_field($json_data['category']);
    }
    // Support custom fields pre-fill (both "field1"/"field2" and "custom_field_1"/"custom_field_2" formats)
    for ($i = 1; $i <= 2; $i++) {
        $value = '';
        if (!empty($json_data["field{$i}"])) {
            $value = $json_data["field{$i}"];
        } elseif (!empty($json_data["custom_field_{$i}"])) {
            $value = $json_data["custom_field_{$i}"];
        }
        if (!empty($value)) {
            $safe_data["custom_field_{$i}"] = sanitize_text_field($value);
        }
    }

    if (empty($safe_data)) {
        // No prefill data — render simple button
        return '<a href="javascript:void(0);" onclick="if(typeof window.wpikoOpenChatbotWithContactForm === \'function\') { window.wpikoOpenChatbotWithContactForm(); } return false;" class="wpiko-contact-button">Contact Form</a>';
    }

    $encoded_data = esc_attr(wp_json_encode($safe_data));
    return '<a href="javascript:void(0);" data-wpiko-prefill="' . $encoded_data . '" onclick="if(typeof window.wpikoOpenChatbotWithContactForm === \'function\') { window.wpikoOpenChatbotWithContactForm(JSON.parse(this.getAttribute(\'data-wpiko-prefill\'))); } return false;" class="wpiko-contact-button">Contact Form</a>';
}

/**
 * Process contact form links in markdown content (runs AFTER markdown processing).
 * 
 * Restores protected markers from placeholders, processes them into <a> tags,
 * and also handles the legacy "Wpiko Form" text pattern.
 */
function wpiko_chatbot_pro_process_contact_form_links($text) {
    // Check if license is active and contact form is enabled
    if (!wpiko_chatbot_is_license_active() || get_option('wpiko_chatbot_enable_contact_form', '0') !== '1') {
        return $text;
    }

    // 1. Restore and process protected markers (from pre-markdown extraction)
    global $wpiko_contact_form_placeholders;
    if (!empty($wpiko_contact_form_placeholders)) {
        foreach ($wpiko_contact_form_placeholders as $i => $original_marker) {
            $placeholder = '%%WPIKOCF' . $i . '%%';
            if (strpos($text, $placeholder) === false) {
                continue;
            }

            // Extract JSON from the marker (handles both formats)
            $json_data = array();
            if (preg_match('/\[wpiko-contact-form:(.*?)\]/is', $original_marker, $m)) {
                $json_data = json_decode($m[1], true) ?: array();
            } elseif (preg_match('/<!--WPIKO_CONTACT_FORM:(.*?)-->/is', $original_marker, $m)) {
                $json_data = json_decode($m[1], true) ?: array();
            }

            $replacement = wpiko_chatbot_pro_build_contact_form_link($json_data);
            $text = str_replace($placeholder, $replacement, $text);
        }
        $wpiko_contact_form_placeholders = array();
    }

    // 2. Also process any markers that weren't pre-protected (e.g. from older pipeline paths)
    $text = preg_replace_callback(
        '/\[wpiko-contact-form:(.*?)\]/is',
        function($matches) {
            $json_data = json_decode($matches[1], true) ?: array();
            return wpiko_chatbot_pro_build_contact_form_link($json_data);
        },
        $text
    );
    $text = preg_replace_callback(
        '/<!--WPIKO_CONTACT_FORM:(.*?)-->/is',
        function($matches) {
            $json_data = json_decode($matches[1], true) ?: array();
            return wpiko_chatbot_pro_build_contact_form_link($json_data);
        },
        $text
    );
    
    // 3. Legacy: Match "Wpiko Form" text patterns (no pre-fill data)
    $text = preg_replace_callback('/\[Wpiko Form\]|\[wpiko form\]|Wpiko Form:|Wpiko Form button|(?<!\w)Wpiko Form(?!\w)/i', 
        function($matches) {
            return '<a href="javascript:void(0);" onclick="if(typeof window.wpikoOpenChatbotWithContactForm === \'function\') { window.wpikoOpenChatbotWithContactForm(); } return false;" class="wpiko-contact-button">Contact Form</a>';
         }, 
         $text
    );
    
    return $text;
}

/**
 * Hook into the main plugin's markdown processing
 */
function wpiko_chatbot_pro_add_markdown_filters() {
    // Add pre-markdown filter to protect contact form markers from markdown mangling
    add_filter('wpiko_chatbot_before_markdown', 'wpiko_chatbot_pro_protect_contact_markers', 10, 1);

    // Add post-markdown filter to restore and process contact form markers
    add_filter('wpiko_chatbot_processed_markdown', 'wpiko_chatbot_pro_process_contact_form_links', 10, 1);
    
    // Add filter to process product links after the main markdown processing
    add_filter('wpiko_chatbot_processed_markdown', 'wpiko_chatbot_pro_process_product_links', 15, 1);
}
add_action('init', 'wpiko_chatbot_pro_add_markdown_filters');

/**
 * Add product card styles to transcript generation
 */
function wpiko_chatbot_pro_add_transcript_styles($styles) {
    if (!wpiko_chatbot_is_license_active() || !get_option('wpiko_chatbot_enable_product_cards', 0)) {
        return $styles;
    }
    
    $product_styles = '
/* Product Card */
.product-link-container {
    margin: 8px 0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    background: #fff;
    max-width: 300px;
    padding: 0;
    font-size: 0; 
    line-height: 0;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.product-link-container:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.product-link {
    display: block;
    text-decoration: none;
    color: #0968fe;
}

.product-image {
    width: 100%;
    height: auto;
    display: block;
    margin: 0;
    padding: 0;
    border-radius: 8px 8px 0 0;
    position: relative; 
    top: 0;
}

.product-info {
    padding: 8px 12px;
    font-size: 14px;
    line-height: 0.5;
    text-align: center;
}

.product-name {
    font-weight: 600;
    color: #3c434a;
    line-height: 1.5;
    display: block;
    margin-top: 5px;
    margin-bottom: 10px;
}

.product-description {
    font-weight: 400;
    color: #3c434a;
    line-height: 1.5;
    display: block;
    margin-bottom: 10px;
}

.product-price {
    font-weight: 500;
    color: #0968fe;
    padding: 6px;
    border-top: 1px solid #eeebeb;
    display: block;
    padding-top: 15px;
}
';
    
    return $styles . $product_styles;
}
add_filter('wpiko_chatbot_transcript_styles', 'wpiko_chatbot_pro_add_transcript_styles', 10, 1);
