<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Current license status.
$license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
$is_license_expired = $license_status === 'expired';
$is_license_active = wpiko_chatbot_is_license_active();

// Are there any scanned page files already?
$has_scanned_files = false;
$files_result = wpiko_chatbot_list_responses_files();
if ($files_result['success']) {
    foreach ($files_result['files'] as $file) {
        if (strpos($file['filename'], 'page_') === 0) {
            $has_scanned_files = true;
            break;
        }
    }
}

$enable_qa_download = get_option('wpiko_chatbot_enable_qa_download', '0');
$auto_sync = get_option('wpiko_chatbot_pro_auto_sync', '0');
?>

<!-- Scan Website content (loaded into the shared popup shell) -->
<div id="url-processing-section" class="wpiko-modal-stack">
    <div id="url-processing-content" class="wpiko-modal-stack">

    <?php if ($is_license_active): ?>
        <section class="wpiko-modal-section wpiko-scan-pages-section" aria-labelledby="wpiko-scan-title">
            <div class="wpiko-modal-section-header">
                <h3 id="wpiko-scan-title" class="wpiko-modal-section-title"><span id="wpiko-scan-title-text">Your pages</span> <span class="wpiko-count" id="wpiko-scan-total" hidden></span></h3>
            </div>
            <p class="description">Pages are rewritten into Q&amp;A by AI. Posts and docs are already written as articles, so they are added as clean Markdown with a short header, with no AI cost. Anything scanned here is removed from “Quick learn from your pages” so nothing is stored twice.</p>

            <div class="wpiko-scan-toolbar">
                <label class="wpiko-scan-select-all">
                    <input type="checkbox" id="wpiko-scan-select-all">
                    <span class="screen-reader-text">Select all pages shown</span>
                </label>
                <div class="wpiko-scan-type" hidden>
                    <label for="wpiko-scan-type" class="screen-reader-text">Content type</label>
                    <select id="wpiko-scan-type"></select>
                </div>
                <div class="wpiko-scan-search">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <label for="wpiko-scan-search" class="screen-reader-text">Filter by title</label>
                    <input type="search" id="wpiko-scan-search" placeholder="Filter by title" autocomplete="off">
                </div>
                <div class="wpiko-scan-filters" role="group" aria-label="Show">
                    <button type="button" class="wpiko-scan-filter is-active" data-filter="all" aria-pressed="true">All <span data-count="all"></span></button>
                    <button type="button" class="wpiko-scan-filter" data-filter="new" aria-pressed="false">Not scanned <span data-count="new"></span></button>
                    <button type="button" class="wpiko-scan-filter" data-filter="changed" aria-pressed="false">Changed <span data-count="changed"></span></button>
                    <button type="button" class="wpiko-scan-filter" data-filter="scanned" aria-pressed="false">Scanned <span data-count="scanned"></span></button>
                </div>
            </div>

            <ul id="wpiko-scan-pages" class="wpiko-file-list wpiko-scan-pages" aria-label="Pages">
                <li class="loading">Loading pages…</li>
            </ul>
            <p class="description wpiko-scan-truncated" hidden>Only the first 500 pages are listed.</p>
            <div id="url-processing-status" role="status" aria-live="polite"></div>
        </section>

        <section class="wpiko-modal-section wpiko-scan-orphans-section" aria-labelledby="wpiko-scan-orphans-title" hidden>
            <div class="wpiko-modal-section-header">
                <h3 id="wpiko-scan-orphans-title" class="wpiko-modal-section-title">Other scanned files <span class="wpiko-count" id="wpiko-scan-orphans-count"></span></h3>
            </div>
            <p class="description">Scanned files from content that was deleted or is no longer listed, or scanned before content was tracked. Remove the ones you no longer need.</p>
            <ul id="wpiko-scan-orphans" class="wpiko-file-list"></ul>
        </section>

        <div class="wpiko-modal-footer-slot">
            <div class="wpiko-scan-progress" hidden>
                <span class="wpiko-scan-progress-text"></span>
                <span class="wpiko-scan-progress-bar" aria-hidden="true"><span></span></span>
            </div>
            <button type="button" id="wpiko-scan-stop" class="button" hidden>Stop</button>
            <button type="button" id="process_url_button" class="button wpiko-chatbot-settings-save" disabled>Scan</button>
        </div>

    <?php elseif ($is_license_expired): ?>
        <div class="premium-feature-notice">
            <h3>Website Scanning Disabled</h3>
            <p>Your license has expired. Website scanning feature has been disabled.</p>
            <p>Get a lifetime license to regain access to these features:</p>
            <ul>
                <li>Scan your pages, posts and docs</li>
                <li>Turn pages into AI-written Q&amp;A</li>
                <li>Add posts and docs as clean Markdown, with no AI cost</li>
                <li>Train your chatbot with your website's content</li>
                <li>Save hours of manual Q&A creation</li>
                <li>Keep your chatbot's knowledge base up-to-date</li>
            </ul>
            <a href="https://wpiko.com/chatbot-pricing/" target="_blank" rel="noopener" class="button button-primary">Get Lifetime License</a>
        </div>

    <?php else: ?>
        <div class="premium-feature-notice">
            <h3>Unlock Website Scanning</h3>
            <p>Activate Pro to train your chatbot on your website content:</p>
            <ul>
                <li>Scan your pages, posts and docs</li>
                <li>Turn pages into AI-written Q&amp;A</li>
                <li>Add posts and docs as clean Markdown, with no AI cost</li>
                <li>Train your chatbot with your website's content</li>
                <li>Save hours of manual Q&A creation</li>
                <li>Keep your chatbot's knowledge base up-to-date</li>
            </ul>
            <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Activate Pro</a>
        </div>
    <?php endif; ?>

    <?php if (!$is_license_active && $has_scanned_files): // Read-only list after a license expires. ?>
        <section class="wpiko-modal-section url-processing-files-section" aria-labelledby="wpiko-scanned-title">
            <?php if (!$is_license_active): ?>
                <div class="notice notice-warning inline">
                    <p>Your premium license has expired. While you can view and manage existing scanned pages, you'll need to <a href="?page=ai-chatbot&tab=license_activation">get a lifetime license</a> to scan new pages.</p>
                </div>
            <?php endif; ?>
            <div class="wpiko-modal-section-header">
                <h3 id="wpiko-scanned-title" class="wpiko-modal-section-title">Scanned pages <span class="wpiko-count" id="url-processing-files-count" hidden></span></h3>
            </div>
            <ul id="url-processing-files-list" class="wpiko-file-list"></ul>
        </section>
    <?php endif; ?>

    <?php if ($is_license_active): ?>
        <section class="wpiko-modal-section download-files-option auto-sync-option" aria-label="Automatic updates">
            <label class="wpiko-chatbot-settings-switch" for="wpiko_scan_auto_sync">
                <input type="checkbox" name="wpiko_scan_auto_sync" id="wpiko_scan_auto_sync" value="1" <?php checked($auto_sync, '1'); ?>>
                <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                <span class="wpiko-chatbot-settings-switch__label">Keep scanned posts and docs up to date</span>
            </label>
            <p class="description">When you edit a post or doc that is already in the knowledge base, it is updated about a minute later, with no AI cost. Moving one to the trash removes it. Pages are not included, because their Q&amp;A is written by AI and uses credits, rescan them here. <span id="wpiko_scan_auto_sync_status" class="wpiko-inline-status" role="status" aria-live="polite"></span></p>
        </section>

        <section class="wpiko-modal-section download-files-option" aria-label="Download option">
            <label class="wpiko-chatbot-settings-switch" for="enable_qa_download">
                <input type="checkbox" name="enable_qa_download" id="enable_qa_download" value="1" <?php checked($enable_qa_download, '1'); ?>>
                <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                <span class="wpiko-chatbot-settings-switch__label">Download a copy of each scanned file</span>
            </label>
            <p class="description">When on, your browser also saves each file (Q&amp;A for pages, Markdown for posts and docs) so you can review it. <span id="qa_download_status" class="wpiko-inline-status" role="status" aria-live="polite"></span></p>
        </section>
    <?php endif; ?>

    </div>
</div>
