jQuery(document).ready(function($) {
    // Add template to the page
    $('body').append(`
        <script type="text/template" id="tmpl-qa-item">
            <div class="qa-item" data-id="{{ data.id }}">
                <div class="qa-question-header">
                    <button type="button" class="qa-toggle" aria-expanded="false">
                        <span class="qa-question-text">{{ data.question }}</span>
                    </button>
                    <button type="button" class="delete-qa wpiko-icon-button is-danger" aria-label="Delete this Q&amp;A" title="Delete">
                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="qa-answer-container" style="display: none;">
                    <label class="qa-field">
                        <span class="qa-field-label">Question</span>
                        <textarea class="qa-question" rows="2" placeholder="e.g. Do you ship internationally?">{{ data.question }}</textarea>
                    </label>
                    <label class="qa-field">
                        <span class="qa-field-label">Answer</span>
                        <textarea class="qa-answer" rows="4" placeholder="Write the answer the chatbot should give">{{ data.answer }}</textarea>
                    </label>
                </div>
            </div>
        </script>
    `);

function initializeQaManagement() {
    var $qaContainer = $('#qa-management-container');
    var $addQaButton = $('#add-qa-button');
    var $downloadQaButton = $('#download-qa-button');
    var $saveAllQaButton = $('#save-all-qa-button');
    var $deleteAllQaButton = $('#delete-all-qa-button');
    var $qaList = $('#qa-list');
    var qaCount = 0;
    
    // Remove the automatic check for duplicate files during initialization
    // Instead, we'll only check when explicitly needed
    
    // Initialize QA files list if it exists
    if ($("#qa-files-list").length) {
        updateQAFileInfo();
        if (typeof wpikoChatbotFileManagement !== 'undefined' && 
            typeof wpikoChatbotFileManagement.refreshQAFileList === 'function') {
            wpikoChatbotFileManagement.refreshQAFileList();
        }
    }

        // Check if elements exist
        if (!$qaContainer.length) {
            return; // Exit if container doesn't exist
        }

        // The shared popup puts the save status next to Save in its footer.
        var $qaStatus = $('#wpiko-qa-save-status');
        if (!$qaStatus.length) {
            // Older markup: create an action row under the list.
            var $actionContainer = $('#qa-action-container');
            if (!$actionContainer.length) {
                $actionContainer = $('<div id="qa-action-container"></div>').insertAfter($qaList);
                $saveAllQaButton.appendTo($actionContainer);
            }
            $qaStatus = $('#qa-status');
            if (!$qaStatus.length) {
                $qaStatus = $('<span id="qa-status"></span>').appendTo($actionContainer);
            }
        }

        var qaTemplate = wp.template('qa-item');
        var isDirty = false;
        var maxPairs = parseInt($qaContainer.data('max'), 10) || 50;
        var FILTER_FROM = 10; // Show the filter box once there are this many pairs.
        var $filterWrap = $('.qa-filter');
        var $filterInput = $('#qa-filter-input');
        var $filterEmpty = $('.qa-filter-empty');
        var savedCount = 0; // Pairs stored on the server (Download / Delete all act on these).

        function askConfirm(options) {
            if (window.wpikoModal && typeof window.wpikoModal.confirm === 'function') {
                return window.wpikoModal.confirm(options);
            }
            return $.Deferred().resolve(window.confirm(options.title)).promise();
        }
        var statusTimer = null;

        function markDirty(value) {
            isDirty = !!value;
            if (window.wpikoModal) {
                window.wpikoModal.setDirty($qaList, isDirty);
            }
            updateButtonStates();
        }

        function showEmptyState() {
            if (!$qaList.find('.qa-item').length && !$qaList.find('.qa-empty-state').length) {
                $qaList.append(
                    '<div class="qa-empty-state">' +
                        '<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>' +
                        '<strong>No Q&amp;A yet</strong>' +
                        '<span>Add your first question and answer. Start with the questions visitors ask most.</span>' +
                    '</div>'
                );
            }
        }

        function showStatus(message, type) {
            clearTimeout(statusTimer);
            $qaStatus.empty().append($('<span></span>').addClass(type).text(message)).show();
            if (type !== 'error' && type !== 'processing') {
                statusTimer = setTimeout(clearStatus, 3000);
            }
        }

        function clearStatus() {
            $qaStatus.empty().hide();
        }

        // Update Q&A file information display
        function updateQAFileInfo() {
            var $qaPairsCount = $('#qa-pairs-count');
            var $qaFileStatus = $('#qa-file-status');
            var $qaFileUpdated = $('#qa-file-updated');
            
            // Update Q&A pairs count
            $qaPairsCount.text(qaCount || '0');
            
            // Get detailed file information
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_get_qa_file_info',
                    security: wpikoChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Update pairs count
                        $qaPairsCount.text(data.qa_count || '0');
                        
                        // Update file status
                        $qaFileStatus.removeClass('status-active status-error status-syncing is-ok is-warn is-error');
                        if (data.has_file && data.qa_count > 0) {
                            $qaFileStatus.text('In sync').addClass('status-active is-ok');
                        } else if (data.qa_count > 0) {
                            $qaFileStatus.text('Needs saving').addClass('status-syncing is-warn');
                        } else {
                            $qaFileStatus.text('No file yet');
                        }
                        
                        // Update last updated time
                        if (data.latest_update) {
                            var date = new Date(data.latest_update);
                            var now = new Date();
                            var diffTime = Math.abs(now - date);
                            var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            
                            if (diffDays === 1) {
                                $qaFileUpdated.text('Today');
                            } else if (diffDays < 7) {
                                $qaFileUpdated.text(diffDays + ' days ago');
                            } else {
                                $qaFileUpdated.text(date.toLocaleDateString());
                            }
                        } else {
                            $qaFileUpdated.text('Never');
                        }
                    }
                },
                error: function() {
                    $qaFileStatus.text('Unavailable').removeClass('status-active status-syncing is-ok is-warn').addClass('status-error is-error');
                    $qaFileUpdated.text('Unknown');
                }
            });
        }

        // Load existing Q&A pairs
        function loadQaPairs() {
            showStatus('Loading Q&A pairs...', 'processing');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_get_qa_pairs',
                    security: wpikoChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $qaList.empty();
                        if (response.data && response.data.length > 0) {
                            response.data.forEach(function(qa) {
                                $qaList.append(qaTemplate(qa));
                            });
                            qaCount = response.data.length;
                        } else {
                            qaCount = 0;
                            showEmptyState();
                        }
                        savedCount = qaCount;
                        clearStatus();
                        markDirty(false);
                        applyFilter();
                        updateQAFileInfo(); // Update file info after loading
                    } else {
                        showStatus('Error loading Q&A pairs: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    showStatus('An error occurred while loading Q&A pairs.', 'error');
                }
            });
        }

        // Update button states
        function updateButtonStates() {
            $addQaButton.prop('disabled', qaCount >= maxPairs)
                .attr('title', qaCount >= maxPairs ? 'You have reached the ' + maxPairs + '-pair limit.' : null);
            $downloadQaButton.prop('disabled', savedCount === 0);
            $saveAllQaButton.prop('disabled', !isDirty);
            $deleteAllQaButton.prop('disabled', savedCount === 0);
            $('#qa-count-label').text(qaCount + ' / ' + maxPairs);
            $filterWrap.prop('hidden', qaCount < FILTER_FROM && !$filterInput.val());
        }

        // Filter rows by question or answer text.
        function applyFilter() {
            var term = $.trim($filterInput.val() || '').toLowerCase();
            var shown = 0;
            $qaList.find('.qa-item').each(function() {
                var $item = $(this);
                var text = ($item.find('.qa-question').val() + ' ' + $item.find('.qa-answer').val()).toLowerCase();
                var match = !term || text.indexOf(term) !== -1 || $item.hasClass('is-new');
                $item.toggle(match);
                if (match) {
                    shown++;
                }
            });
            $filterEmpty.prop('hidden', !term || shown > 0);
        }

        $filterInput.off('input').on('input', applyFilter);

        // Unbind existing events to prevent duplicates
        $addQaButton.off('click');
        $downloadQaButton.off('click');
        $saveAllQaButton.off('click');
        $deleteAllQaButton.off('click');
        $qaList.off('click', '.qa-question-header');
        $qaList.off('click', '.delete-qa');
        $qaList.off('input', 'textarea');

        // Add new Q&A pair
        $addQaButton.on('click', function(e) {
            e.preventDefault();
            if (qaCount < maxPairs) {
                var newQa = {
                    id: '',
                    question: '',
                    answer: ''
                };
                $qaList.find('.qa-empty-state').remove();
                if ($filterInput.val()) {
                    $filterInput.val('');
                    applyFilter();
                }
                var $item = $(qaTemplate(newQa)).addClass('is-new');
                $qaList.prepend($item);
                $item.find('.qa-answer-container').show();
                $item.find('.qa-toggle').attr('aria-expanded', 'true');
                $item.find('.qa-question').trigger('focus');
                qaCount++;
                markDirty(true);
            }
        });

        // Toggle answer visibility
        $qaList.on('click', '.qa-question-header', function(e) {
            if (!$(e.target).closest('.delete-qa').length) {
                var $qaItem = $(this).closest('.qa-item');
                var $answerContainer = $qaItem.find('.qa-answer-container');
                var willOpen = !$answerContainer.is(':visible');
                $qaItem.find('.qa-toggle').attr('aria-expanded', willOpen ? 'true' : 'false');
                $answerContainer.slideToggle(150);
            }
        });

        // Typing marks unsaved changes and keeps the row title in sync.
        $qaList.on('input', 'textarea', function() {
            var $qaItem = $(this).closest('.qa-item');
            $qaItem.removeClass('has-error');
            if ($(this).hasClass('qa-question')) {
                $qaItem.find('.qa-question-text').text($(this).val().trim());
            }
            markDirty(true);
        });

        // Save all Q&A pairs
        $saveAllQaButton.on('click', function(e) {
            e.preventDefault();
            var qaPairs = [];
            var $incomplete = $();
            $qaList.find('.qa-item').each(function() {
                var $qaItem = $(this);
                var question = $qaItem.find('.qa-question').val().trim();
                var answer = $qaItem.find('.qa-answer').val().trim();
                if (question && answer) {
                    qaPairs.push({
                        id: $qaItem.data('id'),
                        question: question,
                        answer: answer
                    });
                } else if (question || answer) {
                    $incomplete = $incomplete.add($qaItem);
                } else {
                    // Completely empty rows are simply dropped.
                    $qaItem.remove();
                    qaCount--;
                }
            });

            if ($incomplete.length) {
                $incomplete.addClass('has-error').find('.qa-answer-container').show();
                $incomplete.find('.qa-toggle').attr('aria-expanded', 'true');
                var $firstEmpty = $incomplete.first().find('textarea').filter(function() { return !$(this).val().trim(); }).first();
                $firstEmpty.trigger('focus');
                showStatus('Each pair needs both a question and an answer.', 'error');
                return;
            }

            // Reverse the array to maintain the original order
            qaPairs.reverse();

            $saveAllQaButton.prop('disabled', true).text('Saving…');
            function resetSaveButton() {
                $saveAllQaButton.text('Save changes');
                updateButtonStates();
            }

            // Removing every pair is the same as Delete all on the server.
            if (qaPairs.length === 0) {
                showStatus('Removing all Q&A…', 'processing');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpiko_chatbot_delete_all_qa',
                        security: wpikoChatbotAdmin.nonce
                    }
                }).done(function(response) {
                    if (response.success) {
                        qaCount = 0;
                        savedCount = 0;
                        markDirty(false);
                        showEmptyState();
                        updateQAFileInfo();
                        showStatus('Saved.', 'success');
                        setTimeout(function() {
                            if (typeof wpikoChatbotFileManagement !== 'undefined') {
                                wpikoChatbotFileManagement.refreshQAFileList();
                            }
                        }, 1000);
                    } else {
                        showStatus('Could not save: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                    }
                }).fail(function() {
                    showStatus('Could not save. Please try again.', 'error');
                }).always(resetSaveButton);
                return;
            }

            showStatus('Saving and updating the knowledge base…', 'processing');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_save_all_qa',
                    security: wpikoChatbotAdmin.nonce,
                    qa_pairs: JSON.stringify(qaPairs)
                },
                complete: resetSaveButton,
                success: function(response) {
                    if (response.success) {
                        markDirty(false);
                        showStatus('Saved.', 'success');
                        loadQaPairs();
                        updateQAFileInfo(); // Update file info after saving
                        // Refresh the Q&A file list with a slight delay to ensure file is indexed
                        setTimeout(function() {
                            if (typeof wpikoChatbotFileManagement !== 'undefined' && 
                                typeof wpikoChatbotFileManagement.refreshQAFileList === 'function') {
                                wpikoChatbotFileManagement.refreshQAFileList();
                            }
                        }, 2000); // 2-second delay to allow for file indexing
                    } else {
                        showStatus('Error saving Q&A pairs: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    showStatus('An error occurred while saving Q&A pairs.', 'error');
                }
            });
        });

        // Delete Q&A pair
        $qaList.on('click', '.delete-qa', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $qaItem = $(this).closest('.qa-item');
            var question = $qaItem.find('.qa-question').val().trim();
            var removeItem = function() {
                $qaItem.remove();
                qaCount--;
                showEmptyState();
                markDirty(true);
            };
            // Nothing typed yet: remove without asking.
            if (!question && !$qaItem.find('.qa-answer').val().trim()) {
                removeItem();
                return;
            }
            askConfirm({
                title: 'Remove this Q&A?',
                message: question ? '“' + question + '” will be removed when you save.' : 'This pair will be removed when you save.',
                confirmLabel: 'Remove',
                danger: true
            }).then(function(ok) {
                if (ok) {
                    removeItem();
                }
            });
        });

        // Delete all Q&A pairs
        $deleteAllQaButton.on('click', function(e) {
            e.preventDefault();
            askConfirm({
                title: 'Delete all Q&A?',
                message: 'Every pair and the knowledge base file will be deleted. This cannot be undone.',
                confirmLabel: 'Delete all',
                danger: true
            }).then(function(ok) {
                if (!ok) {
                    return;
                }
                showStatus('Deleting all Q&A…', 'processing');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpiko_chatbot_delete_all_qa',
                        security: wpikoChatbotAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $qaList.empty();
                            qaCount = 0;
                            savedCount = 0;
                            markDirty(false);
                            showEmptyState();
                            updateQAFileInfo(); // Update file info after deletion
                            showStatus('All Q&A deleted.', 'success');
                        
                            // Refresh the Q&A file list with a slight delay
                            setTimeout(function() {
                                if (typeof wpikoChatbotFileManagement !== 'undefined' && 
                                    typeof wpikoChatbotFileManagement.refreshQAFileList === 'function') {
                                    wpikoChatbotFileManagement.refreshQAFileList();
                                }
                            }, 1000);
                        } else {
                            showStatus('Error deleting Q&A pairs: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showStatus('An error occurred while deleting Q&A pairs.', 'error');
                    }
                });
            });
        });

        // Download Q&A file
        $downloadQaButton.on('click', function(e) {
            e.preventDefault();
            showStatus('Preparing download…', 'processing');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_download_qa_file',
                    security: wpikoChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var blob = new Blob([atob(response.data.content)], {type: 'text/plain'});
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = response.data.filename;
                        link.click();
                        window.URL.revokeObjectURL(link.href);
                        clearStatus();
                    } else {
                        showStatus('Error downloading Q&A file: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    showStatus('An error occurred while downloading the Q&A file.', 'error');
                }
            });
        });

        // Load existing Q&A pairs on initialization
        loadQaPairs();
    }

    // Function to check for duplicate Q&A files
    function checkDuplicateQAFiles() {
        return $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_check_duplicate_qa_files',
                security: wpikoChatbotAdmin.nonce
            }
        });
    }

// Initialize modal handlers
function initializeModalHandlers() {
    // Handle click on the Q&A Management button
    $(document).on('click', '#qa-management-button', function() {
        var $qaStatus = $('#qa-status-message');
        
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        checkDuplicateQAFiles().then(function(response) {
            if (response.success) {
                if (response.data.duplicateFound) {
                    $qaStatus.html('<span class="success">Duplicate files cleaned up successfully.</span>');
                } else {
                    $qaStatus.html('<span class="success">No duplicate files found.</span>');
                }
                setTimeout(function() {
                    $qaStatus.empty();
                }, 3000);
            } else {
                $qaStatus.html('<span class="error">Error checking for duplicate files.</span>');
            }
        }).fail(function() {
            $qaStatus.html('<span class="error">Failed to check for duplicate files.</span>');
        });
    });

    // Handle modal show event (this is triggered when the modal content is loaded)
    $(document).on('qaManagementLoaded', function() {
        initializeQaManagement();
        // Check for duplicates when the Q&A Management modal content is loaded
        var $qaStatus = $('#qa-status-message');
        
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        var $qaStatus = $('#qa-status-message');
        
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        checkDuplicateQAFiles().then(function(response) {
            if (response.success) {
                if (response.data.duplicateFound) {
                    $qaStatus.html('<span class="success">Duplicate files cleaned up successfully.</span>');
                } else {
                    $qaStatus.html('<span class="success">No duplicate files found.</span>');
                }
                setTimeout(function() {
                    $qaStatus.empty();
                }, 3000);
            } else {
                $qaStatus.html('<span class="error">Error checking for duplicate files.</span>');
            }
        }).fail(function() {
            $qaStatus.html('<span class="error">Failed to check for duplicate files.</span>');
        });
    });

    // Handle any wpikoChatbot modal show events
    $(document).on('shown.wpiko.modal', '#qa-management-modal', function() {
        var $qaStatus = $('#qa-status-message');
        
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        checkDuplicateQAFiles().then(function(response) {
            if (response.success) {
                if (response.data.duplicateFound) {
                    $qaStatus.html('<span class="success">Duplicate files cleaned up successfully.</span>');
                } else {
                    $qaStatus.html('<span class="success">No duplicate files found.</span>');
                }
                setTimeout(function() {
                    $qaStatus.empty();
                }, 3000);
            } else {
                $qaStatus.html('<span class="error">Error checking for duplicate files.</span>');
            }
        }).fail(function() {
            $qaStatus.html('<span class="error">Failed to check for duplicate files.</span>');
        });
    });
}


// After file upload, trigger duplicate check
$(document).on('wpikoChatbotFileUploaded', function() {
    var $qaStatus = $('#qa-status-message');
    $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
    
    checkDuplicateQAFiles()
        .then(function(response) {
            if (response.success) {
                if (response.data.duplicateFound) {
                    $qaStatus.html('<span class="success">Duplicate files cleaned up successfully.</span>');
                } else {
                    $qaStatus.html('<span class="success">No duplicate files found.</span>');
                }
            } else {
                $qaStatus.html('<span class="error">Error checking for duplicate files.</span>');
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Duplicate check failed:', errorThrown);
            $qaStatus.html('<span class="error">Failed to check for duplicate files.</span>');
        })
        .always(function() {
            setTimeout(function() {
                $qaStatus.empty();
            }, 3000);
        });
});

// Also check for duplicates when switching between modal tabs
$(document).on('wpikoChatbotTabSwitch', function(e, tabId) {
    if (tabId === 'qa-management') {
        var $qaStatus = $('#qa-status-message');
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        
        checkDuplicateQAFiles()
            .then(function(response) {
                if (response.success) {
                    if (response.data.duplicateFound) {
                        $qaStatus.html('<span class="success">Duplicate files cleaned up successfully.</span>');
                    } else {
                        $qaStatus.html('<span class="success">No duplicate files found.</span>');
                    }
                } else {
                    $qaStatus.html('<span class="error">Error checking for duplicate files.</span>');
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                console.error('Duplicate check failed on tab switch:', errorThrown);
                $qaStatus.html('<span class="error">Failed to check for duplicate files.</span>');
            })
            .always(function() {
                setTimeout(function() {
                    $qaStatus.empty();
                }, 3000);
            });
    }
});

    // Initialize everything
    initializeQaManagement();
    initializeModalHandlers();

    // Make functions available globally
    window.wpikoChatbotQaManagement = {
        initializeQaManagement: initializeQaManagement,
        checkDuplicateQAFiles: checkDuplicateQAFiles,
        initializeModalHandlers: initializeModalHandlers
    };
});
