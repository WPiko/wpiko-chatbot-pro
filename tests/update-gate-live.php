<?php
// Live check, LOCAL SITE ONLY: open as an admin at
// /wp-content/plugins/wpiko-chatbot-pro/tests/update-gate-live.php
//
// Needs WPiko Keymaster 1.8.0+ active on the same site. Uses a temporary product
// type "wpikotest", a temporary release signing key, test licenses and test ZIPs
// (version 9.9.x), talks to this site's own Keymaster over HTTP, and removes all
// of it afterwards. The real "chatbot" release settings and licenses are not touched.
// ?github=0 skips the download of the public v2.1.3 release from GitHub.
// ?demo=1 leaves the test product type with a published 9.9.1 and a waiting 9.9.3
// to look at in Keymaster → Releases; ?cleanup=1 removes it again.
// Keep the tests folder out of release ZIPs.
require dirname(__DIR__, 4) . '/wp-load.php';

if (!current_user_can('manage_options') || !preg_match('/\.(local|test|localhost)$/', (string) wp_parse_url(home_url(), PHP_URL_HOST))) {
    status_header(403);
    exit('Admins on a local site only.');
}
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(180);
require_once ABSPATH . 'wp-admin/includes/file.php';

if (!function_exists('wpiko_keymaster_stage_release') || !class_exists('WPiko_Chatbot_Pro_Updater')) {
    exit('FAIL  WPiko Keymaster 1.8.0+ and WPiko AI Chatbot Pro 2.1.4+ must both be active.');
}

global $wpdb;
$results = array();
$check = function ($ok, $label, $detail = '') use (&$results) {
    $results[] = ($ok ? 'PASS' : 'FAIL') . '  ' . $label . ($ok || $detail === '' ? '' : '  → ' . $detail);
};
$info = function ($label) use (&$results) {
    $results[] = 'INFO  ' . $label;
};

$type = 'wpikotest';
$slug = 'wpiko-chatbot-pro';
$licenses_table = $wpdb->prefix . 'wpiko_license_keys';
$sites_table = $wpdb->prefix . 'wpiko_update_sites';
$updater = WPiko_Chatbot_Pro_Updater::instance();

// Removes everything of the test product type (also after ?demo=1).
$remove_test_type = function () use ($wpdb, $type, $slug, $licenses_table, $sites_table) {
    $state = wpiko_keymaster_release_state($type);
    foreach (array('published', 'previous', 'staged') as $slot) {
        wpiko_keymaster_release_delete_file($state[$slot]);
    }
    $order_file = wpiko_keymaster_order_file($type);
    if ($order_file && is_file($order_file['path'])) {
        $zip_header = wpiko_keymaster_inspect_release_zip($order_file['path'], $slug);
        if (!is_wp_error($zip_header) && strpos($zip_header['version'], '9.9.') === 0) {
            wp_delete_file($order_file['path']);
        }
    }
    delete_option('wpiko_keymaster_release_state_' . $type);
    delete_option('wpiko_keymaster_release_downloads_' . $type);
    $settings = get_option('wpiko_keymaster_release_settings', array());
    unset($settings[$type]);
    update_option('wpiko_keymaster_release_settings', $settings, false);
    $types = get_option('wpiko_keymaster_product_types', array());
    unset($types[$type]);
    update_option('wpiko_keymaster_product_types', $types);
    $wpdb->delete($licenses_table, array('product_type' => $type));
    $wpdb->delete($sites_table, array('product_type' => $type));
};
if (isset($_GET['cleanup'])) {
    $remove_test_type();
    $updater->clear_cache();
    delete_site_transient('update_plugins');
    exit("Removed the test product type, its releases, licenses and statistics.\n");
}
$demo = isset($_GET['demo']);

// ── Saved state to restore ────────────────────────────────────
$saved_types = get_option('wpiko_keymaster_product_types', array());
$saved_release_settings = get_option('wpiko_keymaster_release_settings', array());
$created_files = array();
$license_ids = array();

// Leftovers of an interrupted earlier run.
$wpdb->delete($licenses_table, array('product_type' => $type));
$wpdb->delete($sites_table, array('product_type' => $type));
delete_option('wpiko_keymaster_release_downloads_' . $type);

$types = is_array($saved_types) ? $saved_types : array();
$types[$type] = array('name' => 'Update gate test', 'endpoint' => 'wpiko-chatbot');
update_option('wpiko_keymaster_product_types', $types);
delete_option('wpiko_keymaster_release_state_' . $type);

// ── Test signing keys ─────────────────────────────────────────
$pair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($pair);
$public_b64 = base64_encode(sodium_crypto_sign_publickey($pair));
$other = sodium_crypto_sign_keypair();
$other_secret = sodium_crypto_sign_secretkey($other);
$sign = function ($path, $key = null) use ($secret) {
    return base64_encode(sodium_crypto_sign_detached(file_get_contents($path), $key ?: $secret));
};
wpiko_keymaster_save_release_settings($type, array(
    'slug'         => $slug,
    'repo'         => '',
    'public_key'   => $public_b64,
    'auto_publish' => false,
    'purchase_url' => home_url('/test-pricing/'),
));
// The order file path is shared with the real product type (same plugin folder):
// keep a copy of a real one and put it back afterwards.
$order_file = wpiko_keymaster_order_file($type);
$saved_order_file = null;
if (is_file($order_file['path'])) {
    $leftover = wpiko_keymaster_inspect_release_zip($order_file['path'], $slug);
    if (!is_wp_error($leftover) && strpos($leftover['version'], '9.9.') === 0) {
        wp_delete_file($order_file['path']); // test ZIP left by an interrupted run
    } else {
        $saved_order_file = file_get_contents($order_file['path']);
    }
}
$release_keys_filter = function () use ($public_b64) {
    return array($public_b64);
};
add_filter('wpiko_chatbot_pro_release_keys', $release_keys_filter);

// ── Test ZIPs ────────────────────────────────────────────────
$make_zip = function ($version, $folder = null, $extra = array()) use ($slug, &$created_files) {
    $folder = $folder ?: $slug;
    $path = wp_tempnam('gate-test-' . $version) . '.zip';
    $created_files[] = $path;
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString($folder . '/' . $slug . '.php', "<?php\n/**\n * Plugin Name: WPiko AI Chatbot Pro\n * Version: {$version}\n * Requires at least: 6.0\n * Tested up to: 7.1\n * Requires PHP: 7.0\n */\n");
    $zip->addFromString($folder . '/index.php', "<?php\n// Silence is golden.\n");
    foreach ($extra as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    return $path;
};
$copy = function ($path) use (&$created_files) {
    $tmp = wp_tempnam('gate-test-copy');
    copy($path, $tmp);
    $created_files[] = $tmp;
    return $tmp;
};

// ── Test licenses ────────────────────────────────────────────
$encrypt = function ($plain) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    return base64_encode(openssl_encrypt($plain, 'aes-256-cbc', WPIKO_KEYMASTER_ENCRYPTION_KEY, 0, $iv) . '::' . $iv);
};
$add_license = function ($key, $domains, $expires = null, $trial = false) use ($wpdb, $licenses_table, $type, $encrypt, &$license_ids) {
    $wpdb->insert($licenses_table, array(
        'user_id'         => $trial ? 0 : get_current_user_id(),
        'product_id'      => $trial ? 0 : 999999,
        'license_key'     => $encrypt($key),
        'expiration_date' => $expires,
        'allowed_urls'    => 5,
        'active_domain'   => maybe_serialize($domains),
        'product_type'    => $type,
    ));
    $license_ids[] = (int) $wpdb->insert_id;
    return (int) $wpdb->insert_id;
};
$key_active = 'GATETEST-ACTIVE-' . wp_generate_password(12, false, false);
$key_trial_expired = 'GATETEST-TRIALX-' . wp_generate_password(12, false, false);
$key_other = 'GATETEST-OTHER-' . wp_generate_password(12, false, false);
$key_trial = 'GATETEST-TRIAL-' . wp_generate_password(12, false, false);
$site_active = 'https://gate-test-active.example';
$site_trial = 'https://gate-test-trial.example';
$id_active = $add_license($key_active, array($site_active));
$id_trial_expired = $add_license($key_trial_expired, array($site_trial), gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS), true);
$add_license($key_other, array('https://somewhere-else.example'));
$id_trial = $add_license($key_trial, array(), gmdate('Y-m-d H:i:s', time() + 5 * DAY_IN_SECONDS), true);

// ── HTTP helpers (this site's own REST API) ──────────────────
$api = rest_url('wpiko-keymaster/v1/');
$check_update = function ($body) use ($api, $type) {
    $response = wp_remote_post($api . 'update-check', array(
        'timeout'   => 20,
        'sslverify' => false,
        'body'      => array_merge(array('product_type' => $type, 'version' => '2.1.4', 'wp' => get_bloginfo('version'), 'php' => PHP_VERSION), $body),
    ));
    return array(
        'code' => (int) wp_remote_retrieve_response_code($response),
        'data' => json_decode(wp_remote_retrieve_body($response), true),
        'error' => is_wp_error($response) ? $response->get_error_message() : '',
    );
};
$get = function ($url) {
    $response = wp_remote_get($url, array('timeout' => 30, 'sslverify' => false));
    return array('code' => (int) wp_remote_retrieve_response_code($response), 'body' => wp_remote_retrieve_body($response));
};

try {
    // 1. ZIP checks
    $zip1 = $make_zip('9.9.1');
    $header = wpiko_keymaster_inspect_release_zip($zip1, $slug, '9.9.1');
    $check(!is_wp_error($header) && $header['version'] === '9.9.1' && $header['requires_php'] === '7.0', 'A good ZIP passes the checks and its header is read');
    $mismatch = wpiko_keymaster_inspect_release_zip($zip1, $slug, '9.9.0');
    $check(is_wp_error($mismatch) && $mismatch->get_error_code() === 'version_mismatch', 'A ZIP whose version differs from the release tag is refused');
    $wrong_folder = wpiko_keymaster_inspect_release_zip($make_zip('9.9.1', 'wpiko-chatbot-pro-9.9.1'), $slug);
    $check(is_wp_error($wrong_folder) && $wrong_folder->get_error_code() === 'bad_zip', 'A ZIP with the wrong top folder (like a GitHub source archive) is refused');
    $extra_file = wpiko_keymaster_inspect_release_zip($make_zip('9.9.1', null, array('evil.php' => '<?php')), $slug);
    $check(is_wp_error($extra_file), 'A ZIP with a file outside the plugin folder is refused');

    // 2. Staging and signatures
    $unsigned = wpiko_keymaster_stage_release($type, $copy($zip1), array('signature' => ''));
    $check(is_wp_error($unsigned) && $unsigned->get_error_code() === 'unsigned', 'An unsigned release is refused when a signing key is set');
    $bad_sig = wpiko_keymaster_stage_release($type, $copy($zip1), array('signature' => $sign($zip1, $other_secret)));
    $check(is_wp_error($bad_sig) && $bad_sig->get_error_code() === 'bad_signature', 'A release signed with another key is refused');
    $staged = wpiko_keymaster_stage_release($type, $copy($zip1), array('signature' => $sign($zip1), 'changelog' => "- Fix: test entry\n- Improve: **bold** text"));
    $check(!is_wp_error($staged) && $staged['version'] === '9.9.1' && empty($staged['published_at']), 'A correctly signed release is staged and waits (auto-publish off)');
    $state = wpiko_keymaster_release_state($type);
    $check($state['published'] === null, 'Nothing is offered to websites before Publish');

    $dir = wpiko_keymaster_release_dir();
    $check(is_file($dir . '.htaccess') && is_file($dir . 'index.php'), 'Release folder has a deny-all .htaccess and an index.php');
    $check((bool) preg_match('/^wpiko-chatbot-pro-9\.9\.1-[a-f0-9]{16}\.zip$/', $staged['file']), 'Stored file name has a random part (cannot be guessed)');

    // 3. Publish
    $published = wpiko_keymaster_publish_release($type);
    $check(!is_wp_error($published) && wpiko_keymaster_published_release($type)['version'] === '9.9.1', 'Publish makes 9.9.1 the published release');
    $check(is_file($order_file['path']) && hash_file('sha256', $order_file['path']) === $published['sha256'], 'Published release is copied to the protected WooCommerce download file');
    $check(strpos($order_file['url'], '/woocommerce_uploads/') !== false, 'WooCommerce download file is inside woocommerce_uploads');
    $not_newer = wpiko_keymaster_stage_release($type, $copy($zip1), array('signature' => $sign($zip1)));
    $check(is_wp_error($not_newer) && $not_newer->get_error_code() === 'not_newer', 'The same version cannot be staged again');

    $direct = $get(wp_upload_dir()['baseurl'] . '/wpiko-keymaster-releases/' . $published['file']);
    $info('Direct address of the stored release answers HTTP ' . $direct['code'] . ($direct['code'] === 200 ? ' (this local web server ignores .htaccess; the random file name still keeps it unguessable. On LiteSpeed/Apache it is 403.)' : ''));
    $order_direct = $get($order_file['url']);
    $info('Direct address of the WooCommerce download file answers HTTP ' . $order_direct['code'] . ($order_direct['code'] === 200 ? ' (local web server ignores .htaccess; check on wpiko.com that it is 403)' : ''));

    // 4. Update check over HTTP
    $none = $check_update(array('license_key' => '', 'domain' => 'https://no-license.example'));
    $check($none['code'] === 200 && $none['data']['version'] === '9.9.1' && $none['data']['license']['status'] === 'no_key' && empty($none['data']['package']), 'No license: told about 9.9.1, no download link', wp_json_encode($none));
    $check(!empty($none['data']['changelog']) && strpos($none['data']['changelog'], '<strong>bold</strong>') !== false, 'Release notes are sent as HTML');
    $wrong_site = $check_update(array('license_key' => $key_other, 'domain' => $site_active));
    $check($wrong_site['data']['license']['status'] === 'not_activated' && empty($wrong_site['data']['package']), 'Valid key, but not activated on this website: no download link');
    $unknown = $check_update(array('license_key' => 'NOT-A-REAL-KEY', 'domain' => $site_active));
    $check($unknown['data']['license']['status'] === 'not_found' && empty($unknown['data']['package']), 'Unknown key: no download link');
    $expired = $check_update(array('license_key' => $key_trial_expired, 'domain' => $site_trial));
    $check($expired['data']['license']['status'] === 'expired' && !empty($expired['data']['license']['trial']) && empty($expired['data']['package']), 'Ended free trial: no download link, marked as trial');
    $active = $check_update(array('license_key' => $key_active, 'domain' => $site_active . '/'));
    $check($active['data']['license']['status'] === 'active' && !empty($active['data']['package']) && $active['data']['sha256'] === $published['sha256'] && $active['data']['signature'] !== '', 'Active license on its website: download link, checksum and signature', wp_json_encode($active));
    $check(strpos((string) $active['data']['package'], $key_active) === false, 'The download link does not contain the license key');
    $bad_type = $check_update(array('product_type' => 'nope', 'license_key' => $key_active, 'domain' => $site_active));
    $check($bad_type['code'] === 404, 'Unknown product type answers 404');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sites_table} WHERE product_type = %s AND domain = %s", $type, 'gate-test-active.example'));
    $check($row && $row->version === '2.1.4' && (int) $row->license_id === $id_active, 'The licensed website and its version are recorded');
    $check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sites_table} WHERE product_type = %s", $type)) === 1, 'Websites without a valid license are not recorded');
    $stats = wpiko_keymaster_update_site_stats($type);
    $check(isset($stats['versions']['2.1.4']) && $stats['versions']['2.1.4'] === 1 && $stats['activated'] >= 2, 'Releases tab statistics count it');

    // 5. Download
    $download = $get($active['data']['package']);
    $check($download['code'] === 200 && hash('sha256', $download['body']) === $published['sha256'], 'The download link serves the exact published ZIP', 'HTTP ' . $download['code']);
    $tampered = $get(preg_replace('/.(?=&|$)/', 'x', $active['data']['package'], 1) . 'x');
    $check($tampered['code'] === 403, 'A changed download link is refused', 'HTTP ' . $tampered['code']);
    $wpdb->update($licenses_table, array('active_domain' => maybe_serialize(array('https://moved.example'))), array('id' => $id_active));
    $revoked = $get($active['data']['package']);
    $check($revoked['code'] === 403, 'The link stops working once the license is no longer activated on that website', 'HTTP ' . $revoked['code']);
    $wpdb->update($licenses_table, array('active_domain' => maybe_serialize(array($site_active))), array('id' => $id_active));

    $trial_url = wpiko_keymaster_trial_download_url($id_trial);
    $trial_download = $trial_url !== '' ? $get($trial_url) : array('code' => 0, 'body' => '');
    $check($trial_download['code'] === 200 && hash('sha256', $trial_download['body']) === $published['sha256'], 'A trial link downloads the published release while the trial runs', 'HTTP ' . $trial_download['code']);
    $wpdb->update($licenses_table, array('expiration_date' => gmdate('Y-m-d H:i:s', time() - 60)), array('id' => $id_trial));
    $trial_ended = $get($trial_url);
    $check($trial_ended['code'] === 403, 'The trial link stops working when the trial ends', 'HTTP ' . $trial_ended['code']);
    $check(wpiko_keymaster_trial_download_url($id_trial_expired) === '', 'No trial link is made for an ended trial');
    wp_cache_delete('wpiko_keymaster_release_downloads_' . $type, 'options');
    $downloads = wpiko_keymaster_release_download_count($type, '9.9.1');
    $check($downloads === 2, 'Downloads are counted per version (refused links are not)', (string) $downloads);

    // 6. Pro updater against this site's Keymaster
    $base_filter = function () use ($api) {
        return $api;
    };
    $ssl_filter = '__return_false';
    $body_for = function ($key, $domain) use ($type) {
        return function ($body) use ($key, $domain, $type) {
            return array_merge($body, array('product_type' => $type, 'license_key' => $key, 'domain' => $domain));
        };
    };
    add_filter('wpiko_chatbot_pro_update_api_base', $base_filter);
    add_filter('wpiko_chatbot_pro_update_sslverify', $ssl_filter);
    $body_active = $body_for($key_active, $site_active);
    add_filter('wpiko_chatbot_pro_update_request', $body_active);

    $updater->clear_cache();
    $info_active = $updater->get_info(true);
    $check(is_array($info_active) && $info_active['version'] === '9.9.1' && !empty($info_active['package']), 'Pro reads the latest version and gets a download link');
    $update = $updater->filter_update(false, array(), WPIKO_CHATBOT_PRO_BASENAME);
    $check(is_array($update) && $update['version'] === '9.9.1' && $update['package'] !== '', 'Pro tells WordPress about 9.9.1 with a package');
    $check($updater->filter_update(false, array(), 'other-plugin/other-plugin.php') === false, 'Pro leaves other plugins alone');

    delete_site_transient('update_plugins');
    wp_update_plugins();
    $plugins_transient = get_site_transient('update_plugins');
    $offer = isset($plugins_transient->response[WPIKO_CHATBOT_PRO_BASENAME]) ? $plugins_transient->response[WPIKO_CHATBOT_PRO_BASENAME] : null;
    $check($offer && $offer->new_version === '9.9.1' && !empty($offer->package), 'WordPress\'s own update check lists 9.9.1 (Update URI hook works)');

    $file = $updater->pre_download(false, $update['package'], null, array('plugin' => WPIKO_CHATBOT_PRO_BASENAME));
    $check(is_string($file) && is_file($file) && hash_file('sha256', $file) === $published['sha256'], 'The updater downloads the ZIP and accepts its checksum and signature', is_wp_error($file) ? $file->get_error_message() : '');
    if (is_string($file) && is_file($file)) {
        $created_files[] = $file;
    }
    $foreign = $updater->pre_download(false, 'https://github.com/WPiko/wpiko-chatbot-pro/archive/refs/tags/v9.9.1.zip', null, array('plugin' => WPIKO_CHATBOT_PRO_BASENAME));
    $check(is_wp_error($foreign) && $foreign->get_error_code() === 'wpiko_update_source', 'A package from anywhere else is refused for this plugin');
    $check($updater->pre_download(false, 'https://example.com/other.zip', null, array('plugin' => 'other/other.php')) === false, 'Other plugins\' downloads are left alone');
    $server_host = (string) wp_parse_url($api, PHP_URL_HOST);
    $check($updater->is_our_package('https://www.' . $server_host . '/wp-json/wpiko-keymaster/v1/download?t=abc') && $updater->is_our_package('https://' . $server_host . '/?rest_route=/wpiko-keymaster/v1/download&t=abc'), 'Download links with www., https or ?rest_route= count as the update server');
    $check(!$updater->is_our_package('https://evil.example/wp-json/wpiko-keymaster/v1/download?t=abc') && !$updater->is_our_package('https://' . $server_host . '.evil.example/wp-json/wpiko-keymaster/v1/download'), 'Look-alike addresses on other hosts do not');

    $tampered_zip = $copy($zip1);
    file_put_contents($tampered_zip, 'x', FILE_APPEND);
    $result = $updater->verify_package($tampered_zip, $info_active);
    $check(is_wp_error($result), 'A changed ZIP is refused (checksum)');
    $result = $updater->verify_package($copy($zip1), array_merge($info_active, array('signature' => $sign($zip1, $other_secret))));
    $check(is_wp_error($result) && $result->get_error_code() === 'wpiko_update_signature', 'A ZIP signed with another key is refused (signature)');
    $result = $updater->verify_package($copy($zip1), array_merge($info_active, array('signature' => '')));
    $check(is_wp_error($result) && $result->get_error_code() === 'wpiko_update_signature', 'An unsigned ZIP is refused');
    remove_filter('wpiko_chatbot_pro_release_keys', $release_keys_filter);
    $check(!WPiko_Chatbot_Pro_Updater::signature_is_valid(file_get_contents($zip1), $sign($zip1)), 'Without the test filter only the real WPiko release key is accepted');
    add_filter('wpiko_chatbot_pro_release_keys', $release_keys_filter);

    remove_filter('wpiko_chatbot_pro_update_request', $body_active);
    $body_expired = $body_for($key_trial_expired, $site_trial);
    add_filter('wpiko_chatbot_pro_update_request', $body_expired);
    $updater->clear_cache();
    $update = $updater->filter_update(false, array(), WPIKO_CHATBOT_PRO_BASENAME);
    $check(is_array($update) && $update['version'] === '9.9.1' && $update['package'] === '', 'Ended trial: WordPress shows 9.9.1 without a package');
    $message = $updater->license_message($updater->get_info());
    $check(strpos($message, 'free trial has ended') !== false && strpos($message, '/test-pricing/') !== false, 'Ended trial: the message links to the pricing page');
    $refused = $updater->pre_download(false, $info_active['package'], null, array('plugin' => WPIKO_CHATBOT_PRO_BASENAME));
    $check(is_wp_error($refused) && $refused->get_error_code() === 'wpiko_update_license', 'Ended trial: an old download link from the update list cannot be used');
    remove_filter('wpiko_chatbot_pro_update_request', $body_expired);

    // 7. Rollback
    $zip2 = $make_zip('9.9.2');
    wpiko_keymaster_stage_release($type, $copy($zip2), array('signature' => $sign($zip2)));
    wpiko_keymaster_publish_release($type);
    $check(wpiko_keymaster_published_release($type)['version'] === '9.9.2', '9.9.2 published');
    $rolled = wpiko_keymaster_rollback_release($type);
    $state = wpiko_keymaster_release_state($type);
    $check(!is_wp_error($rolled) && $state['published']['version'] === '9.9.1' && $state['previous']['version'] === '9.9.2', 'Go back to 9.9.1 works and keeps 9.9.2 as previous');
    $check(hash_file('sha256', $order_file['path']) === $state['published']['sha256'], 'The WooCommerce download file follows the rollback');

    // 8. Release ping
    $ping = wp_remote_post($api . 'release-ping', array('timeout' => 20, 'sslverify' => false, 'body' => array('product_type' => $type, 'secret' => 'wrong')));
    $check((int) wp_remote_retrieve_response_code($ping) === 403, 'Release ping with a wrong secret is refused');
    $ping = wp_remote_post($api . 'release-ping', array('timeout' => 20, 'sslverify' => false, 'body' => array('product_type' => $type, 'secret' => wpiko_keymaster_release_ping_secret($type))));
    $ping_data = json_decode(wp_remote_retrieve_body($ping), true);
    $check((int) wp_remote_retrieve_response_code($ping) === 200 && isset($ping_data['message']), 'Release ping with the right secret runs a check', wp_remote_retrieve_body($ping));

    // 9. Secrets
    $enc = wpiko_keymaster_release_encrypt('github_pat_TESTTOKEN123456789');
    $check(strpos($enc, 'TESTTOKEN') === false && wpiko_keymaster_release_decrypt($enc) === 'github_pat_TESTTOKEN123456789', 'GitHub token is stored encrypted and can be read back');
    $check(wpiko_keymaster_release_decrypt(base64_encode(random_bytes(60))) === '', 'A damaged stored token reads as empty');

    // 10. Real GitHub release (public repository, no token)
    if (!isset($_GET['github']) || $_GET['github'] !== '0') {
        $latest = wpiko_keymaster_github_api('repos/WPiko/wpiko-chatbot-pro/releases/tags/v2.1.3', '');
        $asset = null;
        if (!is_wp_error($latest)) {
            foreach ($latest['assets'] as $candidate) {
                if ($candidate['name'] === 'wpiko-chatbot-pro-2.1.3.zip') {
                    $asset = $candidate;
                }
            }
        }
        if ($asset) {
            $tmp = wpiko_keymaster_github_download_asset($asset['url'], '');
            if (!is_wp_error($tmp)) {
                $created_files[] = $tmp;
            }
            $header = is_wp_error($tmp) ? $tmp : wpiko_keymaster_inspect_release_zip($tmp, $slug, '2.1.3');
            $check(!is_wp_error($header) && $header['version'] === '2.1.3', 'Real GitHub release asset v2.1.3 downloads through the redirect and passes the ZIP checks', is_wp_error($header) ? $header->get_error_message() : '');
        } else {
            $info('GitHub release v2.1.3 could not be read: ' . (is_wp_error($latest) ? $latest->get_error_message() : 'no asset'));
        }
    }
} catch (Throwable $e) {
    $results[] = 'FAIL  Exception: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
}

// ── Cleanup ──────────────────────────────────────────────────
if ($demo) {
    // Keep 9.9.1 published, add a waiting 9.9.3 and one recorded website, drop the rest.
    $zip3 = $make_zip('9.9.3');
    wpiko_keymaster_stage_release($type, $copy($zip3), array('signature' => $sign($zip3), 'changelog' => "- Feature: demo release waiting for Publish\n- Fix: **demo** entry"));
    foreach ($created_files as $path) {
        if (is_file($path)) {
            wp_delete_file($path);
        }
    }
    $updater->clear_cache();
    delete_site_transient('update_plugins');
    echo implode("\n", $results) . "\n\nDemo left in Keymaster → Releases (\"Update gate test\"). Remove it with ?cleanup=1\n";
    exit;
}
$state = wpiko_keymaster_release_state($type);
foreach (array('published', 'previous', 'staged') as $slot) {
    wpiko_keymaster_release_delete_file($state[$slot]);
}
delete_option('wpiko_keymaster_release_state_' . $type);
delete_option('wpiko_keymaster_release_downloads_' . $type);
update_option('wpiko_keymaster_release_settings', $saved_release_settings, false);
update_option('wpiko_keymaster_product_types', $saved_types);
if ($saved_order_file !== null) {
    file_put_contents($order_file['path'], $saved_order_file);
} elseif (is_file($order_file['path'])) {
    wp_delete_file($order_file['path']);
}
foreach ($license_ids as $id) {
    $wpdb->delete($licenses_table, array('id' => $id));
}
$wpdb->delete($sites_table, array('product_type' => $type));
foreach ($created_files as $path) {
    if (is_file($path)) {
        wp_delete_file($path);
    }
}
remove_all_filters('wpiko_chatbot_pro_update_request');
remove_all_filters('wpiko_chatbot_pro_update_api_base');
remove_all_filters('wpiko_chatbot_pro_update_sslverify');
remove_filter('wpiko_chatbot_pro_release_keys', $release_keys_filter);
$updater->clear_cache();
delete_site_transient('update_plugins');
$leftover = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$licenses_table} WHERE product_type = %s", $type));
$results[] = ($leftover === 0 && !get_option('wpiko_keymaster_release_state_' . $type) ? 'PASS' : 'FAIL') . '  Cleanup: test licenses, releases, files and settings removed';

$failed = count(array_filter($results, function ($line) {
    return strpos($line, 'FAIL') === 0;
}));
$passed = count(array_filter($results, function ($line) {
    return strpos($line, 'PASS') === 0;
}));
echo implode("\n", $results) . "\n\n" . ($failed ? "FAILED: {$failed} of " . ($passed + $failed) : "All {$passed} checks passed") . "\n";
