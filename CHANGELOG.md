# Changelog

## 2.1.3

* Fix: Live chat replies sent while the visitor was away now appear when they come back: after moving to another page, or after closing the browser when the site remembers conversations across visits. Each reply is shown once, even with several tabs open.
* Improve: Live chat takeover goes back to the AI on its own when nobody from the team has replied to or opened the conversation for 12 hours. The visitor's next visit or message hands it back, with the usual "left the conversation. AI assistant is back." notice, so a visitor returning to a remembered conversation is never left without answers. Developers can change the time with the `wpiko_chatbot_pro_takeover_auto_release_hours` filter (0 = never) and react with the `wpiko_chatbot_pro_takeover_auto_released` action.
* Compatibility: Live chat takeover and the contact form use the conversation where WPiko AI Chatbot 2.1.3 keeps it, so they keep working when the site remembers conversations across visits. Contact form results stay in the remembered conversation. Older versions of WPiko AI Chatbot work as before.

## 2.1.2

* Improve: The plugin is now called "WPiko AI Chatbot Pro" everywhere (plugin name, description, menus, notices and messages), matching the free plugin's WordPress.org listing.
* Compatibility: Requires WPiko AI Chatbot 2.1.2 or newer for the shared admin styles. Update both plugins together.
* Improve: Redesigned Email Capture, Contact Form, Product Card, Analytics, Mobile App, License Activation and training dialogs using the base plugin's shared CSS and settings controls.
* Improve: Standardized inactive-license feature cards and preserved the Analytics date range, summary metrics and upgrade-card order.
* Improve: Updated Analytics metric icons and active chart controls to match the new admin styling.
* Developer: Removed unused legacy admin CSS and consolidated common styles without changing feature behavior.
* Feature: Scan Website now lists all your content with its scan state (Not scanned, Up to date, Changed since scan). Tick several items and scan all of them in one run, with progress for each item, a Stop button and a summary at the end. Items that fail stay selected so you can retry them.
* Feature: Scan Website also reads posts and articles from documentation and knowledge-base plugins, with no plugin-specific code. Every public content type with an editor is listed, grouped under the plugin's own name and icon, with a type selector, title filter and status filters. WooCommerce products, media files and page-builder templates are left out, and so are WooCommerce's Cart, Checkout, My account and Shop pages, which have no text to learn from (products stay in sync through the WooCommerce integration).
* Feature: Scan Website handles each content type the way that suits it. Pages are still rewritten into Q&A by AI. Posts, docs and other article types are added as clean Markdown with a short header (title, link, type, publish and update dates, categories and tags), with no AI request: they are free and instant to scan, and steps, code samples, tables and links keep their exact wording. Blog posts note their publish date so answers can mention it. Items scanned the other way before show "Rescan needed".
* Feature: New "Keep scanned posts and docs up to date" option in Scan Website (off by default). When you edit a post or doc that is already in the knowledge base, it is updated about a minute later with no AI cost, and moving it to the trash removes it. Pages are not included because their Q&A uses AI credits. Updates run in the background in small batches, so saving stays fast and bulk edits work on shared hosting; saves that do not change the text are not uploaded again.
* Feature: Each scanned item can be rescanned, viewed or removed from the knowledge base directly in the list. Scanned files whose content no longer exists are listed under "Other scanned files" so they can be deleted.
* Improve: Private and password-protected items have an amber tag in Scan Website, with a note that once scanned the chatbot can share their content with any visitor.
* Improve: Scanning 5 or more pages asks for confirmation first, since each page uses OpenAI credits. Posts and docs never need confirmation. Closing the window during a run asks before stopping.
* Fix: Two pages with the same title no longer overwrite each other's Q&A file, and rescanning a renamed page removes its previous file.
* Fix: Scan Website no longer breaks links in generated Q&A. Percent-encoded links (for example pages with Greek or other non-Latin addresses) and text in angle brackets were stripped on upload; the Q&A is now kept on the server between generating and uploading, so it is added exactly as written.
* Improve: Q&A Builder now allows up to 50 Q&A pairs (was 30). The limit is also enforced on the server.
* Improve: Q&A Builder has a filter box once there are 10 or more pairs, a pair counter and labelled Question and Answer fields. New pairs open ready to type, and saving checks that every pair has both a question and an answer.
* Fix: Answers saved in the Q&A Builder keep their line breaks.
* Fix: Q&A Builder no longer breaks links in questions and answers. Percent-encoded links (for example Greek or other non-Latin page addresses) and text in angle brackets, such as `<br>` in a how-to answer, are now saved exactly as typed.
* Fix: Q&A Builder no longer shows "No Q&A pairs found" after a pair is added, and removing every pair and saving no longer reports an error.
* Improve: WooCommerce Integration is organised into Product catalog and Order assistance sections. Product and order field choices save as soon as they change, and the order lookup notes are easier to read.
* Improve: The Scan Website download option saves as soon as it changes.
* Improve: Pro features (including the Mobile app) and the license status appear as On/Off chips in the redesigned Dashboard Configuration status (requires WPiko AI Chatbot 2.1.2).
* Developer: Removed unused code: retired order-file sync helpers and AJAX endpoints, the old Scan Website page search, unused Q&A Builder and takeover AJAX endpoints (takeover uses the REST API), the old email capture menu option, unused GitHub status helpers and the pre-WordPress 5.5 `whitelist_options` filter.
* Improve: Removed debug messages from the browser console on the chatbot, contact form and admin screens.
* Developer: New `wpiko_chatbot_pro_scan_mode` filter (return `qa` or `markdown` for an item), `wpiko_chatbot_pro_markdown_header` filter for the Markdown header lines, `wpiko_chatbot_pro_scan_excluded_ids` filter for the items Scan Website hides, and `wpiko_chatbot_pro_auto_sync_delay` / `wpiko_chatbot_pro_auto_sync_batch` filters for automatic updates.
* Developer: New `wpiko_chatbot_pro_scan_post_types`, `wpiko_chatbot_pro_scan_pages_limit` and `wpiko_chatbot_pro_qa_max_pairs` filters, and a `wpiko_chatbot_get_scan_pages` AJAX action.
* Fix: Other add-ons can now add their own screens to the WPiko AI Chatbot admin. Pro passed `false` through the `wpiko_chatbot_admin_tab_content` filter instead of the tab ID, so a screen registered after Pro was replaced by the Dashboard, and a screen registered before Pro showed the Dashboard below it. Unknown tabs still fall back to the Dashboard.

## 2.1.1

* Licensing: WPiko Chatbot Pro licenses are now lifetime. When the license server converts a license to lifetime, Pro switches back to Active right away, including on sites that had expired.
* Improve: Refresh License and the daily license check now ask the license server for the current license state, so a site that missed an update catches up by itself.
* Fix: The daily license check was not scheduled for some dated licenses activated from the dashboard.
* Update: Expired-license messages now point to lifetime licenses instead of renewals.

## 2.1.0

* Compatibility: Requires WPiko Chatbot 2.1.0 or newer. Update both plugins together.
* Security: Updated contact-form buttons to use delegated event listeners and work with the base plugin's safer message rendering. Thanks to Ali Hidayat for responsibly reporting the stored XSS vulnerability in assistant messages.
* Security: Added signature verification for license-server requests that revoke licenses or change their expiration dates.
* Feature: Added controlled WooCommerce order lookup with rate limits. Guests must provide an order number and matching checkout email to receive basic status; signed-in order owners can receive the additional fields enabled by the store.
* Privacy: Replaced order-file synchronization with controlled order lookup and added background cleanup of old order exports from OpenAI.
* Improve: Track pages covered by Pro website scans and update coverage when knowledge files are removed, so the base plugin's page-learning tools can recognize existing training.
* Performance: Load Pro frontend scripts and styles only on pages where the chatbot is displayed.

## 2.0.8

* Feature: Added GPT-6 Sol and GPT-6 Luna to AI Configuration and the OpenAI Responses API model list.
* Improve: Added their supported reasoning efforts, file search support, and model details including context size and token pricing.
* Update: Switched conversation translation and contact text enhancement to GPT-6 Luna, and website Q&A generation to GPT-6 Sol.
* Improve: Added Expand all and Collapse all controls to the admin sidebar, allowed multiple navigation groups to stay open, and remembered open groups between pages.
