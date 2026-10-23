<?php
/**
 * Scan Website: keep scanned posts and docs up to date automatically.
 *
 * Optional, off by default. When on, a post or doc that is already in the
 * knowledge base as Markdown is uploaded again shortly after it is edited,
 * and removed when it is trashed or deleted. Pages are not included: their
 * Q&A is written by AI and uses OpenAI credits.
 *
 * Built for shared hosting: saving a post only adds it to a queue (no
 * request to OpenAI while the editor waits). WP-Cron works through the
 * queue in small batches, a minute after the last edit, so several saves in
 * a row cause one upload and a bulk edit never runs into the time limit.
 * Content that did not change is not uploaded again.
 *
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Is automatic sync turned on?
 *
 * @return bool
 */
function wpiko_chatbot_pro_auto_sync_enabled() {
    return get_option('wpiko_chatbot_pro_auto_sync', '0') === '1';
}

/**
 * The scan record of an item that automatic sync looks after, or null.
 *
 * Only items already in the knowledge base, processed as Markdown, in a
 * content type Scan Website lists and not hidden from it.
 *
 * @param int $post_id Post ID.
 * @return array|null Scan record {filename, file_id, time, mode, hash}.
 */
function wpiko_chatbot_pro_auto_sync_entry($post_id) {
    $post_id = absint($post_id);
    $scanned = get_option('wpiko_chatbot_pro_scanned_pages', array());
    if (!$post_id || !is_array($scanned) || empty($scanned[$post_id]['file_id']) || empty($scanned[$post_id]['filename'])) {
        return null;
    }
    $entry = $scanned[$post_id];

    // The file must still be the current one (not deleted in File Management).
    if (get_option('wpiko_chatbot_responses_qa_file_' . md5($entry['filename']), '') !== $entry['file_id']) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post
        || !in_array($post->post_type, wpiko_chatbot_pro_scan_post_types(), true)
        || in_array($post_id, wpiko_chatbot_pro_scan_excluded_ids(), true)
        || wpiko_chatbot_pro_scan_mode($post) !== 'markdown') {
        return null;
    }

    return $entry;
}

/**
 * Seconds to wait after the last edit before syncing.
 *
 * @return int
 */
function wpiko_chatbot_pro_auto_sync_delay() {
    /**
     * Filter how long automatic sync waits after the last edit.
     *
     * @param int $seconds Default 60.
     */
    return max(10, (int) apply_filters('wpiko_chatbot_pro_auto_sync_delay', 60));
}

/**
 * Add an item to the sync queue (replacing any earlier entry for it).
 *
 * @param int    $post_id Post ID.
 * @param string $action  'sync' or 'remove'.
 * @param string $file_id File to remove (for 'remove').
 * @return void
 */
function wpiko_chatbot_pro_auto_sync_queue_add($post_id, $action, $file_id = '') {
    $queue = get_option('wpiko_chatbot_pro_auto_sync_queue', array());
    $queue = is_array($queue) ? $queue : array();
    $queue[absint($post_id)] = array(
        'action' => $action === 'remove' ? 'remove' : 'sync',
        'due' => time() + wpiko_chatbot_pro_auto_sync_delay(),
        'attempts' => 0,
        'file_id' => (string) $file_id,
    );
    update_option('wpiko_chatbot_pro_auto_sync_queue', $queue, false);
    wpiko_chatbot_pro_auto_sync_schedule(wpiko_chatbot_pro_auto_sync_delay());
}

/**
 * Make sure the queue runner is scheduled.
 *
 * @param int $delay Seconds from now.
 * @return void
 */
function wpiko_chatbot_pro_auto_sync_schedule($delay) {
    if (!wp_next_scheduled('wpiko_chatbot_pro_auto_sync')) {
        wp_schedule_single_event(time() + max(10, (int) $delay), 'wpiko_chatbot_pro_auto_sync');
    }
}

/**
 * Stop automatic sync: empty the queue and unschedule the runner.
 *
 * @return void
 */
function wpiko_chatbot_pro_auto_sync_clear() {
    delete_option('wpiko_chatbot_pro_auto_sync_queue');
    delete_transient('wpiko_chatbot_pro_auto_sync_lock');
    wp_clear_scheduled_hook('wpiko_chatbot_pro_auto_sync');
}

/**
 * Queue an edited post or doc.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an update of an existing post.
 * @return void
 */
function wpiko_chatbot_pro_auto_sync_on_save($post_id, $post, $update) {
    if (!$update || !wpiko_chatbot_pro_auto_sync_enabled()) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if (!$post || in_array($post->post_status, array('trash', 'auto-draft', 'inherit'), true)) {
        return;
    }
    if (!wpiko_chatbot_pro_auto_sync_entry($post_id)) {
        return;
    }
    wpiko_chatbot_pro_auto_sync_queue_add($post_id, 'sync');
}
add_action('wp_after_insert_post', 'wpiko_chatbot_pro_auto_sync_on_save', 20, 3);

/**
 * Queue the removal of a trashed or deleted post or doc.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function wpiko_chatbot_pro_auto_sync_on_remove($post_id) {
    if (!wpiko_chatbot_pro_auto_sync_enabled()) {
        return;
    }
    $entry = wpiko_chatbot_pro_auto_sync_entry($post_id);
    if (!$entry) {
        return;
    }
    wpiko_chatbot_pro_auto_sync_queue_add($post_id, 'remove', $entry['file_id']);
}
add_action('trashed_post', 'wpiko_chatbot_pro_auto_sync_on_remove');
add_action('before_delete_post', 'wpiko_chatbot_pro_auto_sync_on_remove');

/**
 * WP-Cron: work through the queue, a few items per run.
 *
 * @return void
 */
function wpiko_chatbot_pro_auto_sync_run() {
    if (!wpiko_chatbot_pro_auto_sync_enabled() || !wpiko_chatbot_is_license_active()) {
        wpiko_chatbot_pro_auto_sync_clear();
        return;
    }

    // Never run twice at the same time (e.g. two visitors triggering WP-Cron).
    if (get_transient('wpiko_chatbot_pro_auto_sync_lock')) {
        wpiko_chatbot_pro_auto_sync_schedule(60);
        return;
    }
    set_transient('wpiko_chatbot_pro_auto_sync_lock', 1, 5 * MINUTE_IN_SECONDS);

    /**
     * Filter how many items automatic sync uploads per run.
     *
     * @param int $batch Default 5.
     */
    $batch = max(1, (int) apply_filters('wpiko_chatbot_pro_auto_sync_batch', 5));
    $started = time();
    $queue = get_option('wpiko_chatbot_pro_auto_sync_queue', array());
    $queue = is_array($queue) ? $queue : array();

    $processed = 0;
    foreach ($queue as $post_id => $item) {
        if ($processed >= $batch || time() - $started > 20) {
            break; // Leave time for the rest of the request on slow hosts.
        }
        if (!is_array($item) || (int) $item['due'] > time()) {
            continue;
        }
        $processed++;

        $done = $item['action'] === 'remove'
            ? wpiko_chatbot_pro_auto_sync_remove_item((int) $post_id, $item)
            : wpiko_chatbot_pro_auto_sync_item((int) $post_id);

        // Re-read: the item may have been edited again while it was uploading.
        $fresh = get_option('wpiko_chatbot_pro_auto_sync_queue', array());
        $fresh = is_array($fresh) ? $fresh : array();
        if (isset($fresh[$post_id]) && (int) $fresh[$post_id]['due'] === (int) $item['due']) {
            if ($done) {
                unset($fresh[$post_id]);
            } elseif ((int) $item['attempts'] >= 2) {
                wpiko_chatbot_log('Auto-sync gave up on item ' . $post_id . ' after 3 attempts. It is shown as changed in Scan Website.', 'warning');
                unset($fresh[$post_id]);
            } else {
                $fresh[$post_id]['attempts'] = (int) $item['attempts'] + 1;
                $fresh[$post_id]['due'] = time() + 10 * MINUTE_IN_SECONDS;
            }
            update_option('wpiko_chatbot_pro_auto_sync_queue', $fresh, false);
        }
    }

    delete_transient('wpiko_chatbot_pro_auto_sync_lock');

    $queue = get_option('wpiko_chatbot_pro_auto_sync_queue', array());
    if (is_array($queue) && $queue) {
        $next = min(array_map(function ($item) {
            return isset($item['due']) ? (int) $item['due'] : 0;
        }, $queue));
        wpiko_chatbot_pro_auto_sync_schedule(max(30, $next - time()));
    }
}
add_action('wpiko_chatbot_pro_auto_sync', 'wpiko_chatbot_pro_auto_sync_run');

/**
 * Upload the current version of an item.
 *
 * @param int $post_id Post ID.
 * @return bool True when finished (uploaded or nothing to do), false to retry.
 */
function wpiko_chatbot_pro_auto_sync_item($post_id) {
    $entry = wpiko_chatbot_pro_auto_sync_entry($post_id);
    $post = get_post($post_id);
    if (!$entry || !$post || $post->post_status === 'trash') {
        return true; // No longer looked after.
    }

    $markdown = wpiko_chatbot_pro_build_markdown($post_id);
    if (!$markdown) {
        return true;
    }
    if ($markdown['content'] === '') {
        // Keep the last good version rather than an empty file.
        wpiko_chatbot_log('Auto-sync skipped item ' . $post_id . ': it has no text. The previous version stays in the knowledge base.', 'warning');
        return true;
    }

    // Saved without changing the text (e.g. a category or slug edit): no upload.
    $hash = md5($markdown['content']);
    if (isset($entry['mode'], $entry['hash']) && $entry['mode'] === 'markdown' && $entry['hash'] !== '' && hash_equals($entry['hash'], $hash)) {
        wpiko_chatbot_pro_remember_scanned_page($post_id, $entry['filename'], $entry['file_id'], 'markdown', $hash);
        return true;
    }

    $filename = wpiko_chatbot_pro_get_filename_from_title($markdown['title']) . '-' . $post_id;
    $stored = wpiko_chatbot_pro_store_scan_file($post_id, $filename, $markdown['content'], 'markdown');
    if (empty($stored['success'])) {
        wpiko_chatbot_log('Auto-sync could not upload item ' . $post_id . '; it will be retried.', 'warning');
        return false;
    }
    return true;
}

/**
 * Remove a trashed or deleted item's file from the knowledge base.
 *
 * @param int   $post_id Post ID.
 * @param array $item    Queue item with the file ID.
 * @return bool True when finished, false to retry.
 */
function wpiko_chatbot_pro_auto_sync_remove_item($post_id, $item) {
    // Restored from the trash before the queue ran: keep it.
    $post = get_post($post_id);
    if ($post && $post->post_status !== 'trash') {
        return true;
    }

    $scanned = get_option('wpiko_chatbot_pro_scanned_pages', array());
    if (empty($item['file_id']) || !is_array($scanned) || empty($scanned[$post_id]['file_id']) || $scanned[$post_id]['file_id'] !== $item['file_id']) {
        return true; // Already removed or replaced.
    }

    $removed = wpiko_chatbot_delete_responses_file($item['file_id']);
    if (is_array($removed) && empty($removed['success'])) {
        wpiko_chatbot_log('Auto-sync could not remove the file of item ' . $post_id . ': ' . (isset($removed['message']) ? $removed['message'] : 'unknown error'), 'warning');
        return false;
    }
    // The scan record is cleaned up by the file-deleted hook.
    return true;
}

/**
 * AJAX: turn automatic sync on or off.
 *
 * @return void
 */
function wpiko_chatbot_pro_save_auto_sync_setting() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'), 403);
    }
    if (!wpiko_chatbot_is_license_active()) {
        wp_send_json_error(array('message' => 'This feature requires a premium license'));
    }

    $enabled = isset($_POST['enabled']) && sanitize_text_field(wp_unslash($_POST['enabled'])) === 'true';
    update_option('wpiko_chatbot_pro_auto_sync', $enabled ? '1' : '0', false);
    if (!$enabled) {
        wpiko_chatbot_pro_auto_sync_clear();
    }

    wp_send_json_success(array('enabled' => $enabled));
}
add_action('wp_ajax_wpiko_chatbot_save_auto_sync_setting', 'wpiko_chatbot_pro_save_auto_sync_setting');

// Do not leave a scheduled run behind when Pro is deactivated.
if (defined('WPIKO_CHATBOT_PRO_FILE')) {
    register_deactivation_hook(WPIKO_CHATBOT_PRO_FILE, 'wpiko_chatbot_pro_auto_sync_clear');
}
