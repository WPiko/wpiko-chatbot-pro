<?php
/**
 * Plugin updates from wpiko.com.
 *
 * The site asks the WPiko license server (Keymaster) about new versions, sending its
 * license key, address and current version. Every site learns that a new version
 * exists; only a site whose license is valid and activated on it gets a download
 * link. Before WordPress installs an update, the ZIP must match the checksum the
 * server sent and carry a valid Ed25519 signature made with the WPiko release key
 * built into this file. A changed or unsigned ZIP is never installed, even if the
 * download server were compromised.
 *
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPiko_Chatbot_Pro_Updater
{
    const SLUG = 'wpiko-chatbot-pro';
    const PRODUCT_TYPE = 'chatbot';
    const CACHE = 'wpiko_chatbot_pro_update_info';

    /**
     * Public keys of the WPiko release signing key (base64 Ed25519).
     */
    const RELEASE_KEYS = array(
        '9xoPXN/cXtYkbTrxPsoiRJ+0N40mZdJ4EOf+bs1g9es=',
    );

    /** @var WPiko_Chatbot_Pro_Updater|null */
    private static $instance = null;

    /**
     * @return WPiko_Chatbot_Pro_Updater
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register the hooks. Runs on every request, so automatic background updates
     * (WP-Cron) use the same checks as the Plugins screen.
     */
    public function init()
    {
        add_filter('update_plugins_' . self::SLUG, array($this, 'filter_update'), 10, 3);
        add_filter('plugins_api', array($this, 'plugins_api'), 20, 3);
        add_filter('upgrader_pre_download', array($this, 'pre_download'), 10, 4);
        add_action('in_plugin_update_message-' . WPIKO_CHATBOT_PRO_BASENAME, array($this, 'update_message'), 10, 2);
        add_action('upgrader_process_complete', array($this, 'after_update'), 10, 2);

        // A new or removed license changes what the server answers.
        foreach (array('wpiko_chatbot_license_key', 'wpiko_chatbot_license_status', 'wpiko_chatbot_license_domain') as $option) {
            add_action('add_option_' . $option, array($this, 'clear_cache'));
            add_action('update_option_' . $option, array($this, 'clear_cache'));
            add_action('delete_option_' . $option, array($this, 'clear_cache'));
        }
    }

    // ─────────────────────────────────────────────────────────
    // Talking to the license server
    // ─────────────────────────────────────────────────────────

    /**
     * Address of a Keymaster API endpoint.
     *
     * @param string $endpoint 'update-check' or 'download'.
     * @return string
     */
    public function api_url($endpoint)
    {
        $base = 'https://' . wpiko_chatbot_pro_license_server_host() . '/wp-json/wpiko-keymaster/v1/';
        /**
         * Filter the license server API base address (ends with a slash).
         *
         * @param string $base Base address.
         */
        $base = (string) apply_filters('wpiko_chatbot_pro_update_api_base', $base);
        return trailingslashit($base) . $endpoint;
    }

    /**
     * What this site sends with an update check.
     *
     * @return array
     */
    public function request_body()
    {
        $key = function_exists('wpiko_chatbot_pro_decrypt_data') ? wpiko_chatbot_pro_decrypt_data(get_option('wpiko_chatbot_license_key', '')) : '';
        $domain = (string) get_option('wpiko_chatbot_license_domain', '');
        $body = array(
            'product_type' => self::PRODUCT_TYPE,
            'license_key'  => is_string($key) ? $key : '',
            'domain'       => $domain !== '' ? $domain : home_url(),
            'version'      => WPIKO_CHATBOT_PRO_VERSION,
            'wp'           => get_bloginfo('version'),
            'php'          => PHP_VERSION,
        );
        /**
         * Filter the update check request body.
         *
         * @param array $body Body.
         */
        return (array) apply_filters('wpiko_chatbot_pro_update_request', $body);
    }

    /**
     * Latest release information from the license server (cached 12 hours, errors 1 hour).
     *
     * @param bool $force Ask the server again.
     * @return array|WP_Error
     */
    public function get_info($force = false)
    {
        if (!$force) {
            $cached = get_site_transient(self::CACHE);
            if (is_array($cached)) {
                return isset($cached['error']) ? new WP_Error('wpiko_update_check_failed', $cached['error']) : $cached;
            }
        }

        $response = wp_remote_post($this->api_url('update-check'), array(
            'timeout'   => 15,
            'sslverify' => (bool) apply_filters('wpiko_chatbot_pro_update_sslverify', true),
            'body'      => $this->request_body(),
        ));

        $error = '';
        $data = null;
        if (is_wp_error($response)) {
            $error = 'The WPiko update server could not be reached: ' . $response->get_error_message();
        } elseif ((int) wp_remote_retrieve_response_code($response) !== 200) {
            $error = sprintf('The WPiko update server answered %d.', (int) wp_remote_retrieve_response_code($response));
        } else {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data) || !isset($data['license']['status'])) {
                $error = 'The WPiko update server sent an unreadable answer.';
            }
        }

        if ($error !== '') {
            set_site_transient(self::CACHE, array('error' => $error, 'checked_at' => time()), HOUR_IN_SECONDS);
            return new WP_Error('wpiko_update_check_failed', $error);
        }

        $data['checked_at'] = time();
        set_site_transient(self::CACHE, $data, 12 * HOUR_IN_SECONDS);
        return $data;
    }

    /**
     * Forget the cached answer (license changed, update installed, "Check for updates").
     */
    public function clear_cache()
    {
        delete_site_transient(self::CACHE);
    }

    // ─────────────────────────────────────────────────────────
    // WordPress update hooks
    // ─────────────────────────────────────────────────────────

    /**
     * Tell WordPress about the latest version (Update URI: wpiko-chatbot-pro).
     *
     * @param array|false $update      Update data.
     * @param array       $plugin_data Plugin headers.
     * @param string      $plugin_file Plugin basename.
     * @return array|false
     */
    public function filter_update($update, $plugin_data, $plugin_file)
    {
        if ($plugin_file !== WPIKO_CHATBOT_PRO_BASENAME) {
            return $update;
        }
        $info = $this->get_info();
        if (is_wp_error($info) || empty($info['version'])) {
            return $update;
        }
        return array(
            'slug'         => self::SLUG,
            'version'      => (string) $info['version'],
            'url'          => !empty($info['purchase_url']) ? $info['purchase_url'] : 'https://wpiko.com/chatbot',
            'package'      => !empty($info['package']) ? (string) $info['package'] : '',
            'requires'     => isset($info['requires']) ? (string) $info['requires'] : '',
            'tested'       => isset($info['tested']) ? (string) $info['tested'] : '',
            'requires_php' => isset($info['requires_php']) ? (string) $info['requires_php'] : '',
        );
    }

    /**
     * "View details" window on the Plugins screen.
     *
     * @param false|object|array $result Result.
     * @param string             $action Action.
     * @param object             $args   Arguments.
     * @return false|object|array
     */
    public function plugins_api($result, $action, $args)
    {
        if ($action !== 'plugin_information' || !is_object($args) || !isset($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }
        $info = $this->get_info();
        if (is_wp_error($info) || empty($info['version'])) {
            return $result;
        }
        $headers = get_file_data(WPIKO_CHATBOT_PRO_FILE, array('requires' => 'Requires at least', 'tested' => 'Tested up to', 'requires_php' => 'Requires PHP'));
        return (object) array(
            'name'          => 'WPiko AI Chatbot Pro',
            'slug'          => self::SLUG,
            'version'       => (string) $info['version'],
            'author'        => '<a href="https://wpiko.com">WPiko</a>',
            'homepage'      => 'https://wpiko.com/chatbot',
            'requires'      => !empty($info['requires']) ? $info['requires'] : $headers['requires'],
            'tested'        => !empty($info['tested']) ? $info['tested'] : $headers['tested'],
            'requires_php'  => !empty($info['requires_php']) ? $info['requires_php'] : $headers['requires_php'],
            'last_updated'  => isset($info['last_updated']) ? $info['last_updated'] : '',
            'download_link' => !empty($info['package']) ? $info['package'] : '',
            'sections'      => array(
                'description' => 'Pro add-on for WPiko AI Chatbot: mobile app with live takeover and push notifications, WooCommerce product answers, website scanning, analytics and lead capture.',
                'changelog'   => !empty($info['changelog']) ? wp_kses_post($info['changelog']) : 'See wpiko.com for the release notes.',
            ),
        );
    }

    /**
     * Whether a package address points at the WPiko download endpoint.
     *
     * @param string $package Package address.
     * @return bool
     */
    public function is_our_package($package)
    {
        if (!is_string($package) || $package === '') {
            return false;
        }
        $host = function ($url) {
            return preg_replace('/^www\./', '', strtolower((string) wp_parse_url($url, PHP_URL_HOST)));
        };
        $expected = $this->api_url('download');
        $path = (string) wp_parse_url($package, PHP_URL_PATH);
        $query = (string) wp_parse_url($package, PHP_URL_QUERY);
        $is_download = substr(untrailingslashit($path), -strlen('/wpiko-keymaster/v1/download')) === '/wpiko-keymaster/v1/download'
            || strpos($query, 'rest_route=%2Fwpiko-keymaster%2Fv1%2Fdownload') !== false
            || strpos($query, 'rest_route=/wpiko-keymaster/v1/download') !== false;
        return $is_download && $host($package) !== '' && $host($package) === $host($expected);
    }

    /**
     * Download and check the update before WordPress unpacks it.
     *
     * A fresh download link is asked for right before the download (links in
     * WordPress's update list can be hours old). The ZIP must match the server's
     * checksum and the WPiko release signature.
     *
     * @param bool|string|WP_Error $reply      Earlier filter result.
     * @param string               $package    Package address.
     * @param WP_Upgrader          $upgrader   Upgrader.
     * @param array                $hook_extra Extra data (plugin basename).
     * @return bool|string|WP_Error Path of the checked ZIP, or an error.
     */
    public function pre_download($reply, $package, $upgrader = null, $hook_extra = array())
    {
        if ($reply !== false) {
            return $reply;
        }
        $is_ours = $this->is_our_package($package);
        $for_this_plugin = is_array($hook_extra) && isset($hook_extra['plugin']) && $hook_extra['plugin'] === WPIKO_CHATBOT_PRO_BASENAME;
        if (!$is_ours) {
            if ($for_this_plugin && wp_http_validate_url($package)) {
                // Updates of this plugin only come from the WPiko update server.
                return new WP_Error('wpiko_update_source', 'WPiko AI Chatbot Pro updates can only be installed from wpiko.com. Refresh the Plugins page and try again.');
            }
            return $reply;
        }

        $info = $this->get_info(true);
        if (is_wp_error($info)) {
            return $info;
        }
        if (empty($info['package'])) {
            return new WP_Error('wpiko_update_license', wp_strip_all_tags($this->license_message($info)));
        }

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $file = download_url($info['package'], 300);
        if (is_wp_error($file)) {
            return new WP_Error('wpiko_update_download', 'The update could not be downloaded from wpiko.com: ' . $file->get_error_message());
        }

        $checked = $this->verify_package($file, $info);
        if (is_wp_error($checked)) {
            wp_delete_file($file);
            return $checked;
        }
        return $file;
    }

    /**
     * Check a downloaded ZIP against the checksum and the release signature.
     *
     * @param string $file ZIP path.
     * @param array  $info Update information (sha256, signature).
     * @return true|WP_Error
     */
    public function verify_package($file, $info)
    {
        $contents = @file_get_contents($file);
        if ($contents === false || $contents === '') {
            return new WP_Error('wpiko_update_invalid', 'The downloaded update is empty.');
        }
        if (empty($info['sha256']) || !hash_equals(strtolower((string) $info['sha256']), hash('sha256', $contents))) {
            return new WP_Error('wpiko_update_invalid', 'The downloaded update does not match the expected checksum, so it was not installed.');
        }
        if (!self::signature_is_valid($contents, isset($info['signature']) ? (string) $info['signature'] : '')) {
            return new WP_Error('wpiko_update_signature', 'The downloaded update has no valid WPiko signature, so it was not installed. Please contact WPiko support.');
        }
        return true;
    }

    /**
     * Whether a signature was made with a WPiko release key.
     *
     * @param string $contents      File contents.
     * @param string $signature_b64 Base64 signature.
     * @return bool
     */
    public static function signature_is_valid($contents, $signature_b64)
    {
        $signature = base64_decode(trim($signature_b64), true);
        if ($signature === false || strlen($signature) !== 64 || !function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }
        /**
         * Filter the accepted release public keys (base64 Ed25519). For tests.
         *
         * @param string[] $keys Keys.
         */
        $keys = (array) apply_filters('wpiko_chatbot_pro_release_keys', self::RELEASE_KEYS);
        foreach ($keys as $key_b64) {
            $key = base64_decode((string) $key_b64, true);
            if ($key === false || strlen($key) !== 32) {
                continue;
            }
            try {
                if (sodium_crypto_sign_verify_detached($signature, $contents, $key)) {
                    return true;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Line under the update on the Plugins screen when this site cannot download it.
     *
     * @param array  $plugin_data Plugin data.
     * @param object $response    Update data.
     */
    public function update_message($plugin_data, $response)
    {
        if (!empty($response->package)) {
            return;
        }
        $info = $this->get_info();
        if (is_wp_error($info)) {
            return;
        }
        echo ' ' . wp_kses($this->license_message($info), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()), 'strong' => array()));
    }

    /**
     * Why this site cannot download the update, with a link to fix it.
     *
     * @param array $info Update information.
     * @return string HTML.
     */
    public function license_message($info)
    {
        $status = isset($info['license']['status']) ? $info['license']['status'] : '';
        $trial = !empty($info['license']['trial']);
        $license_page = admin_url('admin.php?page=ai-chatbot&tab=license_activation');
        $buy = !empty($info['purchase_url']) ? $info['purchase_url'] : 'https://wpiko.com/chatbot-pricing/';
        $buy_link = '<a href="' . esc_url($buy) . '" target="_blank" rel="noopener">' . esc_html__('Get a lifetime license', 'wpiko-chatbot-pro') . '</a>';
        $license_link = '<a href="' . esc_url($license_page) . '">' . esc_html__('License page', 'wpiko-chatbot-pro') . '</a>';

        switch ($status) {
            case 'expired':
                return $trial
                    /* translators: %s: link */
                    ? sprintf(esc_html__('Your free trial has ended, so this update cannot be installed. %s to keep getting updates.', 'wpiko-chatbot-pro'), $buy_link)
                    /* translators: %s: link */
                    : sprintf(esc_html__('Your license has expired, so this update cannot be installed. %s.', 'wpiko-chatbot-pro'), $buy_link);
            case 'not_activated':
                /* translators: %s: link */
                return sprintf(esc_html__('Your license is not activated on this website, so this update cannot be installed. Activate it on the %s.', 'wpiko-chatbot-pro'), $license_link);
            case 'not_found':
                /* translators: %s: link */
                return sprintf(esc_html__('Your license key was not recognized, so this update cannot be installed. Check it on the %s.', 'wpiko-chatbot-pro'), $license_link);
            case 'no_key':
                /* translators: 1: link to license page, 2: link to pricing */
                return sprintf(esc_html__('Activate your license key on the %1$s to install this update. No license yet? %2$s.', 'wpiko-chatbot-pro'), $license_link, $buy_link);
        }
        return esc_html__('This update is not available for this website right now.', 'wpiko-chatbot-pro');
    }

    /**
     * Forget the cached answer after this plugin was updated.
     *
     * @param WP_Upgrader $upgrader Upgrader.
     * @param array       $options  Options.
     */
    public function after_update($upgrader, $options)
    {
        if (isset($options['type'], $options['plugins']) && $options['type'] === 'plugin' && in_array(WPIKO_CHATBOT_PRO_BASENAME, (array) $options['plugins'], true)) {
            $this->clear_cache();
        }
    }

    // ─────────────────────────────────────────────────────────
    // Status for the License page
    // ─────────────────────────────────────────────────────────

    /**
     * Ask the server now and refresh WordPress's update list.
     *
     * @return array Status (see status()).
     */
    public function check_now()
    {
        $this->clear_cache();
        $this->get_info(true);
        delete_site_transient('update_plugins');
        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . WPINC . '/update.php';
        }
        wp_update_plugins();
        return $this->status();
    }

    /**
     * Update status from the cached answer.
     *
     * @return array current, latest, update_available, can_download, license_status, error, message, checked_at
     */
    public function status()
    {
        $info = $this->get_info();
        $cached = get_site_transient(self::CACHE);
        $status = array(
            'current'          => WPIKO_CHATBOT_PRO_VERSION,
            'latest'           => '',
            'update_available' => false,
            'can_download'     => false,
            'license_status'   => '',
            'error'            => '',
            'message'          => '',
            'checked_at'       => is_array($cached) && isset($cached['checked_at']) ? (int) $cached['checked_at'] : 0,
        );
        if (is_wp_error($info)) {
            $status['error'] = $info->get_error_message();
            return $status;
        }
        $status['latest'] = isset($info['version']) ? (string) $info['version'] : '';
        $status['update_available'] = $status['latest'] !== '' && version_compare(WPIKO_CHATBOT_PRO_VERSION, $status['latest'], '<');
        $status['can_download'] = !empty($info['package']);
        $status['license_status'] = isset($info['license']['status']) ? $info['license']['status'] : '';
        if ($status['update_available'] && !$status['can_download']) {
            $status['message'] = $this->license_message($info);
        }
        return $status;
    }
}

WPiko_Chatbot_Pro_Updater::instance()->init();
