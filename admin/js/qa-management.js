jQuery(document).ready(function($) {
    // Add template to the page
    $('body').append(`
        <script type="text/template" id="tmpl-qa-item">
            <div class="qa-item" data-id="{{ data.id }}">
                <div class="qa-question-header">
                    <span class="qa-question-text">{{ data.question }}</span>
                    <button class="delete-qa" aria-label="Delete">&times;</button>
                </div>
                <div class="qa-answer-container" style="display: none;">
                    <textarea class="qa-question" placeholder="Enter question">{{ data.question }}</textarea>
                    <textarea class="qa-answer" placeholder="Enter answer">{{ data.answer }}</textarea>
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
        if (typeof wpikoChatbotFileManagement !== 'undefined' && 
            typeof wpikoChatbotFileManagement.refreshQAFileList === 'function') {
            wpikoChatbotFileManagement.refreshQAFileList();
        } else {
            console.log('QA file list refresh function not available');
        }
    }

        // Check if elements exist
        if (!$qaContainer.length) {
            return; // Exit if container doesn't exist
        }

        // Create action container if it doesn't exist
        var $actionContainer = $('#qa-action-container');
        if (!$actionContainer.length) {
            $actionContainer = $('<div id="qa-action-container"></div>').insertAfter($qaList);
            $saveAllQaButton.appendTo($actionContainer);
        }

        // Create status element if it doesn't exist
        var $qaStatus = $('#qa-status');
        if (!$qaStatus.length) {
            $qaStatus = $('<span id="qa-status"></span>').appendTo($actionContainer);
        }

        var qaTemplate = wp.template('qa-item');

        function showStatus(message, type) {
            $qaStatus.html('<span class="' + type + '">' + message + '</span>').show();
            if (type !== 'error' && type !== 'processing') {
                setTimeout(clearStatus, 3000);
            }
        }

        function clearStatus() {
            $qaStatus.empty().hide();
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
                            showStatus('Q&A pairs loaded successfully.', 'success');
                        } else {
                            qaCount = 0;
                            $qaList.append('<p>No Q&A pairs found.</p>');
                            clearStatus(); // Clear the loading status when no pairs found
                        }
                        updateButtonStates();
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
            $addQaButton.prop('disabled', qaCount >= 30);
            $downloadQaButton.prop('disabled', qaCount === 0);
            $saveAllQaButton.prop('disabled', qaCount === 0);
            $deleteAllQaButton.prop('disabled', qaCount === 0);
        }

        // Unbind existing events to prevent duplicates
        $addQaButton.off('click');
        $downloadQaButton.off('click');
        $saveAllQaButton.off('click');
        $deleteAllQaButton.off('click');
        $qaList.off('click', '.qa-question-header');
        $qaList.off('click', '.delete-qa');

        // Add new Q&A pair
        $addQaButton.on('click', function(e) {
            e.preventDefault();
            if (qaCount < 30) {
                var newQa = {
                    id: '',
                    question: 'New Question',
                    answer: ''
                };
                $qaList.prepend(qaTemplate(newQa));
                qaCount++;
                updateButtonStates();
                showStatus('New Q&A pair added. Remember to save your changes.', 'success');
            }
        });

        // Toggle answer visibility
        $qaList.on('click', '.qa-question-header', function(e) {
            if (!$(e.target).hasClass('delete-qa')) {
                var $qaItem = $(this).closest('.qa-item');
                var $answerContainer = $qaItem.find('.qa-answer-container');
                $answerContainer.slideToggle();
            }
        });

        // Save all Q&A pairs
        $saveAllQaButton.on('click', function(e) {
            e.preventDefault();
            showStatus('Saving Q&A pairs and generating file...', 'processing');
            var qaPairs = [];
            $('.qa-item').each(function() {
                var $qaItem = $(this);
                var question = $qaItem.find('.qa-question').val().trim();
                var answer = $qaItem.find('.qa-answer').val().trim();
                if (question && answer) {
                    qaPairs.push({
                        id: $qaItem.data('id'),
                        question: question,
                        answer: answer
                    });
                }
            });
            
            // Reverse the array to maintain the original order
            qaPairs.reverse();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_save_all_qa',
                    security: wpikoChatbotAdmin.nonce,
                    qa_pairs: JSON.stringify(qaPairs)
                },
                success: function(response) {
                    if (response.success) {
                        showStatus('All Q&A pairs saved and file generated successfully.', 'success');
                        loadQaPairs();
                        if (typeof wpikoChatbotFileManagement !== 'undefined' && 
                            typeof wpikoChatbotFileManagement.refreshQAFileList === 'function') {
                            wpikoChatbotFileManagement.refreshQAFileList();
                        }
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
            if (confirm('Are you sure you want to delete this Q&A pair?')) {
                var $qaItem = $(this).closest('.qa-item');
                $qaItem.remove();
                qaCount--;
                updateButtonStates();
                showStatus('Q&A pair marked for deletion. Remember to save your changes.', 'success');
            }
        });

        // Delete all Q&A pairs
        $deleteAllQaButton.on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete all Q&A pairs? This action cannot be undone.')) {
                showStatus('Deleting all Q&A pairs...', 'processing');
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
                            updateButtonStates();
                            showStatus('All Q&A pairs deleted successfully.', 'success');
                        
                            if (typeof wpikoChatbotFileManagement !== 'undefined' && 
                                typeof wpikoChatbotFileManagement.refreshQAFileList === 'function') {
                                wpikoChatbotFileManagement.refreshQAFileList();
                            }
                        } else {
                            showStatus('Error deleting Q&A pairs: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showStatus('An error occurred while deleting Q&A pairs.', 'error');
                    }
                });
            }
        });

        // Download Q&A file
        $downloadQaButton.on('click', function(e) {
            e.preventDefault();
            showStatus('Preparing Q&A file for download...', 'processing');
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
                        showStatus('Q&A file downloaded successfully.', 'success');
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
        console.log('Q&A Management button clicked');
        var $qaStatus = $('#qa-status-message');
        
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        checkDuplicateQAFiles().then(function(response) {
            if (response.success) {
                if (response.data.duplicateFound) {
                    console.log('Duplicate Q&A files cleaned up on button click');
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
        console.log('Q&A Management modal content loaded');
        initializeQaManagement();
        // Check for duplicates when the Q&A Management modal content is loaded
        var $qaStatus = $('#qa-status-message');
        
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        var $qaStatus = $('#qa-status-message');
        
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        checkDuplicateQAFiles().then(function(response) {
            if (response.success) {
                if (response.data.duplicateFound) {
                    console.log('Duplicate Q&A files cleaned up on modal load');
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
        console.log('Q&A Management modal shown');
        var $qaStatus = $('#qa-status-message');
        
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        checkDuplicateQAFiles().then(function(response) {
            if (response.success) {
                if (response.data.duplicateFound) {
                    console.log('Duplicate Q&A files cleaned up on modal show');
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
    console.log('File upload completed, checking for duplicates');
    var $qaStatus = $('#qa-status-message');
    $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
    
    checkDuplicateQAFiles()
        .then(function(response) {
            if (response.success) {
                if (response.data.duplicateFound) {
                    console.log('Duplicate Q&A files cleaned up after file upload');
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
        console.log('Q&A Management tab active, checking for duplicates');
        var $qaStatus = $('#qa-status-message');
        $qaStatus.html('<span class="processing">Checking for duplicate files...</span>');
        
        checkDuplicateQAFiles()
            .then(function(response) {
                if (response.success) {
                    if (response.data.duplicateFound) {
                        console.log('Duplicate Q&A files cleaned up on tab switch');
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
