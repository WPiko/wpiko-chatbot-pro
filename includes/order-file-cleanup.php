<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Local initialization only: all remote cleanup runs in bounded cron batches. */
function wpiko_chatbot_initialize_order_lookup() {
    if (!get_option('wpiko_chatbot_private_orders_retired', false)) {
        if (get_option('wpiko_chatbot_order_lookup_enabled', null) === null) {
            add_option('wpiko_chatbot_order_lookup_enabled', get_option('wpiko_chatbot_orders_auto_sync', 'disabled') !== 'disabled', '', false);
        }
        update_option('wpiko_chatbot_orders_auto_sync', 'disabled');
        wp_clear_scheduled_hook('wpiko_chatbot_background_orders_sync');
        wp_clear_scheduled_hook('wpiko_chatbot_delayed_sync_orders');
        update_option('wpiko_chatbot_private_orders_retired', true);
    }
    if (!wp_next_scheduled('wpiko_chatbot_order_lookup_purge_limits')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'wpiko_chatbot_order_lookup_purge_limits');
    }
    $vector = get_option('wpiko_chatbot_responses_vector_store_id', '');
    $state = get_option('wpiko_chatbot_order_file_cleanup', array());
    if (($state['vector'] ?? null) !== $vector) {
        $pending = $state['pending'] ?? array();
        $tracked = get_option('wpiko_chatbot_responses_orders_file_id', '');
        if ($tracked) {
            $pending[$tracked] = $vector;
        }
        $state = array('vector' => $vector, 'cursor' => '', 'scanned' => $vector === '', 'pending' => $pending, 'done' => false, 'error' => false);
        update_option('wpiko_chatbot_order_file_cleanup', $state, false);
    }
    if (empty($state['done']) && !wp_next_scheduled('wpiko_chatbot_cleanup_order_files')) {
        wp_schedule_single_event(time() + 10, 'wpiko_chatbot_cleanup_order_files');
    }
}
add_action('plugins_loaded', 'wpiko_chatbot_initialize_order_lookup', 30);

function wpiko_chatbot_order_cleanup_request($path, $method = 'GET', $body = null) {
    $key = wpiko_chatbot_decrypt_api_key(get_option('wpiko_chatbot_api_key', ''));
    if ($key === '') {
        return new WP_Error('missing_key', 'Missing API key.');
    }
    $args = array('method' => $method, 'timeout' => 10, 'headers' => array('Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'));
    if ($body !== null) {
        $args['body'] = wp_json_encode($body);
    }
    $response = wp_remote_request('https://api.openai.com/v1/' . $path, $args);
    if (is_wp_error($response)) {
        return $response;
    }
    $code = wp_remote_retrieve_response_code($response);
    if ($method === 'DELETE' && $code === 404) {
        return array(); // An earlier batch may already have completed this phase.
    }
    if ($code < 200 || $code >= 300) {
        return new WP_Error('cleanup_failed', 'OpenAI file cleanup is pending.');
    }
    $decoded = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($decoded)) {
        return new WP_Error('invalid_cleanup_response', 'Invalid OpenAI response.');
    }
    return $decoded;
}

function wpiko_chatbot_cleanup_order_files() {
    $state = get_option('wpiko_chatbot_order_file_cleanup', array());
    if (!$state || !empty($state['done'])) {
        return;
    }
    if (!wpiko_chatbot_get_lock('order_file_cleanup', 120)) {
        return;
    }
    $failed = false;
    try {
        if (empty($state['scanned'])) {
            // Finish enumeration before deleting files, so pagination cursors remain valid.
            $path = 'vector_stores/' . rawurlencode($state['vector']) . '/files?limit=3';
            if ($state['cursor'] !== '') {
                $path .= '&after=' . rawurlencode($state['cursor']);
            }
            $page = wpiko_chatbot_order_cleanup_request($path);
            if (is_wp_error($page) || !isset($page['data']) || !is_array($page['data'])) {
                $failed = true;
            } else {
                foreach ($page['data'] as $file) {
                    if (empty($file['id'])) {
                        $failed = true;
                        break;
                    }
                    $id = $file['id'];
                    $details = wpiko_chatbot_order_cleanup_request('files/' . rawurlencode($id));
                    if (is_wp_error($details) || !isset($details['filename'])) {
                        $failed = true;
                        break;
                    }
                    $private = strtolower($details['filename']) === 'woocommerce_orders.json' || isset($state['pending'][$id]);
                    if ($private) {
                        $state['pending'][$id] = $state['vector'];
                    } else {
                        // Preserve existing attributes belonging to other integrations.
                        $attributes = $file['attributes'] ?? array();
                        $attributes['wpiko_scope'] = 'public_v1';
                        $result = wpiko_chatbot_order_cleanup_request('vector_stores/' . rawurlencode($state['vector']) . '/files/' . rawurlencode($id), 'POST', array('attributes' => $attributes));
                        if (is_wp_error($result)) {
                            $failed = true;
                            break;
                        }
                    }
                    $state['cursor'] = $id;
                }
                if (!$failed && empty($page['has_more'])) {
                    $state['scanned'] = true;
                } elseif (!$failed && empty($page['data'])) {
                    $failed = true;
                }
            }
        } else {
            foreach (array_slice($state['pending'], 0, 3, true) as $id => $vector) {
                if ($vector !== '') {
                    $result = wpiko_chatbot_order_cleanup_request('vector_stores/' . rawurlencode($vector) . '/files/' . rawurlencode($id), 'DELETE');
                    if (is_wp_error($result)) {
                        $failed = true;
                        break;
                    }
                }
                $result = wpiko_chatbot_order_cleanup_request('files/' . rawurlencode($id), 'DELETE');
                if (is_wp_error($result)) {
                    $failed = true;
                    break;
                }
                unset($state['pending'][$id]);
                if (get_option('wpiko_chatbot_responses_orders_file_id', '') === $id) {
                    delete_option('wpiko_chatbot_responses_orders_file_id');
                }
                if (function_exists('wpiko_chatbot_remove_cached_file')) {
                    wpiko_chatbot_remove_cached_file($id);
                }
                wpiko_chatbot_clear_vector_store_files_cache();
                do_action('wpiko_chatbot_responses_file_deleted', $id);
            }
            $state['done'] = !$failed && empty($state['pending']);
        }
    } catch (Throwable $error) {
        $failed = true;
    }
    $state['error'] = $failed;
    update_option('wpiko_chatbot_order_file_cleanup', $state, false);
    wpiko_chatbot_release_lock('order_file_cleanup');
    if (empty($state['done']) && !wp_next_scheduled('wpiko_chatbot_cleanup_order_files')) {
        wp_schedule_single_event(time() + ($failed ? 300 : 10), 'wpiko_chatbot_cleanup_order_files');
    }
}
add_action('wpiko_chatbot_cleanup_order_files', 'wpiko_chatbot_cleanup_order_files');

function wpiko_chatbot_order_cleanup_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $state = get_option('wpiko_chatbot_order_file_cleanup', array());
    if ($state && empty($state['done'])) {
        echo '<div class="notice notice-warning"><p>';
        esc_html_e('WPiko Chatbot is preparing knowledge files for controlled order lookup and removing old order exports. Only checked knowledge files can be searched during this process. Cleanup runs in the background. If this notice persists, check your OpenAI connection and WordPress scheduled tasks.', 'wpiko-chatbot-pro');
        echo '</p></div>';
    }
}
add_action('admin_notices', 'wpiko_chatbot_order_cleanup_notice');
