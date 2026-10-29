<?php
// CLI-only regression test: a takeover nobody from the team looks after goes
// back to the AI after 12 hours (wpiko_chatbot_pro_takeover_auto_release_hours).
// Uses the real includes/admin-takeover.php with an in-memory database.
// Run from the Pro plugin directory: php tests/takeover-auto-release.php
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) { throw new ErrorException($message, 0, $level, $file, $line); });
define('ABSPATH', '/tmp/');
define('HOUR_IN_SECONDS', 3600);

$now = strtotime('2026-10-29 12:00:00');
$filters = array();
$actions_fired = array();
function add_filter($name, $fn, $priority = 10) { $GLOBALS['filters'][$name][] = $fn; }
function add_action(...$args) {}
function apply_filters($name, $value, ...$args) {
    foreach ($GLOBALS['filters'][$name] ?? array() as $fn) {
        if (is_callable($fn) && $name !== 'wpiko_chatbot_skip_ai_response') { $value = $fn($value, ...$args); }
    }
    return $value;
}
function do_action($name, ...$args) { $GLOBALS['actions_fired'][] = array($name, $args); }
function get_option($key, $default = false) { return $key === 'wpiko_chatbot_enable_pwa' ? '1' : $default; }
function current_time($type) { return date('Y-m-d H:i:s', $GLOBALS['now']); }
function get_userdata($id) { return $id ? (object) array('display_name' => 'Maria', 'user_login' => 'maria', 'user_email' => 'maria@example.test') : false; }
function absint($v) { return abs((int) $v); }
function wpiko_chatbot_pro_is_license_active() { return true; }
function wpiko_chatbot_log(...$args) {}
function ago($hours) { return date('Y-m-d H:i:s', $GLOBALS['now'] - (int) round($hours * 3600)); }

$wpdb = new class {
    public $prefix = 'wp_';
    public $meta = array();
    public $rows = array();
    public $insert_id = 0;
    public $steal_release = false;
    function prepare($sql, ...$args) { return array($sql, $args); }
    function get_var($q) {
        list($sql, $a) = $q;
        if (strpos($sql, 'SHOW TABLES') === 0) { return $a[0]; }
        if (strpos($sql, 'MAX(timestamp)') !== false) {
            $times = array();
            foreach ($this->rows as $r) { if ($r['session_id'] === $a[0] && $r['role'] === 'admin') { $times[] = $r['timestamp']; } }
            return $times ? max($times) : null;
        }
        if (strpos($sql, 'SELECT updated_at') === 0) { return $this->meta[$a[0]]['human_takeover'][1] ?? null; }
        if (strpos($sql, "meta_key = 'human_takeover'") !== false) { return $this->meta[$a[0]]['human_takeover'][0] ?? null; }
        return $this->meta[$a[0]][$a[1]][0] ?? null;
    }
    function query($q) {
        list($sql, $a) = $q;
        if (strpos($sql, 'UPDATE') === 0) {
            $item = &$this->meta[$a[1]]['human_takeover'];
            if ($this->steal_release) { $item[0] = '0'; return 0; }
            if (($item[0] ?? '') !== '1') { return 0; }
            $item = array('0', $a[0]);
            return 1;
        }
        throw new RuntimeException('Unexpected query: ' . $sql);
    }
    function replace($table, $data) { $this->meta[$data['session_id']][$data['meta_key']] = array($data['meta_value'], $data['updated_at']); return 1; }
    function delete($table, $where) { unset($this->meta[$where['session_id']][$where['meta_key']]); return 1; }
    function insert($table, $data) { $this->rows[] = $data; $this->insert_id = count($this->rows); return 1; }
};

require dirname(__DIR__) . '/includes/admin-takeover.php';

$failures = 0;
function check($label, $actual, $expected) {
    if ($actual === $expected) { echo "PASS  $label\n"; return; }
    $GLOBALS['failures']++;
    echo "FAIL  $label\n      expected " . json_encode($expected) . "\n      got      " . json_encode($actual) . "\n";
}
function start_takeover($session, $hours_ago, $with_started_at = true) {
    global $wpdb;
    $wpdb->meta[$session] = array(
        'human_takeover' => array('1', ago($hours_ago)),
        'takeover_admin_id' => array('5', ago($hours_ago)),
    );
    if ($with_started_at) { $wpdb->meta[$session]['takeover_started_at'] = array(ago($hours_ago), ago($hours_ago)); }
}
function team_message($session, $hours_ago) { $GLOBALS['wpdb']->rows[] = array('session_id' => $session, 'role' => 'admin', 'message' => 'Hello from the team', 'timestamp' => ago($hours_ago)); }
function notices($session) {
    return count(array_filter($GLOBALS['wpdb']->rows, function ($r) use ($session) { return $r['session_id'] === $session && strpos($r['message'], 'AI assistant is back') !== false; }));
}
function skip($session) { return wpiko_chatbot_pro_filter_skip_ai(false, $session); }

start_takeover('a', 2);
check('Recent takeover keeps the AI paused', skip('a'), true);
check('Recent takeover is not released', wpiko_chatbot_pro_is_takeover_active('a'), true);

start_takeover('b', 30);
team_message('b', 1);
check('Team reply 1 hour ago keeps the takeover', skip('b'), true);

start_takeover('c', 30);
$wpdb->meta['c']['admin_watching'] = array(ago(0.5), ago(0.5));
check('Team member viewing 30 minutes ago keeps the takeover', skip('c'), true);

start_takeover('d', 13);
team_message('d', 12.5);
$actions_fired = array();
check('13 hours with no team activity: the AI answers again', skip('d'), false);
check('Takeover flag is off', wpiko_chatbot_pro_is_takeover_active('d'), false);
check('Takeover owner is cleared', isset($wpdb->meta['d']['takeover_admin_id']), false);
check('"Left the conversation" notice added once', notices('d'), 1);
check('Notice names the team member', end($wpdb->rows)['message'], 'Maria left the conversation. AI assistant is back.');
check('Auto-release action fired with the team member', $actions_fired, array(array('wpiko_chatbot_pro_takeover_auto_released', array('d', 5))));
check('Next check does not release or notify again', array(skip('d'), notices('d')), array(false, 1));

start_takeover('e', 20, false);
check('Takeover without a start time uses when it was switched on', skip('e'), false);
start_takeover('f', 1, false);
check('...and keeps a recent one', skip('f'), true);

$filters['wpiko_chatbot_pro_takeover_auto_release_hours'][] = function () { return 0; };
start_takeover('g', 100);
check('Filter 0: takeovers never go back on their own', skip('g'), true);
$filters['wpiko_chatbot_pro_takeover_auto_release_hours'] = array(function () { return 1; });
start_takeover('h', 2);
check('Filter 1 hour: released after 2 hours', skip('h'), false);
$filters['wpiko_chatbot_pro_takeover_auto_release_hours'] = array();

start_takeover('i', 20);
$wpdb->steal_release = true;
check('Released by a parallel request: AI answers', skip('i'), false);
check('...without a second notice', notices('i'), 0);
$wpdb->steal_release = false;

echo $failures ? "\n$failures check(s) failed\n" : "\nAll checks passed\n";
exit($failures ? 1 : 0);
