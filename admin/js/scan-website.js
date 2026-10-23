/**
 * Scan Website popup: pick content, scan several items at once, rescan
 * items that changed, and remove scanned files.
 *
 * Pages are rewritten into Q&A by OpenAI. Docs, posts and other article
 * types are added as clean Markdown with no AI request (page.mode).
 *
 * Server endpoints (wpiko-chatbot-pro/includes/scan-website.php):
 *   wpiko_chatbot_get_scan_pages          pages + scan state, orphan files
 *   wpiko_chatbot_process_page            one item -> Q&A text (OpenAI) or Markdown
 *   wpiko_chatbot_upload_qa_to_assistant  file -> knowledge base (Markdown is rebuilt on the server)
 *   wpiko_chatbot_delete_file             (core) remove a knowledge file
 */
jQuery(document).ready(function($) {
    'use strict';

    // Ask before starting a run with this many AI (Q&A) items or more.
    var BULK_CONFIRM_AT = 5;

    var MODE_LABELS = { qa: 'AI Q&A', markdown: 'Markdown' };

    function ajax(action, data, options) {
        return $.ajax($.extend({
            url: wpikoChatbotAdmin.ajax_url,
            type: 'POST',
            data: $.extend({ action: action, security: wpikoChatbotAdmin.nonce }, data || {})
        }, options || {}));
    }

    function relativeTime(timestamp) {
        if (!timestamp || timestamp < 0) {
            return '';
        }
        var seconds = Date.now() / 1000 - timestamp;
        if (seconds < 60) {
            return 'just now';
        }
        if (seconds < 3600) {
            return Math.floor(seconds / 60) + ' min ago';
        }
        if (seconds < 86400) {
            var hours = Math.floor(seconds / 3600);
            return hours + (hours === 1 ? ' hour ago' : ' hours ago');
        }
        if (seconds < 86400 * 30) {
            var days = Math.floor(seconds / 86400);
            return days + (days === 1 ? ' day ago' : ' days ago');
        }
        return new Date(timestamp * 1000).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function confirmDialog(options) {
        if (window.wpikoModal && typeof window.wpikoModal.confirm === 'function') {
            return window.wpikoModal.confirm(options);
        }
        return $.Deferred().resolve(window.confirm(options.title)).promise();
    }

    function errorMessage(response, fallback) {
        return response && response.data && response.data.message ? response.data.message : fallback;
    }

    function initializeContentProcessor() {
        var $section = $('#url-processing-section');
        var $list = $('#wpiko-scan-pages');
        initializeDownloadSetting();
        initializeAutoSyncSetting();
        if (!$list.length) {
            return; // Read-only (expired licence) view, or content not loaded.
        }

        var $modal = $section.closest('.wpiko-modal');
        var $generate = $('#process_url_button');
        var $stop = $('#wpiko-scan-stop');
        var $progress = $('.wpiko-scan-progress');
        var $status = $('#url-processing-status');
        var $selectAll = $('#wpiko-scan-select-all');
        var $search = $('#wpiko-scan-search');
        var $type = $('#wpiko-scan-type');

        var state = {
            pages: [],
            byId: {},
            orphans: [],
            types: [],      // [{name, label, singular, count}] — only types with content
            typeInfo: {},
            type: 'all',
            filter: 'all',
            search: '',
            selected: {},
            results: {},   // page ID -> {status: queued|running|done|failed, message}
            running: false,
            stop: false
        };

        /* ---------------- Data ---------------- */

        function category(page) {
            if (!page.scanned) {
                return 'new';
            }
            return page.changed ? 'changed' : 'scanned';
        }

        function isMultiType() {
            return state.types.length > 1;
        }

        // "page"/"pages" while the site only has pages, "item"/"items" once docs or posts are listed.
        function noun(count) {
            if (isMultiType()) {
                return count === 1 ? 'item' : 'items';
            }
            return count === 1 ? 'page' : 'pages';
        }

        function inCurrentType(page) {
            return state.type === 'all' || page.type === state.type;
        }

        function isVisible(page) {
            if (!inCurrentType(page)) {
                return false;
            }
            var cat = category(page);
            if (state.filter === 'new' && cat !== 'new') {
                return false;
            }
            if (state.filter === 'changed' && cat !== 'changed') {
                return false;
            }
            if (state.filter === 'scanned' && cat === 'new') {
                return false;
            }
            if (state.search && page.title.toLowerCase().indexOf(state.search) === -1) {
                return false;
            }
            return true;
        }

        function visiblePages() {
            return state.pages.filter(isVisible);
        }

        function selectedIds() {
            // Keep the order shown in the list.
            return state.pages
                .filter(function(page) { return state.selected[page.id]; })
                .map(function(page) { return page.id; });
        }

        function loadPages() {
            return ajax('wpiko_chatbot_get_scan_pages').done(function(response) {
                if (!response || !response.success) {
                    $list.empty().append($('<li class="error"></li>').text(errorMessage(response, 'Could not load your pages.')));
                    return;
                }
                state.pages = response.data.pages || [];
                state.orphans = response.data.orphans || [];
                state.types = response.data.types || [];
                state.typeInfo = {};
                state.types.forEach(function(type) { state.typeInfo[type.name] = type; });
                if (state.type !== 'all' && !state.typeInfo[state.type]) {
                    state.type = 'all';
                }
                renderTypes();
                state.byId = {};
                state.pages.forEach(function(page) { state.byId[page.id] = page; });
                // Drop selections of pages that no longer exist.
                Object.keys(state.selected).forEach(function(id) {
                    if (!state.byId[id]) {
                        delete state.selected[id];
                    }
                });
                $section.find('.wpiko-scan-truncated')
                    .text('Only the first ' + (response.data.limit || 500) + ' of each type are listed.')
                    .prop('hidden', !response.data.truncated);
                renderList();
                renderOrphans();
            }).fail(function() {
                $list.empty().append('<li class="error">Could not load your pages. Close this window and try again.</li>');
            });
        }

        /* ---------------- Rendering ---------------- */

        function renderTypes() {
            var multi = isMultiType();
            $('#wpiko-scan-title-text').text(multi ? 'Your content' : 'Your pages');
            $type.closest('.wpiko-scan-type').prop('hidden', !multi);
            if (!multi) {
                return;
            }
            $type.empty().append($('<option value="all"></option>').text('All content (' + state.pages.length + ')'));
            state.types.forEach(function(type) {
                $type.append($('<option></option>').val(type.name).text(type.label + ' (' + type.count + ')'));
            });
            $type.val(state.type);
        }

        // Status tag next to the title. Private and password-protected items
        // are hidden from visitors on the site, but once scanned the chatbot
        // can share their content with anyone, so they are highlighted.
        function statusTag(page) {
            var info = state.typeInfo[page.type];
            var what = info && info.singular ? info.singular.toLowerCase() : noun(1);
            if (page.status === 'draft') { return { text: 'Draft' }; }
            if (page.status === 'pending') { return { text: 'Pending' }; }
            if (page.status === 'private') {
                return { text: 'Private', warn: true, tip: 'Private: only logged-in editors can see this ' + what + '. Once scanned, the chatbot can share its content with any visitor.' };
            }
            if (page.protected) {
                return { text: 'Password', warn: true, tip: 'Password protected: visitors need the password to see this ' + what + '. Once scanned, the chatbot can share its content with any visitor.' };
            }
            return null;
        }

        function metaText(page) {
            var result = state.results[page.id];
            if (result && result.status === 'failed') {
                return result.message || 'Could not scan this item.';
            }
            if (!page.scanned) {
                return page.modified > 0 ? 'Edited ' + relativeTime(page.modified) : 'Not scanned yet';
            }
            var scanned = page.scanned_at ? 'Scanned ' + relativeTime(page.scanned_at) : 'Scanned (date unknown)';
            if (page.format_changed) {
                return scanned + ' as ' + (MODE_LABELS[page.scanned_mode] || 'Q&A') + ' · rescan to add it as ' + (MODE_LABELS[page.mode] || page.mode);
            }
            if (page.changed) {
                return scanned + ' · page edited ' + relativeTime(page.modified);
            }
            return scanned;
        }

        function badge(page) {
            var result = state.results[page.id];
            var $badge = $('<span class="wpiko-badge"></span>');
            if (result) {
                switch (result.status) {
                    case 'queued': return $badge.addClass('is-queued').text('Queued');
                    case 'running': return $badge.addClass('is-running').text('Scanning…');
                    case 'done': return $badge.addClass('is-ok').text('Done');
                    case 'failed': return $badge.addClass('is-error').text('Failed');
                }
            }
            switch (category(page)) {
                case 'changed': return $badge.addClass('is-warn').text(page.format_changed ? 'Rescan needed' : 'Changed since scan');
                case 'scanned': return $badge.addClass('is-ok').text('Up to date');
                default: return $badge.text('Not scanned');
            }
        }

        function buildRow(page) {
            var checkboxId = 'wpiko-scan-page-' + page.id;
            var result = state.results[page.id];
            var $row = $('<li class="wpiko-file-row wpiko-scan-row"></li>').attr('data-id', page.id);
            if (state.selected[page.id]) {
                $row.addClass('is-selected');
            }
            if (result) {
                $row.addClass('is-' + result.status);
            }

            $row.append(
                $('<input type="checkbox" class="wpiko-scan-check">')
                    .attr('id', checkboxId)
                    .prop('checked', !!state.selected[page.id])
                    .prop('disabled', state.running)
            );

            var $name = $('<span class="file-name"></span>').text(page.title);
            var tag = statusTag(page);
            if (tag) {
                var $tag = $('<span class="wpiko-scan-tag"></span>').text(tag.text);
                if (tag.warn) {
                    $tag.addClass('is-warn').attr('title', tag.tip);
                    $name.append(' ', $tag, $('<span class="screen-reader-text"></span>').text(' (' + tag.tip + ')'));
                } else {
                    $name.append(' ', $tag);
                }
            }
            $row.append(
                $('<label class="file-info"></label>').attr('for', checkboxId)
                    .append($name)
                    .append($('<span class="file-meta"></span>').text(metaText(page)))
            );

            $row.append(badge(page));

            var $actions = $('<span class="wpiko-scan-actions"></span>');
            if (page.scanned) {
                $actions.append(
                    $('<button type="button" class="wpiko-icon-button wpiko-scan-rescan"></button>')
                        .attr('aria-label', 'Rescan ' + page.title)
                        .attr('title', 'Rescan')
                        .prop('disabled', state.running)
                        .append('<span class="dashicons dashicons-update" aria-hidden="true"></span>')
                );
            }
            if (page.url) {
                $actions.append(
                    $('<a class="wpiko-icon-button" target="_blank" rel="noopener noreferrer"></a>')
                        .attr('href', page.url)
                        .attr('aria-label', 'View ' + page.title + ' (opens in a new tab)')
                        .attr('title', 'View page')
                        .append('<span class="dashicons dashicons-external" aria-hidden="true"></span>')
                );
            }
            if (page.scanned) {
                $actions.append(
                    $('<button type="button" class="wpiko-icon-button is-danger wpiko-scan-remove"></button>')
                        .attr('aria-label', 'Remove ' + page.title + ' from the knowledge base')
                        .attr('title', 'Remove from knowledge base')
                        .prop('disabled', state.running)
                        .append('<span class="dashicons dashicons-trash" aria-hidden="true"></span>')
                );
            }
            $row.append($actions);
            return $row;
        }

        function emptyRow(title, text) {
            return $('<li class="wpiko-list-empty"></li>')
                .append('<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>')
                .append($('<strong></strong>').text(title))
                .append($('<span></span>').text(text));
        }

        function renderList() {
            var rows = visiblePages();
            $list.empty();

            if (!state.pages.length) {
                $list.append(emptyRow('Nothing to scan yet', 'Create a page in WordPress and it will show up here.'));
            } else if (!rows.length) {
                $list.append(emptyRow('No pages match', 'Try a different filter or search.'));
            } else if (isGrouped()) {
                // "All content" with several types: one titled group per type,
                // named and iconed the way the plugin registered it.
                state.types.forEach(function(type) {
                    var groupRows = rows.filter(function(page) { return page.type === type.name; });
                    if (!groupRows.length) {
                        return;
                    }
                    $list.append(buildGroupHeader(type, groupRows.length));
                    groupRows.forEach(function(page) { $list.append(buildRow(page)); });
                });
            } else {
                rows.forEach(function(page) { $list.append(buildRow(page)); });
            }

            updateCounts();
            updateSelectionUi();
        }

        function isGrouped() {
            return isMultiType() && state.type === 'all';
        }

        // How a type is added: "AI Q&A" or "Markdown" (nothing if a filter mixes them).
        function typeMode(typeName) {
            var modes = {};
            state.pages.forEach(function(page) {
                if (page.type === typeName && page.mode) {
                    modes[page.mode] = true;
                }
            });
            var keys = Object.keys(modes);
            return keys.length === 1 ? keys[0] : '';
        }

        function modeChip(mode) {
            return $('<span class="wpiko-scan-mode"></span>')
                .addClass('is-' + mode)
                .attr('title', mode === 'qa'
                    ? 'Rewritten into questions and answers by OpenAI (uses API credits)'
                    : 'Added as clean Markdown with a short header (no AI request)')
                .text(MODE_LABELS[mode] || mode);
        }

        function buildGroupHeader(type, count) {
            var checkboxId = 'wpiko-scan-group-' + type.name;
            var mode = typeMode(type.name);
            return $('<li class="wpiko-scan-group"></li>').attr('data-type', type.name)
                .append(
                    $('<input type="checkbox" class="wpiko-scan-group-check">')
                        .attr('id', checkboxId)
                        .prop('disabled', state.running)
                )
                .append(
                    $('<label></label>').attr('for', checkboxId)
                        .append($('<span class="dashicons" aria-hidden="true"></span>').addClass(type.icon || 'dashicons-media-document'))
                        .append($('<span class="wpiko-scan-group-name"></span>').text(type.label))
                        .append($('<span class="wpiko-scan-group-count"></span>').text(count))
                        .append($('<span class="screen-reader-text"></span>').text(' — select all'))
                )
                .append(mode ? modeChip(mode) : null);
        }

        // Group checkboxes reflect the rows shown under them.
        function updateGroupChecks() {
            $list.children('.wpiko-scan-group').each(function() {
                var typeName = $(this).data('type');
                var groupRows = visiblePages().filter(function(page) { return page.type === typeName; });
                var selected = groupRows.filter(function(page) { return state.selected[page.id]; }).length;
                $(this).find('.wpiko-scan-group-check')
                    .prop('disabled', state.running)
                    .prop('checked', groupRows.length > 0 && selected === groupRows.length)
                    .prop('indeterminate', selected > 0 && selected < groupRows.length);
            });
        }

        function updateRow(id) {
            var page = state.byId[id];
            var $existing = $list.children('[data-id="' + id + '"]');
            if (page && $existing.length) {
                $existing.replaceWith(buildRow(page));
            }
        }

        function updateCounts() {
            var inType = state.pages.filter(inCurrentType);
            var counts = { all: inType.length, 'new': 0, changed: 0, scanned: 0 };
            inType.forEach(function(page) {
                var cat = category(page);
                if (cat === 'new') {
                    counts['new']++;
                } else {
                    counts.scanned++;
                    if (cat === 'changed') {
                        counts.changed++;
                    }
                }
            });
            $section.find('[data-count]').each(function() {
                var value = counts[$(this).data('count')];
                $(this).text(value ? value : '');
            });
            $('#wpiko-scan-total').text(state.pages.length).prop('hidden', !state.pages.length);
        }

        function updateSelectionUi() {
            var count = selectedIds().length;
            $generate
                .prop('disabled', count === 0 || state.running)
                .text(count ? 'Scan ' + count + ' ' + noun(count) : 'Scan');

            updateGroupChecks();

            var visible = visiblePages();
            var visibleSelected = visible.filter(function(page) { return state.selected[page.id]; }).length;
            $selectAll
                .prop('disabled', state.running || !visible.length)
                .prop('checked', visible.length > 0 && visibleSelected === visible.length)
                .prop('indeterminate', visibleSelected > 0 && visibleSelected < visible.length);
        }

        function renderOrphans() {
            var $orphanSection = $section.find('.wpiko-scan-orphans-section');
            var $orphanList = $('#wpiko-scan-orphans').empty();
            $orphanSection.prop('hidden', !state.orphans.length);
            $('#wpiko-scan-orphans-count').text(state.orphans.length);

            state.orphans.forEach(function(file) {
                var name = String(file.filename || '')
                    .replace(/^page_/, '')
                    .replace(/\.(txt|md)$/, '')
                    .replace(/-/g, ' ');
                var meta = (file.bytes ? (file.bytes / 1024).toFixed(1) + ' KB · ' : '') + 'Uploaded ' + relativeTime(file.created_at);
                $orphanList.append(
                    $('<li class="wpiko-file-row"></li>')
                        .append('<span class="wpiko-file-type" aria-hidden="true"><span class="dashicons dashicons-media-text"></span></span>')
                        .append(
                            $('<div class="file-info"></div>')
                                .append($('<span class="file-name"></span>').text(name).attr('title', file.filename))
                                .append($('<span class="file-meta"></span>').text(meta))
                        )
                        .append(
                            $('<button type="button" class="wpiko-icon-button is-danger wpiko-scan-remove-orphan"></button>')
                                .attr('data-file-id', file.id)
                                .attr('aria-label', 'Delete ' + name)
                                .attr('title', 'Delete')
                                .append('<span class="dashicons dashicons-trash" aria-hidden="true"></span>')
                        )
                );
            });
        }

        function showStatus(message, type) {
            $status.empty();
            if (message) {
                $status.append($('<span></span>').addClass(type || 'processing').text(message));
            }
        }

        /* ---------------- Queue ---------------- */

        function setProgress(done, total, title) {
            $progress.prop('hidden', false);
            if (done < total) {
                $progress.find('.wpiko-scan-progress-text').text('Scanning ' + (done + 1) + ' of ' + total + (title ? ': ' + title : ''));
            }
            $progress.find('.wpiko-scan-progress-bar > span').css('width', Math.round((done / total) * 100) + '%');
        }

        function downloadText(content, filename, extension) {
            var markdown = extension === 'md';
            var blob = new Blob([content], { type: markdown ? 'text/markdown' : 'text/plain' });
            var link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = filename + (markdown ? '.md' : '.txt');
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(function() { window.URL.revokeObjectURL(link.href); }, 1000);
        }

        function processPage(id) {
            return ajax('wpiko_chatbot_process_page', { page_id: id }, { timeout: 180000 }).then(function(response) {
                if (!response || !response.success) {
                    return $.Deferred().reject(errorMessage(response, 'Could not scan this item.'));
                }
                if (response.data.enable_download) {
                    downloadText(response.data.content, response.data.filename, response.data.extension);
                }
                var upload = {
                    filename: response.data.filename,
                    page_id: response.data.page_id || id
                };
                // Markdown is rebuilt on the server, so it is not sent back.
                if (response.data.mode !== 'markdown') {
                    upload.content = response.data.content;
                }
                return ajax('wpiko_chatbot_upload_qa_to_assistant', upload).then(function(upload) {
                    if (!upload || !upload.success) {
                        return $.Deferred().reject(errorMessage(upload, 'Could not add it to the knowledge base.'));
                    }
                    return upload;
                }, function(xhr, textStatus, error) {
                    return $.Deferred().reject('Upload failed (' + (error || textStatus) + ').');
                });
            }, function(xhr, textStatus, error) {
                if (textStatus === 'timeout') {
                    return $.Deferred().reject('Timed out. The page may be too long; try again.');
                }
                return $.Deferred().reject('Request failed (' + (error || textStatus) + ').');
            });
        }

        function setRunning(running) {
            state.running = running;
            $stop.prop('hidden', !running).prop('disabled', false).text('Stop');
            $section.toggleClass('is-running', running);
            $list.find('.wpiko-scan-check, .wpiko-scan-rescan, .wpiko-scan-remove').prop('disabled', running);
            $section.find('.wpiko-scan-remove-orphan').prop('disabled', running);
            if (window.wpikoModal && typeof window.wpikoModal.setGuard === 'function') {
                window.wpikoModal.setGuard($modal, running ? {
                    title: 'Stop scanning?',
                    message: 'Finished ' + noun(2) + ' stay in the knowledge base. The one being scanned now will finish; the rest are skipped.',
                    confirmLabel: 'Stop and close',
                    cancelLabel: 'Keep scanning',
                    danger: true
                } : null);
            }
            updateSelectionUi();
        }

        function runQueue(ids) {
            if (!ids.length || state.running) {
                return;
            }
            state.stop = false;
            state.results = {};
            ids.forEach(function(id) { state.results[id] = { status: 'queued' }; });
            setRunning(true);
            renderList();
            showStatus('');

            var processed = 0;
            var failed = 0;
            var total = ids.length;

            function finish() {
                setRunning(false);
                var added = processed - failed;
                var skipped = total - processed;
                setProgress(total, total);
                var parts = [added + ' ' + noun(added) + ' added to the knowledge base'];
                if (failed) {
                    parts.push(failed + ' failed');
                }
                if (skipped) {
                    parts.push(skipped + ' skipped');
                }
                $progress.find('.wpiko-scan-progress-text').text(parts.join(' · '));
                showStatus(failed ? 'Some ' + noun(2) + ' could not be scanned. They stay selected so you can try again.' : '', 'error');

                // Finished pages leave the selection; failed ones stay selected for a retry.
                ids.forEach(function(id) {
                    var result = state.results[id];
                    if (result && result.status === 'done') {
                        delete state.selected[id];
                        delete state.results[id];
                    } else if (result && result.status === 'queued') {
                        delete state.results[id];
                    }
                });
                loadPages();
            }

            function next(index) {
                if (index >= total || state.stop) {
                    finish();
                    return;
                }
                var id = ids[index];
                var page = state.byId[id];
                state.results[id] = { status: 'running' };
                updateRow(id);
                setProgress(index, total, page ? page.title : '');

                processPage(id).done(function() {
                    state.results[id] = { status: 'done' };
                    if (page) {
                        page.scanned = true;
                        page.changed = false;
                        page.format_changed = false;
                        page.scanned_mode = page.mode;
                        page.scanned_at = Math.floor(Date.now() / 1000);
                    }
                }).fail(function(message) {
                    failed++;
                    state.results[id] = { status: 'failed', message: typeof message === 'string' ? message : 'Could not scan this item.' };
                }).always(function() {
                    processed++;
                    updateRow(id);
                    next(index + 1);
                });
            }

            next(0);
        }

        /* ---------------- Events ---------------- */

        $list.on('change', '.wpiko-scan-check', function() {
            var id = $(this).closest('.wpiko-scan-row').data('id');
            if (this.checked) {
                state.selected[id] = true;
            } else {
                delete state.selected[id];
            }
            $(this).closest('.wpiko-scan-row').toggleClass('is-selected', this.checked);
            updateSelectionUi();
        });

        $list.on('change', '.wpiko-scan-group-check', function() {
            var typeName = $(this).closest('.wpiko-scan-group').data('type');
            var check = this.checked;
            visiblePages().forEach(function(page) {
                if (page.type !== typeName) {
                    return;
                }
                if (check) {
                    state.selected[page.id] = true;
                } else {
                    delete state.selected[page.id];
                }
            });
            renderList();
        });

        $selectAll.off('change').on('change', function() {
            var check = this.checked;
            visiblePages().forEach(function(page) {
                if (check) {
                    state.selected[page.id] = true;
                } else {
                    delete state.selected[page.id];
                }
            });
            renderList();
        });

        var searchTimer = null;
        $search.off('input').on('input', function() {
            var value = $.trim($(this).val()).toLowerCase();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                state.search = value;
                renderList();
            }, 120);
        });

        $type.off('change').on('change', function() {
            state.type = $(this).val() || 'all';
            renderList();
        });

        $section.on('click', '.wpiko-scan-filter', function() {
            state.filter = $(this).data('filter');
            $section.find('.wpiko-scan-filter').removeClass('is-active').attr('aria-pressed', 'false');
            $(this).addClass('is-active').attr('aria-pressed', 'true');
            renderList();
        });

        $generate.off('click').on('click', function() {
            var ids = selectedIds();
            if (!ids.length) {
                return;
            }
            // Only AI (Q&A) items use credits and take time; Markdown items do not.
            var aiCount = ids.filter(function(id) {
                return !state.byId[id] || state.byId[id].mode !== 'markdown';
            }).length;
            if (aiCount >= BULK_CONFIRM_AT) {
                var others = ids.length - aiCount;
                confirmDialog({
                    title: 'Scan ' + ids.length + ' ' + noun(ids.length) + '?',
                    message: aiCount + ' ' + (aiCount === 1 ? 'page is' : 'pages are') + ' sent to OpenAI to write Q&A, which uses your API credits.' +
                        (others ? ' The other ' + others + ' ' + (others === 1 ? 'is' : 'are') + ' added as Markdown at no cost.' : '') +
                        ' Items are processed one at a time, which can take a few minutes. Keep this window open until it finishes.',
                    confirmLabel: 'Scan'
                }).then(function(ok) {
                    if (ok) {
                        runQueue(ids);
                    }
                });
                return;
            }
            runQueue(ids);
        });

        $stop.off('click').on('click', function() {
            state.stop = true;
            $(this).prop('disabled', true).text('Stopping…');
        });

        $modal.off('wpiko:modal-guard-confirmed.scan').on('wpiko:modal-guard-confirmed.scan', function() {
            state.stop = true;
        });

        $list.on('click', '.wpiko-scan-rescan', function() {
            runQueue([$(this).closest('.wpiko-scan-row').data('id')]);
        });

        function removeFile(fileId, title, message) {
            return confirmDialog({
                title: title,
                message: message,
                confirmLabel: 'Remove',
                danger: true
            }).then(function(ok) {
                if (!ok) {
                    return;
                }
                showStatus('Removing…', 'processing');
                ajax('wpiko_chatbot_delete_file', { file_id: fileId }).done(function(response) {
                    if (response && response.success) {
                        showStatus('');
                    } else {
                        showStatus(errorMessage(response, 'Could not remove the file.'), 'error');
                    }
                }).fail(function() {
                    showStatus('Could not remove the file. Please try again.', 'error');
                }).always(loadPages);
            });
        }

        $list.on('click', '.wpiko-scan-remove', function() {
            var page = state.byId[$(this).closest('.wpiko-scan-row').data('id')];
            if (!page || !page.file_id) {
                return;
            }
            removeFile(page.file_id, 'Remove “' + page.title + '” from the knowledge base?', 'The assistant stops using it. You can scan it again at any time.');
        });

        $section.on('click', '.wpiko-scan-remove-orphan', function() {
            removeFile($(this).data('file-id'), 'Delete this scanned file?', 'The assistant stops using it. This cannot be undone.');
        });

        loadPages();
    }

    // "Keep scanned posts and docs up to date" saves as soon as it changes.
    function initializeAutoSyncSetting() {
        $('#wpiko_scan_auto_sync').off('change').on('change', function() {
            var $toggle = $(this);
            var enabled = $toggle.is(':checked');
            var $status = $('#wpiko_scan_auto_sync_status');
            $status.removeClass('is-ok is-error').text('Saving…');
            ajax('wpiko_chatbot_save_auto_sync_setting', { enabled: enabled }).done(function(response) {
                if (response && response.success) {
                    $status.addClass('is-ok').text('Saved');
                    setTimeout(function() { $status.removeClass('is-ok').text(''); }, 2500);
                } else {
                    $toggle.prop('checked', !enabled);
                    $status.addClass('is-error').text(errorMessage(response, 'Could not save. Please try again.'));
                }
            }).fail(function() {
                $toggle.prop('checked', !enabled);
                $status.addClass('is-error').text('Could not save. Please try again.');
            });
        });
    }

    // "Download a copy of each generated Q&A" saves as soon as it changes.
    function initializeDownloadSetting() {
        $('#enable_qa_download').off('change').on('change', function() {
            var $status = $('#qa_download_status');
            $status.removeClass('is-ok is-error').text('Saving…');
            ajax('wpiko_chatbot_save_qa_download_setting', { enable_download: $(this).is(':checked') }).done(function(response) {
                if (response && response.success) {
                    $status.addClass('is-ok').text('Saved');
                    setTimeout(function() { $status.removeClass('is-ok').text(''); }, 2500);
                } else {
                    $status.addClass('is-error').text('Could not save. Please try again.');
                }
            }).fail(function() {
                $status.addClass('is-error').text('Could not save. Please try again.');
            });
        });
    }

    // Initialize on document ready and whenever the popup content is loaded.
    initializeContentProcessor();
    $(document).on('scanWebsiteContentLoaded', initializeContentProcessor);
});
