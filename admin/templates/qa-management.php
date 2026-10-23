<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<!-- Q&A Builder content (loaded into the shared popup shell) -->
<div id="qa-management-section">
    <div id="qa-management-content" class="wpiko-modal-stack">
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
        $api_display_name = 'Responses API';
        
        // Check for Responses API files
        $responses_vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');
        if ($responses_vector_store_id && function_exists('wpiko_chatbot_list_responses_files')) {
            $files_result = wpiko_chatbot_list_responses_files();
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

        <?php $qa_max_pairs = function_exists('wpiko_chatbot_pro_qa_max_pairs') ? wpiko_chatbot_pro_qa_max_pairs() : 50; ?>
        <?php if (wpiko_chatbot_is_license_active()): ?>
            <div id="qa-management-container" class="wpiko-modal-stack" data-max="<?php echo esc_attr($qa_max_pairs); ?>">
                <section class="wpiko-modal-section" aria-labelledby="wpiko-qa-pairs-title">
                    <div class="wpiko-modal-section-header">
                        <h3 id="wpiko-qa-pairs-title" class="wpiko-modal-section-title">Your Q&amp;A <span class="wpiko-count" id="qa-count-label">0 / <?php echo esc_html($qa_max_pairs); ?></span></h3>
                        <div id="qa-management-container-buttons" class="wpiko-modal-toolbar">
                            <button type="button" id="download-qa-button" class="button download-files-option-button">
                                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                                Download
                            </button>
                            <button type="button" id="delete-all-qa-button" class="button wpiko-chatbot-settings-danger">
                                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                Delete all
                            </button>
                            <button type="button" id="add-qa-button" class="button wpiko-chatbot-settings-save">
                                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                Add Q&amp;A
                            </button>
                        </div>
                    </div>
                    <p class="description">Add topics, frequent questions or facts that are not on your website. Your assistant uses these answers word for word where they fit.</p>
                    <div id="qa-status-message"></div>
                    <div class="qa-filter" hidden>
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <label for="qa-filter-input" class="screen-reader-text">Filter Q&amp;A</label>
                        <input type="search" id="qa-filter-input" placeholder="Filter questions and answers" autocomplete="off">
                    </div>
                    <div id="qa-list" aria-live="polite"></div>
                    <p class="qa-filter-empty" hidden>No Q&amp;A match your filter.</p>
                </section>

                <section class="wpiko-modal-section qa-file-section" aria-labelledby="wpiko-qa-file-title">
                    <div class="wpiko-modal-section-header">
                        <h3 id="wpiko-qa-file-title" class="wpiko-modal-section-title">Knowledge base file</h3>
                        <span class="stat-value wpiko-badge" id="qa-file-status">…</span>
                    </div>
                    <div id="qa-file-status-container">
                        <dl id="qa-file-info" class="qa-file-info-box qa-file-stats">
                            <div class="stat-item">
                                <dt class="stat-label">Q&amp;A pairs saved</dt>
                                <dd class="stat-value" id="qa-pairs-count">–</dd>
                            </div>
                            <div class="stat-item">
                                <dt class="stat-label">Last updated</dt>
                                <dd class="stat-value" id="qa-file-updated">–</dd>
                            </div>
                        </dl>
                    </div>
                    <p class="description">Saving builds one file from all your pairs and sends it to your assistant's knowledge base.</p>
                    <ul id="qa-files-list" class="wpiko-file-list"></ul>
                </section>

                <div class="wpiko-modal-footer-slot">
                    <span id="wpiko-qa-save-status" class="wpiko-modal-status" role="status" aria-live="polite"></span>
                    <button type="button" id="save-all-qa-button" class="button wpiko-chatbot-settings-save" disabled>Save changes</button>
                </div>
            </div>

        <?php elseif ($is_license_expired || !wpiko_chatbot_is_license_active()): ?>
            <div class="premium-feature-notice">
                <h3><?php echo $is_license_expired ? 'Q&A Builder Disabled' : 'Unlock Q&A Builder'; ?></h3>
                <p><?php echo $is_license_expired ? 'Your license has expired. Q&A Builder has been disabled.' : 'Activate Pro to manually create and manage Q&A pairs:'; ?></p>
                <ul>
                    <li>Create custom Q&A pairs manually</li>
                    <li>Edit and update existing Q&A pairs</li>
                    <li>Organize your chatbot's knowledge base</li>
                    <li>Add up to <?php echo esc_html($qa_max_pairs); ?> custom Q&A pairs</li>
                    <li>Download Q&A pairs for backup</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary"><?php echo $is_license_expired ? 'Get Lifetime License' : 'Activate Pro'; ?></a>
            </div>
            
            <?php if ($has_qa_pairs || $has_qa_files): ?>
                <div class="qa-management-view-only wpiko-modal-section">
                    <div class="notice notice-warning inline">
                        <p>Your premium license has expired. While you can view your existing Q&A pairs, you'll need to <a href="?page=ai-chatbot&tab=license_activation">get a lifetime license</a> to add, edit, or manage them.</p>
                    </div>
                    <div id="qa-management-container">
                        <?php if ($has_qa_pairs): ?>
                        <div id="qa-management-container-buttons" class="wpiko-modal-toolbar">
                            <button type="button" id="delete-all-qa-button" class="button" disabled>Delete All Q&A</button>
                            <button type="button" id="download-qa-button" class="button download-files-option-button">Download Q&A File</button>
                        </div>
                        <div id="qa-list-container-expired">
                            <div id="qa-list"></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($has_qa_files): ?>
                        <div class="qa-files-section">
                            <h3>
                                Q&A Knowledge Base Files
                            </h3>
                            <p class="description">View your existing Q&A knowledge base files uploaded to the AI Assistant.</p>
                            <ul id="qa-files-list" class="wpiko-file-list"></ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>    
</div>
