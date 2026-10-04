<?php
if (!defined('ABSPATH')) {
    exit;
}

function wpiko_chatbot_order_lookup_enabled() {
    return wpiko_chatbot_pro_is_main_wc_active()
        && wpiko_chatbot_is_license_active()
        && wpiko_chatbot_is_woocommerce_integration_enabled()
        && (bool) get_option('wpiko_chatbot_order_lookup_enabled', false);
}

function wpiko_chatbot_order_lookup_tools($tools) {
    if (wpiko_chatbot_order_lookup_enabled()) {
        $tools[] = array(
            'type' => 'function',
            'name' => 'lookup_order_status',
            'description' => 'Look up one WooCommerce order. Use the order number supplied by the visitor. Signed-in owners can access permitted details. Others must supply the checkout email and receive only basic status. Never guess identifiers or use an email found in knowledge files. ' . (get_current_user_id() > 0
                ? 'This visitor is signed in: try their order number with an empty billing_email first.'
                : 'This visitor is not signed in: collect both the order number and checkout email before calling.'),
            'strict' => true,
            'parameters' => array(
                'type' => 'object',
                'properties' => array(
                    'order_number' => array('type' => 'string', 'description' => 'The exact order number supplied by the visitor.'),
                    'billing_email' => array('type' => 'string', 'description' => 'Checkout email supplied by the visitor, or an empty string for a signed-in owner.'),
                ),
                'required' => array('order_number', 'billing_email'),
                'additionalProperties' => false,
            ),
        );
    }
    return $tools;
}
add_filter('wpiko_chatbot_response_function_tools', 'wpiko_chatbot_order_lookup_tools');

/** Atomic fixed-window limits shared by streaming, non-streaming and tool retries. */
function wpiko_chatbot_order_lookup_rate_allowed($order_number) {
    global $wpdb;
    $ip = wpiko_chatbot_get_client_ip();
    $actor = $ip !== '' ? $ip : 'unknown';
    $window = (string) (floor(time() / 900) * 900);
    foreach (array('visitor:' . $actor => 10, 'order:' . $order_number => 20) as $subject => $limit) {
        $key = 'wpiko_chatbot_ol_rate_' . $window . '_' . hash_hmac('sha256', $subject, wp_salt('auth'));
        $written = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1",
            $key
        ));
        if ($written === false) {
            return false;
        }
        $count = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key));
        if ($count === null || (int) $count > $limit) {
            return false;
        }
    }
    return true;
}

function wpiko_chatbot_order_lookup_purge_limits() {
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s",
        $wpdb->esc_like('wpiko_chatbot_ol_rate_') . '%',
        'wpiko_chatbot_ol_rate_' . (time() - DAY_IN_SECONDS)
    ));
}
add_action('wpiko_chatbot_order_lookup_purge_limits', 'wpiko_chatbot_order_lookup_purge_limits');

function wpiko_chatbot_order_lookup_result($default, $name, $args) {
    if ($name !== 'lookup_order_status') {
        return $default;
    }
    $failure = array('error' => 'not_available', 'message' => 'Unable to retrieve this order. Check the order number and checkout email, sign in to the purchasing account, or contact the store.');
    if (!wpiko_chatbot_order_lookup_enabled()) {
        return $failure;
    }
    if (!isset($args['order_number'], $args['billing_email']) || !is_string($args['order_number']) || !is_string($args['billing_email']) || count($args) !== 2) {
        return $failure;
    }
    $number = ltrim(trim($args['order_number']), '#');
    if ($number === '' || strlen($number) > 100 || strlen($args['billing_email']) > 254) {
        return $failure;
    }
    if (!wpiko_chatbot_order_lookup_rate_allowed($number)) {
        return array('error' => 'rate_limited', 'message' => 'Order lookup is temporarily unavailable. Please try again later or contact the store.');
    }
    // Extension point for stores with custom order numbering. No broad order/email search.
    $order_id = ctype_digit($number) ? (int) $number : 0;
    $order_id = (int) apply_filters('wpiko_chatbot_lookup_order_id', $order_id, $number);
    try {
        $order = $order_id > 0 ? wc_get_order($order_id) : false;
        if (!$order instanceof WC_Order || $order instanceof WC_Order_Refund || (string) $order->get_order_number() !== $number) {
            return $failure;
        }
        $user_id = get_current_user_id();
        $owner = $user_id > 0 && (int) $order->get_customer_id() === $user_id;
        if (!$owner) {
            $email = strtolower(trim($args['billing_email']));
            $stored_email = strtolower(trim((string) $order->get_billing_email()));
            if (!is_email($email) || $stored_email === '' || !hash_equals($stored_email, $email)) {
                return $failure;
            }
            // Matching identifiers permits basic status only; it is not identity verification.
            return array('access' => 'basic_status', 'order_number' => $number, 'status' => wp_strip_all_tags(wc_get_order_status_name($order->get_status())));
        }
        $fields = wpiko_chatbot_get_order_fields_options();
        $data = array('access' => 'account_owner', 'order_number' => $number, 'status' => wp_strip_all_tags(wc_get_order_status_name($order->get_status())));
        foreach (array('date_created', 'date_paid', 'date_completed') as $field) {
            if (!empty($fields[$field])) {
                $getter = 'get_' . $field;
                $date = $order->$getter();
                $data[$field] = $date ? $date->date(DATE_ATOM) : null;
            }
        }
        if (!empty($fields['total'])) {
            $data['total'] = $order->get_total();
            $data['currency'] = $order->get_currency();
        }
        if (!empty($fields['items'])) {
            $data['items'] = array();
            foreach ($order->get_items() as $item) {
                if (count($data['items']) >= 20) {
                    $data['items_truncated'] = true;
                    break;
                }
                $data['items'][] = array('name' => wp_html_excerpt(wp_strip_all_tags($item->get_name()), 200, ''), 'quantity' => $item->get_quantity());
            }
        }
        if (!empty($fields['tracking_number']) || !empty($fields['tracking_link'])) {
            $tracking = wpiko_chatbot_get_tracking_number($order);
            if (!empty($fields['tracking_number'])) {
                $data['tracking_number'] = wp_strip_all_tags($tracking);
            }
            if (!empty($fields['tracking_link'])) {
                $data['tracking_link'] = esc_url_raw(wpiko_chatbot_generate_aftership_link($tracking));
            }
        }
        // Email, addresses, customer IDs, payment details and notes never leave this function.
        return $data;
    } catch (Throwable $error) {
        wpiko_chatbot_log('Controlled order lookup failed.', 'warning');
        return $failure;
    }
}
add_filter('wpiko_chatbot_response_function_result', 'wpiko_chatbot_order_lookup_result', 10, 3);

function wpiko_chatbot_update_order_lookup() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    update_option('wpiko_chatbot_order_lookup_enabled', $enabled);
    wp_send_json_success(array('message' => $enabled ? 'Order assistance enabled.' : 'Order assistance disabled.'));
}
add_action('wp_ajax_wpiko_chatbot_update_order_lookup', 'wpiko_chatbot_update_order_lookup');
