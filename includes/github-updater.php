<?php
/**
 * GitHub Plugin Updater
 * 
 * Handles automatic updates for the WPiko Chatbot Pro plugin via GitHub Releases.
 * 
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WPiko_Chatbot_Pro_GitHub_Updater {
    
    /**
     * GitHub configuration
     */
    private $github_user;
    private $github_repo;
    private $github_token;
    private $config;
    
    /**
     * Plugin configuration
     */
    private $plugin_slug;
    private $plugin_basename;
    private $plugin_version;
    private $plugin_file;
    
    /**
     * Transient names for caching
     */
    private $version_transient_name;
    private $info_transient_name;
    
    /**
     * Constructor
     */
    public function __construct($plugin_file, $plugin_slug, $plugin_version) {
        // Load configuration
        $this->config = WPiko_Chatbot_Pro_GitHub_Config::get_config();
        $this->github_user = $this->config['github_user'];
        $this->github_repo = $this->config['github_repo'];
        $this->github_token = $this->config['github_token'];
        
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = $plugin_slug;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_version = $plugin_version;
        
        $this->version_transient_name = 'wpiko_chatbot_pro_github_version';
        $this->info_transient_name = 'wpiko_chatbot_pro_github_info';
        
        // Only initialize if configuration is valid
        $validation = WPiko_Chatbot_Pro_GitHub_Config::validate_config();
        if (!is_wp_error($validation)) {
            $this->init_hooks();
        } else {
            // Log configuration error if logging is available
            if (function_exists('wpiko_chatbot_log')) {
                wpiko_chatbot_log('GitHub updater configuration error: ' . $validation->get_error_message(), 'error');
            }
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_api_call'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection'), 10, 3);
        add_action('upgrader_process_complete', array($this, 'after_update'), 10, 2);
        
        // Add custom update message
        add_action("in_plugin_update_message-{$this->plugin_basename}", array($this, 'plugin_update_message'));
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get remote version
        $remote_version = $this->get_remote_version();
        if (!$remote_version) {
            return $transient;
        }
        
        // Compare versions
        if (version_compare($this->plugin_version, $remote_version, '<')) {
            $transient->response[$this->plugin_basename] = array(
                'slug' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => $this->get_github_repo_url(),
                'package' => $this->get_download_url($remote_version),
            );
        }
        
        return $transient;
    }
    
    /**
     * Get remote version from GitHub
     */
    private function get_remote_version() {
        // Check for cached version
        $version = get_transient($this->version_transient_name);
        if ($version !== false) {
            return $version;
        }
        
        // Fetch latest release from GitHub
        $request = $this->make_github_api_request('/releases/latest');
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true);
        
        if (!isset($data['tag_name'])) {
            return false;
        }
        
        $version = ltrim($data['tag_name'], 'v'); // Remove 'v' prefix if present
        
        // Cache for the configured frequency
        $cache_duration = isset($this->config['check_frequency']) ? $this->config['check_frequency'] * HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS;
        set_transient($this->version_transient_name, $version, $cache_duration);
        
        return $version;
    }
    
    /**
     * Get plugin information for the update screen
     */
    public function plugin_api_call($false, $action, $response) {
        if ($action !== 'plugin_information' || $response->slug !== $this->plugin_slug) {
            return $false;
        }
        
        // Check for cached info
        $info = get_transient($this->info_transient_name);
        if ($info !== false) {
            return $info;
        }
        
        // Fetch release info from GitHub
        $request = $this->make_github_api_request('/releases/latest');
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
            return $false;
        }
        
        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true);
        
        if (!isset($data['tag_name'])) {
            return $false;
        }
        
        $info = new stdClass();
        $info->name = 'WPiko Chatbot Pro';
        $info->slug = $this->plugin_slug;
        $info->version = ltrim($data['tag_name'], 'v');
        $info->author = 'WPiko';
        $info->homepage = $this->get_github_repo_url();
        $info->download_link = $this->get_download_url($info->version);
        $info->requires = '5.0';
        $info->tested = get_bloginfo('version');
        $info->requires_php = '7.4';
        $info->last_updated = $data['published_at'];
        $info->sections = array(
            'description' => 'Premium add-on for WPiko Chatbot with advanced features.',
            'changelog' => $this->parse_changelog($data['body'])
        );
        
        // Cache for the configured frequency
        $cache_duration = isset($this->config['check_frequency']) ? $this->config['check_frequency'] * HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS;
        set_transient($this->info_transient_name, $info, $cache_duration);
        
        return $info;
    }
    
    /**
     * Make GitHub API request
     */
    private function make_github_api_request($endpoint) {
        $url = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}{$endpoint}";
        
        $args = array(
            'headers' => array(
                'Authorization' => 'token ' . $this->github_token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WPiko-Chatbot-Pro-Updater'
            ),
            'timeout' => 30
        );
        
        return wp_remote_get($url, $args);
    }
    
    /**
     * Get download URL for specific version
     */
    private function get_download_url($version) {
        return "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/zipball/v{$version}";
    }
    
    /**
     * Get GitHub repository URL
     */
    private function get_github_repo_url() {
        return "https://github.com/{$this->github_user}/{$this->github_repo}";
    }
    
    /**
     * Fix plugin folder name after download
     */
    public function upgrader_source_selection($source, $remote_source, $upgrader) {
        if (!isset($upgrader->skin->plugin_info) || $upgrader->skin->plugin_info['Name'] !== 'WPiko Chatbot Pro') {
            return $source;
        }
        
        $corrected_source = trailingslashit($remote_source) . $this->plugin_slug . '/';
        
        if (rename($source, $corrected_source)) {
            return $corrected_source;
        }
        
        return $source;
    }
    
    /**
     * Clear transients after update
     */
    public function after_update($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin' && isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if ($plugin === $this->plugin_basename) {
                    delete_transient($this->version_transient_name);
                    delete_transient($this->info_transient_name);
                    break;
                }
            }
        }
    }
    
    /**
     * Display custom update message
     */
    public function plugin_update_message() {
        echo '<br><strong>Note:</strong> This update will be downloaded from GitHub. Make sure you have a backup before updating.';
    }
    
    /**
     * Parse changelog from release body
     */
    private function parse_changelog($body) {
        if (empty($body)) {
            return 'No changelog available.';
        }
        
        // Convert markdown to basic HTML
        $changelog = wpautop($body);
        $changelog = str_replace(array('**', '__'), array('<strong>', '</strong>'), $changelog);
        $changelog = preg_replace('/\*\s(.+)/', '<li>$1</li>', $changelog);
        $changelog = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $changelog);
        
        return $changelog;
    }
    
    /**
     * Force update check
     */
    public function force_update_check() {
        delete_transient($this->version_transient_name);
        delete_transient($this->info_transient_name);
        delete_site_transient('update_plugins');
    }
    
    /**
     * Check if update is available
     */
    public function is_update_available() {
        $remote_version = $this->get_remote_version();
        if (!$remote_version) {
            return false;
        }
        
        return version_compare($this->plugin_version, $remote_version, '<');
    }
    
    /**
     * Get latest version info
     */
    public function get_latest_version_info() {
        $request = $this->make_github_api_request('/releases/latest');
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($request);
        return json_decode($body, true);
    }
}
