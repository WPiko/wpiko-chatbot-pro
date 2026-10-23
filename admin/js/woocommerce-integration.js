jQuery(document).ready(function($) {

    // In-page message in the wpiko notice style; falls back to a browser
    // message on older core versions without the notices helper.
    function wpikoNotify(message, type, options) {
        if (window.wpikoChatbotNotices) {
            return window.wpikoChatbotNotices.show(message, type, options);
        }
        window.alert(message);
        return null;
    }
    function initializeWooCommerceIntegration() {
        var $container = $('#responses-woocommerce-integration-container');
        
        // Check if container exists
        if (!$container.length) {
            // Try to initialize the WooCommerce files list if the section exists
            if ($('#woocommerce-integration-section').length && $('#woocommerce-files-list').length) {
                if (typeof wpikoChatbotFileManagement !== 'undefined' && 
                    typeof wpikoChatbotFileManagement.refreshWooCommerceFileList === 'function') {
                    wpikoChatbotFileManagement.refreshWooCommerceFileList();
                }
            }
            return; // Exit if main container doesn't exist
        }

        // Cache DOM elements
        var $wooCommerceIntegration = $('#woocommerce_integration_enabled');
        var $productsAutoUpdate = $('#products_auto_sync');
        var $ordersSync = $('#order_lookup_enabled');
        var $syncButton = $('#sync_existing_products');
        var $downloadProductsButton = $('#download_products_json');
        var $statusDiv = $('#woocommerce-integration-status');
        var $saveProductFieldsButton = $('#save_product_fields');
        var $productFieldsToggle = $('#product-fields-toggle');
        var $productFieldsContent = $('#product-fields-content');
        var $productFieldsSummary = $('#product-fields-summary');
        
        // New order fields elements
        var $saveOrderFieldsButton = $('#save_order_fields');
        var $orderFieldsToggle = $('#order-fields-toggle');
        var $orderFieldsContent = $('#order-fields-content');
        var $orderFieldsSummary = $('#order-fields-summary');

        // Unbind existing events to prevent duplicates
        $wooCommerceIntegration.off('change');
        $productsAutoUpdate.off('change');
        $ordersSync.off('change');
        $syncButton.off('click');
        $downloadProductsButton.off('click');
        $saveProductFieldsButton.off('click');
        $productFieldsToggle.off('click');
        $saveOrderFieldsButton.off('click');
        $orderFieldsToggle.off('click');

        // Function to toggle accessibility and visibility of options
        function toggleWooCommerceOptions() {
            var isEnabled = $wooCommerceIntegration.is(':checked');
            
            // Ensure content areas are closed by default
            $productFieldsContent.hide();
            $orderFieldsContent.hide();
            
            // Sections that only make sense while the integration is on.
            var $dependent = $('#woocommerce-integration-section .wpiko-woo-requires-enabled');
            if ($dependent.length) {
                $dependent.toggle(isEnabled);
            } else {
                // Older markup (table rows).
                [
                    $('#product-fields-toggle').closest('tr'),
                    $('#sync_existing_products').closest('tr'),
                    $('#products_auto_sync').closest('tr'),
                    $('#order-fields-toggle').closest('tr'),
                    $('#order_lookup_enabled').closest('tr'),
                    $('#woocommerce-integration-section .download-files-option')
                ].forEach(function(element) {
                    if (element.length) {
                        element.toggle(isEnabled);
                    }
                });
            }
            $productFieldsToggle.attr('aria-expanded', 'false').removeClass('active');
            $orderFieldsToggle.attr('aria-expanded', 'false').removeClass('active');

            // WooCommerce Files list should always be visible if there are files
            // So we don't toggle it here
            
            // Toggle form controls (keep existing functionality)
            $productsAutoUpdate.prop('disabled', !isEnabled);
            $ordersSync.prop('disabled', !isEnabled);
            $syncButton.prop('disabled', !isEnabled);
            $downloadProductsButton.prop('disabled', !isEnabled);
            $saveProductFieldsButton.prop('disabled', !isEnabled);
            $saveOrderFieldsButton.prop('disabled', !isEnabled);
            
            // Toggle input fields
            $('.product-field-option input').prop('disabled', !isEnabled);
            $('.order-field-option input').prop('disabled', !isEnabled);
            $productFieldsToggle.toggleClass('disabled', !isEnabled);
            $orderFieldsToggle.toggleClass('disabled', !isEnabled);
            
            if (!isEnabled) {
                $productsAutoUpdate.val('disabled');
                $ordersSync.val('0');
            }
        }

        // Function to update the product fields summary text
        function updateProductFieldsSummary() {
            var totalFields = $('.product-field-option input').length;
            var enabledFields = $('.product-field-option input:checked').length;
            $productFieldsSummary.find('span').text(enabledFields + ' of ' + totalFields + ' fields selected');
        }
        
        // Function to update the order fields summary text
        function updateOrderFieldsSummary() {
            var totalFields = $('.order-field-option input').length;
            var enabledFields = $('.order-field-option input:checked').length;
            $orderFieldsSummary.find('span').text(enabledFields + ' of ' + totalFields + ' fields selected');
        }

        // Product Fields Toggle Handler
        $productFieldsToggle.on('click', function() {
            if ($wooCommerceIntegration.is(':checked')) {
                $(this).toggleClass('active').attr('aria-expanded', $(this).hasClass('active') ? 'true' : 'false');
                $productFieldsContent.slideToggle(200);
                updateProductFieldsSummary();
            }
        });
        
        // Order Fields Toggle Handler
        $orderFieldsToggle.on('click', function() {
            if ($wooCommerceIntegration.is(':checked')) {
                $(this).toggleClass('active').attr('aria-expanded', $(this).hasClass('active') ? 'true' : 'false');
                $orderFieldsContent.slideToggle(200);
                updateOrderFieldsSummary();
            }
        });

        // Function to update integration status display
        function updateWooCommerceIntegrationStatus(isEnabled) {
            if (isEnabled) {
                $statusDiv.empty().hide();
            } else {
                $statusDiv.hide();
            }
        }

        // Function to check sync progress with improved error handling
        function checkSyncProgress() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_sync_progress',
                    security: wpikoChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var status = response.data;
                        var progress = status.progress || 0;
                        var syncStatus = status.status || '';
                        var errorMsg = status.error || '';
                        var lastSync = status.last_sync || '';
                        var lockActive = status.lock_active || false;
                        var phase = status.phase || '';
                        var processedProducts = parseInt(status.processed_products, 10) || 0;
                        var totalProducts = parseInt(status.total_products, 10) || 0;
                        
                        // Update progress message
                        let statusText = '';
                        let statusClass = 'status-info';
                        
                        if (syncStatus === 'running' || syncStatus === 'scheduled') {
                            if (syncStatus === 'scheduled') {
                                statusText = 'Sync scheduled...';
                            } else if (phase === 'uploading') {
                                statusText = `Uploading product file: ${progress}%`;
                            } else if (totalProducts > 0) {
                                statusText = `Sync in progress: ${progress}% (${processedProducts} of ${totalProducts} products)`;
                            } else {
                                statusText = `Sync in progress: ${progress}%`;
                            }

                            $('#sync_status')
                                .text(statusText)
                                .removeClass('status-success status-error')
                                .addClass('status-info');
                                
                            // Continue polling
                            setTimeout(checkSyncProgress, 3000);
                            return;
                        } 
                        else if (syncStatus === 'completed') {
                            statusText = 'Sync completed successfully!';
                            statusClass = 'status-success';
                            if (lastSync) {
                                statusText += ' Last sync: ' + lastSync;
                            }
                        }
                        else if (syncStatus === 'completed_fallback') {
                            statusText = 'Sync completed with fallback method.';
                            statusClass = 'status-success';
                        }
                        else if (syncStatus === 'failed') {
                            statusText = 'Sync failed. ';
                            statusClass = 'status-error';
                            if (errorMsg) {
                                statusText += 'Error: ' + errorMsg;
                            }
                        }
                        else if (lockActive) {
                            statusText = 'Another sync process is running.';
                            statusClass = 'status-info';
                            
                            // Continue polling
                            setTimeout(checkSyncProgress, 3000);
                            return;
                        }
                        else {
                            if (progress === 100) {
                                statusText = 'Sync completed.';
                                statusClass = 'status-success';
                            } else {
                                statusText = 'Unknown status.';
                                statusClass = 'status-warning';
                            }
                        }

                        // The sync has finished (the product file may have been
                        // uploaded in the background), so let the AI Configuration
                        // page refresh its Vector Store information.
                        $(document).trigger('wpiko:knowledgeChanged');
                        
                        $('#sync_status')
                            .text(statusText)
                            .removeClass('status-info status-error status-success status-warning')
                            .addClass(statusClass);
                            
                        $syncButton.prop('disabled', false);
                        
                        if (statusClass === 'status-success') {
                            // Refresh files list if sync was successful
                            if (typeof wpikoChatbotFileManagement !== 'undefined') {
                                wpikoChatbotFileManagement.refreshWooCommerceFileList();
                            }
                        }
                    } else {
                        $('#sync_status')
                            .text('Error checking sync progress.')
                            .removeClass('status-info status-success status-warning')
                            .addClass('status-error');
                        $syncButton.prop('disabled', false);
                    }
                },
                error: function() {
                    $('#sync_status')
                        .text('Error checking sync progress.')
                        .removeClass('status-info status-success status-warning')
                        .addClass('status-error');
                    $syncButton.prop('disabled', false);
                }
            });
        }

        // WooCommerce Integration Toggle Handler
        $wooCommerceIntegration.on('change', function() {
            var isEnabled = $(this).is(':checked');
            $statusDiv.html('<p class="wpiko-inline-status">Saving…</p>').show();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'toggle_woocommerce_integration',
                    security: wpikoChatbotAdmin.nonce,
                    enabled: isEnabled
                },
                        success: function(response) {
                    if (response.success) {
                        var newState = response.data.enabled;
                        $wooCommerceIntegration.prop('checked', newState);
                        wpikoNotify(response.data.message, 'success', { after: $statusDiv });

                        setTimeout(function() {
                            updateWooCommerceIntegrationStatus(newState);
                            toggleWooCommerceOptions();
                            if (typeof wpikoChatbotFileManagement !== 'undefined') {
                                wpikoChatbotFileManagement.refreshWooCommerceFileList();
                            }
                        }, 1000);                        if (!newState) {
                            $productsAutoUpdate.val('disabled');
                            $ordersSync.val('0').trigger('change');
                        }
                    } else {
                        wpikoNotify('Error: ' + (response.data ? response.data.message : 'Unknown error'), 'error', { after: $statusDiv });
                        $wooCommerceIntegration.prop('checked', !isEnabled);
                        $statusDiv.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    wpikoNotify('An error occurred. Please try again.', 'error', { after: $statusDiv });
                    $wooCommerceIntegration.prop('checked', !isEnabled);
                    $statusDiv.hide();
                },
                complete: function() {
                    toggleWooCommerceOptions();
                }
            });
        });

        // Sync Existing Products Handler with improved error handling
        $syncButton.on('click', function() {
            var $status = $('#sync_status');
            $(this).prop('disabled', true);
            $status.text('Starting sync process...')
                .removeClass('status-success status-error status-warning')
                .addClass('status-info');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sync_existing_products',
                    security: wpikoChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.text(response.data.message)
                            .removeClass('status-error status-success status-warning')
                            .addClass('status-info');
                            
                        checkSyncProgress(); // Start monitoring progress
                        
                    } else {
                        $status.text('Error: ' + (response.data.message || 'Unknown error'))
                            .removeClass('status-info status-success status-warning')
                            .addClass('status-error');
                        $syncButton.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'An error occurred. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    
                    $status.text('Error: ' + errorMsg)
                        .removeClass('status-info status-success status-warning')
                        .addClass('status-error');
                    $syncButton.prop('disabled', false);
                },
                timeout: 0
            });
        });

        // Products Auto-Sync Handler
        $productsAutoUpdate.on('change', function() {
            var frequency = $(this).val();
            var $status = $('<span>').insertAfter($(this))
                .text('Loading...')
                .addClass('status-loading');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_products_auto_sync',
                    security: wpikoChatbotAdmin.nonce,
                    frequency: frequency
                },
                success: function(response) {
                    $status.remove();
                    if (response.success) {
                        wpikoNotify('Products auto-update setting updated successfully.', 'success', { after: $productsAutoUpdate });
                        if (typeof wpikoChatbotFileManagement !== 'undefined') {
                            wpikoChatbotFileManagement.refreshWooCommerceFileList();
                        }
                    } else {
                        wpikoNotify('Failed to update products auto-update setting.', 'error', { after: $productsAutoUpdate });
                    }
                },
                error: function() {
                    $status.remove();
                    wpikoNotify('An error occurred while updating products auto-update setting.', 'error', { after: $productsAutoUpdate });
                }
            });
        });

        // Controlled Order Assistance setting
        $ordersSync.on('change', function() {
            var syncOption = $(this).val();
            var $status = $('<span>').insertAfter($(this))
                .text('Updating...')
                .addClass('status-loading');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_update_order_lookup',
                    security: wpikoChatbotAdmin.nonce,
                    enabled: syncOption
                },
                success: function(response) {
                    if (response.success) {
                        $status.text(response.data.message)
                            .removeClass('status-loading')
                            .addClass('status-success');
                        setTimeout(function() { $status.fadeOut(400, function() { $(this).remove(); }); }, 5000);
                    } else {
                        $status.text('Error: ' + response.data.message)
                            .removeClass('status-loading')
                            .addClass('status-error');
                        setTimeout(function() { $status.fadeOut(400, function() { $(this).remove(); }); }, 8000);
                    }
                },
                error: function() {
                    $status.text('An error occurred while updating order assistance.')
                        .removeClass('status-loading')
                        .addClass('status-error');
                    setTimeout(function() { $status.fadeOut(400, function() { $(this).remove(); }); }, 8000);
                }
            });
        });

        // Download Products JSON Handler
        $downloadProductsButton.on('click', function() {
            var $status = $('#download_status');
            $(this).prop('disabled', true);
            $status.text('Generating JSON...')
                .removeClass('status-success status-error')
                .addClass('status-info');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'download_products_json',
                    security: wpikoChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('JSON generated successfully. Downloading...')
                            .removeClass('status-info')
                            .addClass('status-success');
                        
                        var jsonString = JSON.stringify(response.data.products, null, 2);
                        var blob = new Blob([jsonString], {type: 'application/json'});
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = 'woocommerce_products.json';
                        link.click();
                        
                        setTimeout(function() {
                            $status.text('Download complete.');
                            $downloadProductsButton.prop('disabled', false);
                        }, 2000);
                    } else {
                        $status.text('Error: ' + response.data.message)
                            .removeClass('status-info')
                            .addClass('status-error');
                        $downloadProductsButton.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.text('An error occurred. Please try again.')
                        .removeClass('status-info')
                        .addClass('status-error');
                    $downloadProductsButton.prop('disabled', false);
                }
            });
        });

        // Product Fields Options Handler
        $saveProductFieldsButton.on('click', function() {
            var $status = $('#product_fields_status');
            $status.text('Saving…').removeClass('is-ok is-error');

            var fields = {};
            $('.product-field-option input').each(function() {
                var fieldName = $(this).attr('name').match(/product_fields\[(.*?)\]/)[1];
                fields[fieldName] = $(this).prop('checked');
            });

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_product_fields',
                    security: wpikoChatbotAdmin.nonce,
                    fields: fields
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved').addClass('is-ok');
                        
                        // Update summary count
                        updateProductFieldsSummary();
                            
                        setTimeout(function() {
                            $status.text('').removeClass('is-ok');
                        }, 2500);
                    } else {
                        $status.text('Error: ' + response.data.message).addClass('is-error');
                    }
                },
                error: function() {
                    $status.text('Could not save. Please try again.').addClass('is-error');
                }
            });
        });

        // Order Fields Options Handler
        $saveOrderFieldsButton.on('click', function() {
            var $status = $('#order_fields_status');
            $status.text('Saving…').removeClass('is-ok is-error');

            var fields = {};
            $('.order-field-option input').each(function() {
                var fieldName = $(this).attr('name').match(/order_fields\[(.*?)\]/)[1];
                fields[fieldName] = $(this).prop('checked');
            });

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_order_fields',
                    security: wpikoChatbotAdmin.nonce,
                    fields: fields
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('Saved').addClass('is-ok');
                        
                        // Update summary count
                        updateOrderFieldsSummary();
                            
                        setTimeout(function() {
                            $status.text('').removeClass('is-ok');
                        }, 2500);
                        
                        // Refresh files list
                        if (typeof wpikoChatbotFileManagement !== 'undefined') {
                            wpikoChatbotFileManagement.refreshWooCommerceFileList();
                        }
                    } else {
                        $status.text('Error: ' + response.data.message).addClass('is-error');
                    }
                },
                error: function() {
                    $status.text('Could not save. Please try again.').addClass('is-error');
                }
            });
        });

        // Field choices save right away (debounced), like the other settings here.
        var productFieldsTimer = null;
        var orderFieldsTimer = null;
        $('.product-field-option input').off('change').on('change', function() {
            updateProductFieldsSummary();
            clearTimeout(productFieldsTimer);
            productFieldsTimer = setTimeout(function() { $saveProductFieldsButton.trigger('click'); }, 400);
        });
        $('.order-field-option input').off('change').on('change', function() {
            updateOrderFieldsSummary();
            clearTimeout(orderFieldsTimer);
            orderFieldsTimer = setTimeout(function() { $saveOrderFieldsButton.trigger('click'); }, 400);
        });

        // Initialize the state on load
        toggleWooCommerceOptions();
        updateWooCommerceIntegrationStatus($wooCommerceIntegration.is(':checked'));
        updateProductFieldsSummary();
        updateOrderFieldsSummary();
        
        // Initialize WooCommerce files list if it exists
        if ($('#woocommerce-files-list').length) {
            if (typeof wpikoChatbotFileManagement !== 'undefined' && 
                typeof wpikoChatbotFileManagement.refreshWooCommerceFileList === 'function') {
                wpikoChatbotFileManagement.refreshWooCommerceFileList();
            }
        }

        // Initialize tooltips if available
        if (typeof $.fn.tooltip === 'function') {
            $('.tooltip-icon').tooltip({
                position: { my: "left+15 center", at: "right center" }
            });
        }
    }

    // Initialize when modal content is loaded
    $(document).on('woocommerceIntegrationLoaded', initializeWooCommerceIntegration);
    initializeWooCommerceIntegration();

    // Add click handler for WooCommerce integration button
    $(document).on('click', '#woocommerce-integration-button', function() {
        // Check if WooCommerce is active
        if (typeof wpikoChatbot !== 'undefined' && wpikoChatbot.is_woocommerce_active === false) {
            wpikoNotify('WooCommerce is not active. Please install and activate WooCommerce to use this feature.', 'warning');
            return;
        }
        
        // Show the modal
        $('#woocommerce-integration-modal').fadeIn();
        
        // Load WooCommerce integration content if not already loaded
        var $container = $('#responses-woocommerce-integration-container');
        if ($container.is(':empty')) {
            $container.html('<div class="loading">Loading WooCommerce Integration...</div>');
            
            $.ajax({
                url: wpikoChatbot.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_load_woocommerce_integration',
                    security: wpikoChatbot.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data);
                        // Trigger custom event to initialize WooCommerce integration
                        $(document).trigger('woocommerceIntegrationLoaded');
                    } else {
                        $container.html('<div class="error">Failed to load WooCommerce Integration.</div>');
                    }
                },
                error: function() {
                    $container.html('<div class="error">Error loading WooCommerce Integration.</div>');
                }
            });
        }
    });

    // Make functions available globally
    window.wpikoChatbotWooCommerce = {
        initializeWooCommerceIntegration: initializeWooCommerceIntegration
    };
});
