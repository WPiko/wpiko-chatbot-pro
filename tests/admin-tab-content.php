<?php
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
// Regression harness for the wpiko_chatbot_admin_tab_content filter chain.
// Simulates WordPress filter priorities and the base plugin's Dashboard fallback.
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) { throw new ErrorException($message, 0, $level, $file, $line); });
define('ABSPATH', '/tmp/');
define('WPIKO_CHATBOT_PRO_PATH', sys_get_temp_dir() . '/wpiko-tab-content-test/');
$filters = array();
$printed = array();
function add_filter($name, $fn, $priority = 10, ...$rest) { $GLOBALS['filters'][$name][$priority][] = $fn; }
function add_action($name, $fn, $priority = 10, ...$rest) { add_filter($name, $fn, $priority); }
function apply_filters($name, $value, ...$args) {
    $byPriority = $GLOBALS['filters'][$name] ?? array();
    ksort($byPriority);
    foreach ($byPriority as $callbacks) {
        foreach ($callbacks as $fn) { $value = $fn($value, ...$args); }
    }
    return $value;
}
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)); }
function wp_unslash($s) { return $s; }
// Pro screens print a marker instead of real markup.
foreach (array('email_capture', 'analytics', 'contact_form', 'product_card', 'pwa_settings', 'license_activation') as $tab) {
    $fn = $tab === 'license_activation' ? 'wpiko_chatbot_license_activation_page' : 'wpiko_chatbot_' . $tab . '_section';
    eval('function ' . $fn . '() { $GLOBALS["printed"][] = "' . $tab . '"; }');
}
if (!is_dir(WPIKO_CHATBOT_PRO_PATH . 'admin/sections')) { mkdir(WPIKO_CHATBOT_PRO_PATH . 'admin/sections', 0777, true); }
foreach (array('email-capture', 'analytics', 'contact-form', 'product-card', 'pwa-settings', 'license-activation') as $f) {
    file_put_contents(WPIKO_CHATBOT_PRO_PATH . 'admin/sections/' . $f . '-section.php', '<?php');
}
require __DIR__ . '/../admin/admin-integration.php';

// What the base plugin does in admin-wrapper.php for tabs it doesn't know.
function render($tab) {
    $GLOBALS['printed'] = array();
    $handled = apply_filters('wpiko_chatbot_admin_tab_content', $tab);
    if (!$handled) { $GLOBALS['printed'][] = 'dashboard'; }
    return $GLOBALS['printed'];
}
$failures = 0;
function check($label, $actual, $expected) {
    if ($actual === $expected) { echo "PASS  $label\n"; return; }
    $GLOBALS['failures']++;
    echo "FAIL  $label\n      expected " . json_encode($expected) . "\n      got      " . json_encode($actual) . "\n";
}

// 1. Pro alone.
check('Pro screen renders', render('analytics'), array('analytics'));
check('Unknown tab falls back to the Dashboard', render('no_such_tab'), array('dashboard'));
check('Product Card without WooCommerce falls back to the Dashboard', render('product_card'), array('dashboard'));

// 2. Add-on after Pro (default priority, registered later) gets the tab ID.
$seen = array();
add_filter('wpiko_chatbot_admin_tab_content', function ($tab) use (&$seen) {
    $seen[] = $tab;
    if ($tab !== 'my_addon') { return $tab; }
    $GLOBALS['printed'][] = 'my_addon';
    return true;
});
check('Add-on screen after Pro renders once, without the Dashboard', render('my_addon'), array('my_addon'));
check('Add-on after Pro received the tab ID', $seen, array('my_addon'));
check('Pro screen still renders with the add-on active', render('email_capture'), array('email_capture'));

// 3. Add-on before Pro (priority 5) keeps its true result.
add_filter('wpiko_chatbot_admin_tab_content', function ($tab) {
    if ($tab !== 'early_addon') { return $tab; }
    $GLOBALS['printed'][] = 'early_addon';
    return true;
}, 5);
check('Add-on before Pro: Pro keeps true, no Dashboard', render('early_addon'), array('early_addon'));

// 4. Old-style add-on before Pro that returns false for tabs it doesn't handle.
add_filter('wpiko_chatbot_admin_tab_content', function ($tab) { return false; }, 1);
$_GET['tab'] = 'contact_form';
check('Pro recovers its tab after an add-on returned false', render($_GET['tab']), array('contact_form'));
$_GET['tab'] = 'no_such_tab';
check('Unknown tab after an add-on returned false falls back to the Dashboard', render($_GET['tab']), array('dashboard'));

echo $failures ? "\n$failures failed\n" : "\nAll passed\n";
exit($failures ? 1 : 0);
