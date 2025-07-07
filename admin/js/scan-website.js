jQuery(document).ready(function($) {
    // Main initialization function
    function initializeContentProcessor() {
        var $searchInput = $('#process_url_search');
        var $searchResults = $('#page-search-results');
        var $generateButton = $('#process_url_button');
        var selectedPageUrl = null;

        if (!$searchInput.length) {
            return; // Exit if elements don't exist
        }

        // Unbind any existing events to prevent duplicates
        $searchInput.off('input');
        $generateButton.off('click');

        // Search input handler
        $searchInput.on('input', function() {
            var query = $(this).val();
            console.log('Search query:', query); // Debug log

            if (query.length >= 3) {
                $.ajax({
                    url: wpikoChatbotAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wpiko_chatbot_search_pages',
                        security: wpikoChatbotAdmin.nonce,
                        query: query
                    },
                    beforeSend: function() {
                        $searchResults.html('<p>Searching...</p>');
                    },
                    success: function(response) {
                        console.log('Search response:', response); // Debug log
                        if (response.success) {
                            displaySearchResults(response.data);
                        } else {
                            $searchResults.html('<p class="error">' + (response.data ? response.data.message : 'Error searching pages') + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Search error:', error); // Debug log
                        $searchResults.html('<p class="error">Error searching pages. Please try again.</p>');
                    }
                });
            } else {
                $searchResults.empty();
                $generateButton.prop('disabled', true);
            }
        });
        
        // Save download setting handler
        $('#save_qa_download_setting').on('click', function() {
            var enableDownload = $('#enable_qa_download').is(':checked');
        
            $.ajax({
                url: wpikoChatbotAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_save_qa_download_setting',
                    security: wpikoChatbotAdmin.nonce,
                    enable_download: enableDownload
                },
                success: function(response) {
                    if (response.success) {
                        var message = $('<div class="notice notice-success is-dismissible"><p>Download setting saved successfully.</p></div>');
                    
                        // Remove any existing notices first
                        $('.download-files-option').nextAll('.notice').remove();
                    
                        // Insert the new notice
                        message.insertAfter('.download-files-option');
                    
                        // Auto-dismiss after 3 seconds
                        setTimeout(function() {
                            message.fadeOut('slow', function() {
                                $(this).remove();
                            });
                        }, 3000);
                    } else {
                        $('.download-files-option').nextAll('.notice').remove();
                        $('<div class="notice notice-error is-dismissible"><p>Failed to save setting. Please try again.</p></div>')
                            .insertAfter('.download-files-option');
                    }
                },
                error: function() {
                    $('.download-files-option').nextAll('.notice').remove();
                    $('<div class="notice notice-error is-dismissible"><p>An error occurred. Please try again.</p></div>')
                        .insertAfter('.download-files-option');
                }
            });
        });

        function displaySearchResults(pages) {
            $searchResults.empty();
            if (pages && pages.length > 0) {
                var $ul = $('<ul class="page-search-results-list">');
                pages.forEach(function(page) {
                    $ul.append(
                        $('<li>')
                            .text(page.title)
                            .data('page-url', page.url)
                            .addClass('page-search-result-item')
                            .click(function() {
                                selectedPageUrl = $(this).data('page-url');
                                $searchInput.val($(this).text());
                                $searchResults.empty();
                                $generateButton.prop('disabled', false);
                            })
                    );
                });
                $searchResults.append($ul);
            } else {
                $searchResults.html('<p>No pages found.</p>');
            }
        }

        // Generate button handler
        $generateButton.on('click', function() {
            if (selectedPageUrl) {
                generateQAContent(selectedPageUrl);
            }
        });

        function generateQAContent(url) {
            $('#url-processing-status').html('<span class="processing">Generating Q&A content... This may take a few minutes for large pages.</span>');

            $.ajax({
                url: wpikoChatbotAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_process_url',
                    security: wpikoChatbotAdmin.nonce,
                    url: url
                },
                timeout: 180000, // Increase timeout to 3 minutes for large pages
                success: function(response) {
                    console.log('Generate response:', response); // Debug log
                    if (response.success) {
                        if (response.data.enable_download) {
                            downloadQAContent(response.data.content, response.data.filename);
                        }
                        
                        uploadQAToAssistant(response.data.content, response.data.filename);

                        let statusMessage = '<span class="success">' +
                            'Q&A content generated successfully. <br>';
                        
                        if (response.data.enable_download) {
                            statusMessage += 'File "' + response.data.filename + '.txt" has been downloaded. <br>';
                        }
                        
                        statusMessage += 'Uploading to Assistant API... <br>' +
                            '</span>';

                        $('#url-processing-status').html(statusMessage);
                    } else {
                        $('#url-processing-status').html(
                            '<span class="error">' +
                            'Error: ' + (response.data ? response.data.message : 'Failed to generate Q&A content') + '<br>' +
                            'Please check the WordPress error log for more details.' +
                            '</span>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Generate error:', error); // Debug log
                    $('#url-processing-status').html(
                        '<span class="error">' +
                        'An error occurred while generating Q&A content. <br>' +
                        'The page may be too large or the server timed out. <br>' +
                        'Try breaking it into smaller sections. <br>' +
                        'Status: ' + status + '<br>' +
                        'Error: ' + error +
                        '</span>'
                    );
                }
            });
        }

        function downloadQAContent(content, filename) {
            var blob = new Blob([content], {type: 'text/plain'});
            var link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = filename + '.txt';
            link.click();
            window.URL.revokeObjectURL(link.href);
        }

        function uploadQAToAssistant(content, filename) {
            $.ajax({
                url: wpikoChatbotAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_upload_qa_to_assistant',
                    security: wpikoChatbotAdmin.nonce,
                    content: content,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        $('#url-processing-status').append(
                            '<span class="success">' +
                            'File uploaded successfully to Assistant API. <br>' +
                            '</span>'
                        );
                        // Add a small delay to ensure cache cleanup completes before refreshing the list
                        if (typeof wpikoChatbotFileManagement !== 'undefined' && 
                            typeof wpikoChatbotFileManagement.refreshUrlProcessingFileList === 'function') {
                            setTimeout(function() {
                                wpikoChatbotFileManagement.refreshUrlProcessingFileList();
                            }, 500); // 500ms delay to allow cache cleanup to complete
                        }
                    } else {
                        $('#url-processing-status').append(
                            '<span class="error">' +
                            'Error uploading to Assistant API: ' + (response.data ? response.data.message : 'Upload failed') + '<br>' +
                            '</span>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $('#url-processing-status').append(
                        '<span class="error">' +
                        'An error occurred while uploading to Assistant API. <br>' +
                        'Status: ' + status + '<br>' +
                        'Error: ' + error +
                        '</span>'
                    );
                }
            });
        }
    }

    // Initialize on document ready
    initializeContentProcessor();

    // Initialize when modal content is loaded
    $(document).on('scanWebsiteContentLoaded', initializeContentProcessor);
    
});