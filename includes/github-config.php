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
            // GitHub repository owner (your username or organization)
            'github_user' => 'WPiko',
            
            // GitHub repository name
            'github_repo' => 'wpiko-chatbot-pro',
            
            // GitHub Personal Access Token
            // Generate at: https://github.com/settings/personal-access-tokens/new
            // Required permissions: Contents (Read) for private repos
            'github_token' => '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',
            
            // Repository type (public or private)
            'repo_type' => 'private',
            
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
        
        // Check required fields
        $required_fields = array('github_user', 'github_repo', 'github_token');
        foreach ($required_fields as $field) {
            if (empty($config[$field]) || $config[$field] === 'your-github-username' || $config[$field] === 'your-github-personal-access-token') {
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
        
        $args = array(
            'headers' => array(
                'Authorization' => 'token ' . $config['github_token'],
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
        } elseif ($response_code === 401) {
            return new WP_Error('invalid_token', 'GitHub token is invalid or expired.');
        } elseif ($response_code === 404) {
            return new WP_Error('repo_not_found', 'GitHub repository not found or access denied.');
        } else {
            return new WP_Error('api_error', "GitHub API returned status code: {$response_code}");
        }
    }
}

// Example of how to set up your configuration:
/*
1. Replace 'your-github-username' with your actual GitHub username
2. Replace 'your-github-personal-access-token' with your actual token
3. If your repository name is different, update 'github_repo'

To generate a GitHub Personal Access Token:
1. Go to https://github.com/settings/personal-access-tokens/new
2. Give it a descriptive name like "WPiko Chatbot Pro Updates"
3. Set expiration (recommended: 1 year)
4. For private repos, select: Repository access > Selected repositories > your-repo
5. Under "Repository permissions", set "Contents" to "Read"
6. Click "Generate token" and copy the token

Security Note: Never commit your actual token to version control.
Consider using environment variables or WordPress constants for production.
*/
