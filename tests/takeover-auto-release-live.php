<?php
// Live check, LOCAL SITE ONLY: open as an admin at
// /wp-content/plugins/wpiko-chatbot-pro/tests/takeover-auto-release-live.php
// Runs the takeover auto-release against the real database tables with two
// test conversations (no OpenAI requests) and removes them afterwards.
// Keep the tests folder out of release ZIPs.
require dirname(__DIR__, 4) . '/wp-load.php';

if (!current_user_can('manage_options') || !preg_match('/\.(local|test|localhost)$/', (string) wp_parse_url(home_url(), PHP_URL_HOST))) {
    status_header(403);
    exit('Admins on a local site only.');
}
header('Content-Type: text/plain; charset=utf-8');

global $wpdb;
$meta = $wpdb->prefix . 'wpiko_chatbot_conversation_meta';
$conversations = $wpdb->prefix . 'wpiko_chatbot_conversations';
$admin_id = get_current_user_id();
$results = array();
$check = function ($ok, $label) use (&$results) { $results[] = ($ok ? 'PASS' : 'FAIL') . '  ' . $label; };
$backdate = function ($session_id, $hours) use ($wpdb, $meta) {
    $then = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - $hours * HOUR_IN_SECONDS);
    $wpdb->query($wpdb->prepare("UPDATE `$meta` SET updated_at = %s WHERE session_id = %s", $then, $session_id));
    $wpdb->query($wpdb->prepare("UPDATE `$meta` SET meta_value = %s WHERE session_id = %s AND meta_key = 'takeover_started_at'", $then, $session_id));
};
$notices = function ($session_id) use ($wpdb, $conversations) {
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$conversations` WHERE session_id = %s AND role = 'admin'", $session_id));
};

$stale = 'resp_takeovertest_stale_' . wp_generate_password(8, false, false);
$fresh = 'resp_takeovertest_fresh_' . wp_generate_password(8, false, false);

wpiko_chatbot_pro_activate_takeover($stale, $admin_id);
wpiko_chatbot_pro_activate_takeover($fresh, $admin_id);
$backdate($stale, 13);
$backdate($fresh, 2);

$check(wpiko_chatbot_pro_maybe_auto_release_takeover($fresh) === false && wpiko_chatbot_pro_is_takeover_active($fresh), 'Takeover from 2 hours ago stays active');
$check(wpiko_chatbot_pro_maybe_auto_release_takeover($stale) === true, 'Takeover from 13 hours ago goes back to the AI');
$check(!wpiko_chatbot_pro_is_takeover_active($stale), 'Takeover flag is off in the database');
$check(wpiko_chatbot_pro_get_takeover_admin_id($stale) === 0, 'Takeover owner removed');
$check($notices($stale) === 1, '"Left the conversation" notice saved in the transcript');
wpiko_chatbot_pro_maybe_auto_release_takeover($stale);
$check($notices($stale) === 1 && !wpiko_chatbot_pro_is_takeover_active($stale), 'A second check adds no second notice');

$filter_ok = get_option('wpiko_chatbot_enable_pwa', '0') === '1' && wpiko_chatbot_pro_is_license_active();
if ($filter_ok) {
    wpiko_chatbot_pro_activate_takeover($stale, $admin_id);
    $backdate($stale, 13);
    $check(apply_filters('wpiko_chatbot_skip_ai_response', false, $stale) === false, 'Chat request filter lets the AI answer a stale takeover');
    $check(apply_filters('wpiko_chatbot_skip_ai_response', false, $fresh) === true, 'Chat request filter keeps the AI paused for a recent takeover');
} else {
    $results[] = 'SKIP  chat request filter (Mobile App or license not active)';
}

foreach (array($stale, $fresh) as $session_id) {
    $wpdb->delete($meta, array('session_id' => $session_id));
    $wpdb->delete($conversations, array('session_id' => $session_id));
}

echo implode("\n", $results) . "\n";
