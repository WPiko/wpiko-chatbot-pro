<?php
/**
 * GitHub Configuration for WPiko Chatbot Pro
 * 
 * Configuration settings for GitHub-based updates.
 * 
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * GitHub Configuration Class
 */
class WPiko_Chatbot_Pro_GitHub_Config {
    
    /**
     * Get GitHub configuration
     * 
     * @return array GitHub configuration settings
     */
    public static function get_config() {
        return array(
            // GitHub repository owner
            'github_user' => 'WPiko',
            
            // GitHub repository name
            'github_repo' => 'wpiko-chatbot-pro',
            
            // Repository type (public or private)
            'repo_type' => 'public',
            
            // Update check frequency (in hours)
            'check_frequency' => 12,
            
            // Plugin slug for WordPress
            'plugin_slug' => 'wpiko-chatbot-pro',
        );
    }
    
    /**
     * Validate GitHub configuration
     * 
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_config() {
        $config = self::get_config();
        
        // Check required fields (token not required for public repos)
        $required_fields = array('github_user', 'github_repo');
        foreach ($required_fields as $field) {
            if (empty($config[$field]) || $config[$field] === 'your-github-username') {
                return new WP_Error('missing_config', "GitHub configuration field '{$field}' is not properly set.");
            }
        }
        
        return true;
    }
    
    /**
     * Test GitHub API connection
     * 
     * @return bool|WP_Error True if connection successful, WP_Error on failure
     */
    public static function test_connection() {
        $config = self::get_config();
        
        $url = "https://api.github.com/repos/{$config['github_user']}/{$config['github_repo']}";
        
        // Setup request args - no authentication needed for public repos
        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WPiko-Chatbot-Pro-Updater'
            ),
            'timeout' => 15
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', 'Failed to connect to GitHub API: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return true;
        } elseif ($response_code === 404) {
            return new WP_Error('repo_not_found', 'GitHub repository not found. Please check the repository name and ensure it is public.');
        } else {
            return new WP_Error('api_error', "GitHub API returned status code: {$response_code}");
        }
    }
}
