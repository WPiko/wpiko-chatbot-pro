<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<!-- Q&A Management - Section -->
<div id="qa-management-section">
    <h3>
        <span class="dashicons dashicons-insert"></span> 
        Q&A Builder 
        <?php if (!wpiko_chatbot_is_license_active()): ?>
            <span class="premium-feature-badge">Premium</span>
        <?php endif; ?>
    </h3>
    <div id="qa-management-content">
        <?php 
        // Get current license status and check for existing Q&A pairs
        $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
        $is_license_expired = $license_status === 'expired';
        
        // Check if there are any existing Q&A pairs
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpiko_chatbot_qa';
        $has_qa_pairs = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}wpiko_chatbot_qa`") > 0;
        
        // Check if there are any existing Q&A files
        $has_qa_files = false;
        $assistant_id = get_option('wpiko_chatbot_assistant_id', '');
        if ($assistant_id) {
            $files_result = wpiko_chatbot_list_assistant_files($assistant_id);
            if ($files_result['success']) {
                foreach ($files_result['files'] as $file) {
                    if (strpos($file['filename'], 'qa_data_') === 0) {
                        $has_qa_files = true;
                        break;
                    }
                }
            }
        }
        ?>

        <?php if (wpiko_chatbot_is_license_active()): ?>
            <p class="description">This section allows you to manually add, edit, or remove Q&A pairs, giving you fine-grained control over your chatbot's knowledge base. Use this feature to address specific topics, frequently asked questions, or to provide information that may not be present on your website. You can add up to 30 Q&A pairs.</p>
            <div id="qa-management-container">
                <div id="qa-management-container-buttons">
                    <button type="button" id="add-qa-button" class="button button-secondary">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        Add New Q&A
                    </button>
                    <button id="delete-all-qa-button" class="button button-secondary">
                        <span class="dashicons dashicons-trash"></span>
                        Delete All Q&A
                    </button>  
                    <button id="download-qa-button" class="button button-secondary download-files-option-button">
                        <span class="dashicons dashicons-download"></span>
                        Download Q&A File
                    </button>
                    <div id="qa-status-message"></div> 
                </div>      
                <div id="qa-list"></div>
                
                <h3>Q&A Files List</h3>
                <p class="description">View and manage your Q&A files uploaded to the Assistant API's knowledge base.</p>
                <ul id="qa-files-list"></ul>
                <button id="save-all-qa-button" class="button button-primary">Save All Q&A</button>
            </div>
            
        <?php elseif ($is_license_expired || !wpiko_chatbot_is_license_active()): ?>
            <div class="premium-feature-notice">
                <h3><?php echo $is_license_expired ? '🔒 Q&A Builder Disabled' : '🤖 Unlock Q&A Builder'; ?></h3>
                <p><?php echo $is_license_expired ? 'Your license has expired. Q&A Builder has been disabled.' : 'Upgrade to Premium to manually create and manage Q&A pairs:'; ?></p>
                <ul>
                    <li>✨ Create custom Q&A pairs manually</li>
                    <li>📝 Edit and update existing Q&A pairs</li>
                    <li>🗂️ Organize your chatbot's knowledge base</li>
                    <li>⚡ Add up to 30 custom Q&A pairs</li>
                    <li>📥 Download Q&A pairs for backup</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary"><?php echo $is_license_expired ? 'Renew License' : 'Upgrade to Premium'; ?></a>
            </div>
            
            <?php if ($has_qa_pairs || $has_qa_files): ?>
                <div class="qa-management-view-only">
                    <div class="notice notice-warning inline">
                        <p>Your premium license has expired. While you can view your existing Q&A pairs, you'll need to <a href="?page=ai-chatbot&tab=license_activation">renew your license</a> to add, edit, or manage them.</p>
                    </div>
                    <div id="qa-management-container">
                        <?php if ($has_qa_pairs): ?>
                        <div id="qa-management-container-buttons">
                            <button id="delete-all-qa-button" class="button button-secondary" disabled>Delete All Q&A</button>
                            <button id="download-qa-button" class="button button-secondary download-files-option-button">Download Q&A File</button>
                        </div>
                        <div id="qa-list-container-expired">
                            <style>
                                #qa-list-container-expired .delete-qa {
                                    display: none;
                                }
                            </style>
                            <div id="qa-list"></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($has_qa_files): ?>
                        <div class="qa-files-section">
                            <h3>Q&A Files List</h3>
                            <p class="description">View your Q&A files uploaded to the Assistant API's knowledge base.</p>
                            <ul id="qa-files-list"></ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>    
</div>
