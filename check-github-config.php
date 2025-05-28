<?php
/**
 * GitHub Configuration Checker
 * 
 * Run this file directly to test your GitHub configuration.
 * Access via: yourdomain.com/wp-content/plugins/wpiko-chatbot-pro/check-github-config.php
 * 
 * @package WPiko_Chatbot_Pro
 */

// Basic WordPress environment
if (!defined('ABSPATH')) {
    // Load WordPress if not already loaded
    $wp_load_path = __DIR__ . '/../../../../wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die('WordPress not found. Please run this from within WordPress.');
    }
}

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. Administrator privileges required.');
}

// Include required files
require_once __DIR__ . '/includes/github-config.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>GitHub Configuration Checker - WPiko Chatbot Pro</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .test-result { margin: 20px 0; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .config-item { margin: 10px 0; }
        .config-label { font-weight: bold; width: 200px; display: inline-block; }
        .config-value { font-family: monospace; background: #f8f9fa; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🚀 GitHub Configuration Checker</h1>
    <p>This tool validates your GitHub update configuration for WPiko Chatbot Pro.</p>

    <?php
    
    // Test 1: Configuration File
    echo '<div class="test-result">';
    echo '<h2>📁 Configuration File</h2>';
    
    if (class_exists('WPiko_Chatbot_Pro_GitHub_Config')) {
        echo '<div class="status success">✅ GitHub configuration class loaded successfully</div>';
        
        $config = WPiko_Chatbot_Pro_GitHub_Config::get_config();
        
        echo '<h3>Current Configuration:</h3>';
        foreach ($config as $key => $value) {
            echo '<div class="config-item">';
            echo '<span class="config-label">' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</span> ';
            
            if ($key === 'github_token') {
                if (strpos($value, 'your-github-') === 0) {
                    echo '<span class="config-value" style="color: #dc3545;">❌ Not configured</span>';
                } else {
                    echo '<span class="config-value" style="color: #28a745;">✅ ' . esc_html(substr($value, 0, 8)) . '...</span>';
                }
            } else {
                if (strpos($value, 'your-') === 0) {
                    echo '<span class="config-value" style="color: #dc3545;">❌ ' . esc_html($value) . '</span>';
                } else {
                    echo '<span class="config-value" style="color: #28a745;">✅ ' . esc_html($value) . '</span>';
                }
            }
            echo '</div>';
        }
    } else {
        echo '<div class="status error">❌ GitHub configuration class not found</div>';
    }
    echo '</div>';
    
    // Test 2: Configuration Validation
    echo '<div class="test-result">';
    echo '<h2>🔍 Configuration Validation</h2>';
    
    if (class_exists('WPiko_Chatbot_Pro_GitHub_Config')) {
        $validation = WPiko_Chatbot_Pro_GitHub_Config::validate_config();
        
        if (is_wp_error($validation)) {
            echo '<div class="status error">❌ ' . esc_html($validation->get_error_message()) . '</div>';
            echo '<div class="status info">💡 Please update your configuration in <code>includes/github-config.php</code></div>';
        } else {
            echo '<div class="status success">✅ Configuration is valid</div>';
        }
    }
    echo '</div>';
    
    // Test 3: GitHub API Connection
    echo '<div class="test-result">';
    echo '<h2>🌐 GitHub API Connection</h2>';
    
    if (class_exists('WPiko_Chatbot_Pro_GitHub_Config')) {
        $connection_test = WPiko_Chatbot_Pro_GitHub_Config::test_connection();
        
        if (is_wp_error($connection_test)) {
            echo '<div class="status error">❌ ' . esc_html($connection_test->get_error_message()) . '</div>';
            
            // Provide specific troubleshooting based on error
            $error_code = $connection_test->get_error_code();
            switch ($error_code) {
                case 'invalid_token':
                    echo '<div class="status info">💡 Check your Personal Access Token:<br>';
                    echo '• Make sure it hasn\'t expired<br>';
                    echo '• Verify it has the correct permissions<br>';
                    echo '• Ensure it has access to your repository</div>';
                    break;
                case 'repo_not_found':
                    echo '<div class="status info">💡 Repository access issues:<br>';
                    echo '• Check your username and repository name<br>';
                    echo '• Verify the repository exists<br>';
                    echo '• Ensure your token has access to the repository</div>';
                    break;
                default:
                    echo '<div class="status info">💡 General troubleshooting:<br>';
                    echo '• Check your internet connection<br>';
                    echo '• Verify GitHub is accessible<br>';
                    echo '• Try again in a few minutes</div>';
            }
        } else {
            echo '<div class="status success">✅ Successfully connected to GitHub API</div>';
        }
    }
    echo '</div>';
    
    // Test 4: WordPress Integration
    echo '<div class="test-result">';
    echo '<h2>🔌 WordPress Integration</h2>';
    
    if (class_exists('WPiko_Chatbot_Pro_GitHub_Updater')) {
        echo '<div class="status success">✅ GitHub Updater class loaded</div>';
    } else {
        echo '<div class="status error">❌ GitHub Updater class not found</div>';
    }
    
    if (function_exists('wpiko_chatbot_pro_get_github_status')) {
        echo '<div class="status success">✅ Helper functions loaded</div>';
        
        $status = wpiko_chatbot_pro_get_github_status();
        if ($status['config_valid'] && $status['connection_valid']) {
            echo '<div class="status success">✅ Update system is fully operational</div>';
            
            if ($status['update_available']) {
                echo '<div class="status warning">⚠️ An update is available!</div>';
            } else {
                echo '<div class="status info">ℹ️ No updates available (plugin is current)</div>';
            }
        }
    } else {
        echo '<div class="status error">❌ Helper functions not loaded</div>';
    }
    echo '</div>';
    
    // Test 5: Plugin Information
    echo '<div class="test-result">';
    echo '<h2>📦 Plugin Information</h2>';
    
    if (defined('WPIKO_CHATBOT_PRO_VERSION')) {
        echo '<div class="config-item">';
        echo '<span class="config-label">Current Version:</span> ';
        echo '<span class="config-value">' . esc_html(WPIKO_CHATBOT_PRO_VERSION) . '</span>';
        echo '</div>';
    }
    
    if (defined('WPIKO_CHATBOT_PRO_BASENAME')) {
        echo '<div class="config-item">';
        echo '<span class="config-label">Plugin Basename:</span> ';
        echo '<span class="config-value">' . esc_html(WPIKO_CHATBOT_PRO_BASENAME) . '</span>';
        echo '</div>';
    }
    
    echo '</div>';
    
    // Setup Instructions
    echo '<div class="test-result">';
    echo '<h2>📋 Next Steps</h2>';
    
    $all_good = class_exists('WPiko_Chatbot_Pro_GitHub_Config') && 
                !is_wp_error(WPiko_Chatbot_Pro_GitHub_Config::validate_config()) &&
                !is_wp_error(WPiko_Chatbot_Pro_GitHub_Config::test_connection());
    
    if ($all_good) {
        echo '<div class="status success">🎉 Your GitHub update system is ready!</div>';
        echo '<div class="status info">';
        echo '<strong>To create a release:</strong><br>';
        echo '1. Update your plugin version<br>';
        echo '2. Run the release script: <code>./release.sh</code><br>';
        echo '3. Create a GitHub release with the new tag<br>';
        echo '4. WordPress sites will automatically detect the update';
        echo '</div>';
    } else {
        echo '<div class="status warning">⚠️ Configuration needs attention</div>';
        echo '<div class="status info">';
        echo '<strong>To fix issues:</strong><br>';
        echo '1. Review the errors above<br>';
        echo '2. Update <code>includes/github-config.php</code><br>';
        echo '3. Check the setup guide: <code>GITHUB-SETUP.md</code><br>';
        echo '4. Refresh this page to retest';
        echo '</div>';
    }
    
    echo '</div>';
    
    ?>
    
    <div class="test-result">
        <h2>🔄 Actions</h2>
        <p>
            <a href="?refresh=1" style="background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;">🔄 Refresh Tests</a>
            <a href="admin.php?page=ai-chatbot&tab=license_activation" style="background: #46b450; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin-left: 10px;">⚙️ View in Admin</a>
        </p>
    </div>
    
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px;">
        <p>WPiko Chatbot Pro - GitHub Update System | Last checked: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>
