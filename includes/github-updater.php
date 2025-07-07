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
        // Validate input parameters
        if (empty($plugin_file) || empty($plugin_slug) || empty($plugin_version)) {
            return;
        }
        
        // Load configuration
        $this->config = WPiko_Chatbot_Pro_GitHub_Config::get_config();
        if (!is_array($this->config)) {
            return;
        }
        
        $this->github_user = isset($this->config['github_user']) ? $this->config['github_user'] : '';
        $this->github_repo = isset($this->config['github_repo']) ? $this->config['github_repo'] : '';
        
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
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Only add hooks in admin context and for appropriate screens
        if (!is_admin()) {
            return;
        }
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_api_call'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection'), 10, 3);
        add_action('upgrader_process_complete', array($this, 'after_update'), 10, 2);
        add_filter('upgrader_pre_download', array($this, 'upgrader_pre_download'), 10, 3);
        
        // Add custom update message
        add_action("in_plugin_update_message-{$this->plugin_basename}", array($this, 'plugin_update_message'));
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_update($transient) {
        // Early return if transient is not properly formed
        if (empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }
        
        // Only check if our plugin is in the checked list
        if (!isset($transient->checked[$this->plugin_basename])) {
            return $transient;
        }
        
        // Don't run update checks on the "Add New" plugin page to avoid interference
        global $pagenow;
        if ($pagenow === 'plugin-install.php') {
            return $transient;
        }
        
        // Check if we just updated (prevent immediate re-check after update)
        $just_updated = get_transient($this->plugin_basename . '_just_updated');
        if ($just_updated !== false) {
            // If less than 2 minutes since update, skip check
            if ((time() - $just_updated) < (2 * MINUTE_IN_SECONDS)) {
                return $transient;
            }
        }
        
        // Get the current plugin version from the file system (more reliable after updates)
        $current_version = $this->get_current_plugin_version();
        
        // Get remote version
        $remote_version = $this->get_remote_version();
        if (!$remote_version) {
            return $transient;
        }
        
        // Compare versions
        if (version_compare($current_version, $remote_version, '<')) {
            $update_info = new stdClass();
            $update_info->slug = $this->plugin_slug;
            $update_info->plugin = $this->plugin_basename;
            $update_info->new_version = $remote_version;
            $update_info->url = $this->get_github_repo_url();
            $update_info->package = $this->get_download_url($remote_version);
            
            // Ensure the response array exists
            if (!isset($transient->response) || !is_array($transient->response)) {
                $transient->response = array();
            }
            
            $transient->response[$this->plugin_basename] = $update_info;
        }
        
        return $transient;
    }
    
    /**
     * Get current plugin version from file system (more reliable after updates)
     */
    private function get_current_plugin_version() {
        // First try to get from WordPress plugin data
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $plugin_data = get_plugin_data($this->plugin_file, false, false);
        if (!empty($plugin_data['Version'])) {
            return $plugin_data['Version'];
        }
        
        // Fallback to the version passed in constructor
        return $this->plugin_version;
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
        $response_code = wp_remote_retrieve_response_code($request);
        
        if (is_wp_error($request)) {
            return false;
        }
        
        if ($response_code === 404) {
            // No releases found - this is not an error, just no updates available
            // Cache "no updates" for a shorter period
            set_transient($this->version_transient_name, $this->plugin_version, 1 * HOUR_IN_SECONDS);
            return $this->plugin_version; // Return current version to indicate no updates
        }
        
        if ($response_code !== 200) {
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
        // Only handle our specific plugin requests and plugin_information action
        if ($action !== 'plugin_information' || 
            !is_object($response) ||
            !isset($response->slug) || 
            $response->slug !== $this->plugin_slug) {
            return $false;
        }
        
        // Additional safety check - only proceed if this looks like a legitimate request for our plugin
        if (!current_user_can('manage_options')) {
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
        $info->plugin = $this->plugin_basename;
        $info->version = ltrim($data['tag_name'], 'v');
        $info->author = 'WPiko';
        $info->homepage = $this->get_github_repo_url();
        $info->download_link = $this->get_download_url($info->version);
        $info->package = $this->get_download_url($info->version);
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
     * Make GitHub API request (public repository, no authentication required)
     */
    private function make_github_api_request($endpoint) {
        $url = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}{$endpoint}";
        
        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WPiko-Chatbot-Pro-Updater'
            ),
            'timeout' => 30
        );
        
        return wp_remote_get($url, $args);
    }
    
    /**
     * Get download URL for specific version (public repository)
     */
    private function get_download_url($version) {
        // Return the direct GitHub download URL for public repositories
        return "https://github.com/{$this->github_user}/{$this->github_repo}/archive/refs/tags/v{$version}.zip";
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
        // Check if this is our plugin being updated
        if (!isset($upgrader->skin->plugin_info)) {
            return $source;
        }
        
        // Check by plugin basename or plugin name
        $is_our_plugin = false;
        
        // Method 1: Check by plugin basename
        if (isset($upgrader->skin->plugin) && $upgrader->skin->plugin === $this->plugin_basename) {
            $is_our_plugin = true;
        }
        
        // Method 2: Check by plugin name
        if (!$is_our_plugin && isset($upgrader->skin->plugin_info['Name']) && 
            $upgrader->skin->plugin_info['Name'] === 'WPiko Chatbot Pro') {
            $is_our_plugin = true;
        }
        
        // Method 3: Check if the source contains GitHub repo name
        if (!$is_our_plugin && strpos($source, $this->github_repo) !== false) {
            $is_our_plugin = true;
        }
        
        if (!$is_our_plugin) {
            return $source;
        }
        
        $corrected_source = trailingslashit($remote_source) . $this->plugin_slug . '/';
        
        // Initialize WordPress filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }
        
        if ($wp_filesystem->move($source, $corrected_source)) {
            return $corrected_source;
        } else {
            // Log only if move fails
            if (function_exists('wpiko_chatbot_log')) {
                wpiko_chatbot_log("Failed to rename plugin folder during update", 'error');
            }
        }
        
        return $source;
    }
    
    /**
     * Handle the download with authentication for GitHub releases
     */
    public function upgrader_pre_download($reply, $package, $upgrader) {
        // Only handle our plugin's downloads (updated for public repository URLs)
        if (strpos($package, "github.com/{$this->github_user}/{$this->github_repo}/archive/") === false) {
            return $reply;
        }
        
        // Log download attempt
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log("Attempting to download package: " . $package, 'info');
        }
        
        // Set up request arguments (no authentication needed for public repositories)
        $args = array(
            'headers' => array(
                'User-Agent' => 'WPiko-Chatbot-Pro-Updater'
            ),
            'timeout' => 300 // 5 minutes for download
        );
        
        // Download the file
        $response = wp_remote_get($package, $args);
        
        if (is_wp_error($response)) {
            if (function_exists('wpiko_chatbot_log')) {
                wpiko_chatbot_log("Download error: " . $response->get_error_message(), 'error');
            }
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_msg = 'Download failed with response code: ' . $response_code;
            if (function_exists('wpiko_chatbot_log')) {
                wpiko_chatbot_log($error_msg, 'error');
            }
            return new WP_Error('download_failed', $error_msg);
        }
        
        // Create temporary file
        $temp_file = wp_tempnam($package);
        if (!$temp_file) {
            return new WP_Error('temp_file_failed', 'Could not create temporary file');
        }
        
        // Write the downloaded content to the temporary file
        $file_contents = wp_remote_retrieve_body($response);
        if (file_put_contents($temp_file, $file_contents) === false) {
            wp_delete_file($temp_file);
            return new WP_Error('write_failed', 'Could not write to temporary file');
        }
        
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log("Successfully downloaded to: " . $temp_file, 'info');
        }
        
        return $temp_file;
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
                    
                    // Set a temporary "just updated" flag to prevent immediate re-checking
                    set_transient($this->plugin_basename . '_just_updated', time(), 5 * MINUTE_IN_SECONDS);
                    
                    // Log the update completion
                    if (function_exists('wpiko_chatbot_log')) {
                        wpiko_chatbot_log("Plugin update completed, set temporary update flag", 'info');
                    }
                    
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
    /**
     * Force check for updates by clearing cache
     */
    public function force_update_check() {
        // Clear our plugin-specific transients
        delete_transient($this->version_transient_name);
        delete_transient($this->info_transient_name);
        
        // Clear the "just updated" flag if user manually requests update check
        delete_transient($this->plugin_basename . '_just_updated');
        
        // Clear WordPress update transients
        delete_site_transient('update_plugins');
        delete_transient('plugins_api');
        
        // Clear any potential object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Force WordPress to check for updates on next page load
        wp_clean_plugins_cache();
        
        // Test the connection immediately to provide better feedback
        $test_connection = WPiko_Chatbot_Pro_GitHub_Config::test_connection();
        if (is_wp_error($test_connection)) {
            return false;
        }
        
        // Try to get the remote version to verify everything is working
        $remote_version = $this->get_remote_version();
        if ($remote_version === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if update is available
     */
    public function is_update_available() {
        $remote_version = $this->get_remote_version();
        if (!$remote_version) {
            return false;
        }
        
        $current_version = $this->get_current_plugin_version();
        return version_compare($current_version, $remote_version, '<');
    }
    
    /**
     * Get latest version info
     */
    public function get_latest_version_info() {
        $request = $this->make_github_api_request('/releases/latest');
        $response_code = wp_remote_retrieve_response_code($request);
        
        if (is_wp_error($request)) {
            return false;
        }
        
        if ($response_code === 404) {
            // No releases found - return current plugin info
            return array(
                'tag_name' => 'v' . $this->plugin_version,
                'name' => 'Current Version',
                'body' => 'No releases have been published yet.',
                'published_at' => gmdate('c'),
                'html_url' => $this->get_github_repo_url()
            );
        }
        
        if ($response_code !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($request);
        return json_decode($body, true);
    }
}
