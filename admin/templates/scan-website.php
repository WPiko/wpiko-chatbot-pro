<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<!-- Scan Website Content - Section -->
<div id="url-processing-section">
    <h3>
        <span class="dashicons dashicons-admin-site-alt3"></span> 
        Scan Website 
        <?php if (!wpiko_chatbot_is_license_active()): ?>
            <span class="premium-feature-badge">Premium</span>
        <?php endif; ?>
    </h3>
    <div id="url-processing-content">
    
    <?php 
    // Get current license status
    $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
    $is_license_expired = $license_status === 'expired';
    
    // Get the assistant ID from options
    $assistant_id = get_option('wpiko_chatbot_assistant_id', '');
    
    // Check if there are any existing scanned page files
    $has_scanned_files = false;
    if ($assistant_id) {
        $files_result = wpiko_chatbot_list_assistant_files($assistant_id);
        if ($files_result['success']) {
            foreach ($files_result['files'] as $file) {
                if (strpos($file['filename'], 'page_') === 0) {
                    $has_scanned_files = true;
                    break;
                }
            }
        }
    }
    ?>
    
    <?php
    // Get current option value
    $enable_qa_download = get_option('wpiko_chatbot_enable_qa_download', '0');
    ?>

    <?php if (wpiko_chatbot_is_license_active()): ?>
        <p class="description">Scan your website content to generate questions and answers for your AI assistant's knowledge base.</p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="process_url_search">Search Page</label></th>
                <td>
                    <input type="text" id="process_url_search" name="process_url_search" class="regular-text" placeholder="Enter at least 3 letters to search">
                    <div id="page-search-results"></div>
                    <button type="button" id="process_url_button" class="button button-secondary" disabled>
                        <span class="dashicons dashicons-plus-alt2"></span>
                        Generate Q&A
                    </button>
                    <p class="description">Enter at least 3 letters to search for a page, then select it to generate Q&A content. Ensure the page is live and not labeled as 'under maintenance' or 'coming soon.'</p>
               </td>
            </tr>
            
            <tr valign="top" class="download-files-option">
                <th scope="row"><label for="enable_qa_download">Enable Q&A Download</label></th>
                <td>
                   <input type="checkbox" name="enable_qa_download" id="enable_qa_download" value="1" <?php checked($enable_qa_download, '1'); ?>>
                    <button type="button" id="save_qa_download_setting" class="button button-secondary">Save Setting</button>
                   <p class="description">If enabled, users can download the generated Q&A content for preview purposes.</p>
                </td>
            </tr>
        </table>
    <?php elseif ($is_license_expired): ?>
        <div class="premium-feature-notice">
            <h3>🔒 Website Scanning Disabled</h3>
            <p>Your license has expired. Website scanning feature has been disabled.</p>
            <p>Renew your license to regain access to these features:</p>
            <ul>
                <li>✨ Scan any page on your website</li>
                <li>📝 Automatically generate relevant Q&A pairs</li>
                <li>🤖 Train your chatbot with your website's content</li>
                <li>⚡ Save hours of manual Q&A creation</li>
                <li>📈 Keep your chatbot's knowledge base up-to-date</li>
            </ul>
            <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Renew License</a>
        </div>
        
    <?php else: ?>
        <div class="premium-feature-notice">
            <h3>🔍 Unlock Website Scanning</h3>
            <p>Upgrade to Premium to automatically generate Q&A pairs from your website content:</p>
            <ul>
                <li>✨ Scan any page on your website</li>
                <li>📝 Automatically generate relevant Q&A pairs</li>
                <li>🤖 Train your chatbot with your website's content</li>
                <li>⚡ Save hours of manual Q&A creation</li>
                <li>📈 Keep your chatbot's knowledge base up-to-date</li>
            </ul>
            <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Upgrade to Premium</a>
        </div>
    <?php endif; ?>

    <div id="url-processing-status"></div>
    
    <?php if (wpiko_chatbot_is_license_active() || $has_scanned_files): ?>
        <div class="url-processing-files-section">
            <?php if (!wpiko_chatbot_is_license_active()): ?>
                <div class="notice notice-warning inline">
                    <p>Your premium license has expired. While you can view and manage existing scanned pages, you'll need to <a href="?page=ai-chatbot&tab=license_activation">renew your license</a> to scan new pages.</p>
                </div>
            <?php endif; ?>
            <h3>Website Pages List</h3>
            <p class="description">View and manage the website pages you've uploaded to the Assistant API's knowledge base.</p>
            <ul id="url-processing-files-list"></ul>
        </div>    
    <?php endif; ?>
    </div>    
</div>
