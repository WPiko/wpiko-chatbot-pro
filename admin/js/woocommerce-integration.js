jQuery(document).ready(function($) {
    function initializeWooCommerceIntegration() {
        var $container = $('#woocommerce-integration-container');
        
        // Check if container exists
        if (!$container.length) {
            // Try to initialize the WooCommerce files list if the section exists
            if ($('#woocommerce-integration-section').length && $('#woocommerce-files-list').length) {
                if (typeof wpikoChatbotFileManagement !== 'undefined' && 
                    typeof wpikoChatbotFileManagement.refreshWooCommerceFileList === 'function') {
                    wpikoChatbotFileManagement.refreshWooCommerceFileList();
                } else {
                    console.log('WooCommerce files list refresh function not available');
                }
            }
            return; // Exit if main container doesn't exist
        }

        // Cache DOM elements
        var $wooCommerceIntegration = $('#woocommerce_integration_enabled');
        var $productsAutoUpdate = $('#products_auto_sync');
        var $ordersSync = $('#orders_auto_sync');
        var $syncButton = $('#sync_existing_products');
        var $downloadProductsButton = $('#download_products_json');
        var $downloadOrdersButton = $('#download_orders_json');
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
        $downloadOrdersButton.off('click');
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
            
            // Elements to hide/show
            const elementsToToggle = [
                // Product Data Fields
                $('#product-fields-toggle').closest('tr'),
                $('#product-fields-summary'),
                
                // Sync Products Manually
                $('#sync_existing_products').closest('tr'),
                
                // Product Catalog Auto-Sync
                $('#products_auto_sync').closest('tr'),
                
                // Order Data Fields
                $('#order-fields-toggle').closest('tr'),
                $('#order-fields-summary'),
                
                // Recent Orders Auto-Sync
                $('#orders_auto_sync').closest('tr'),
                
                // Download Files
                $('.download-files-option')
            ];
            
            // Show/Hide elements
            elementsToToggle.forEach(element => {
                if (element.length) {
                    element.toggle(isEnabled);
                }
            });
            
            // WooCommerce Files list should always be visible if there are files
            // So we don't toggle it here
            
            // Toggle form controls (keep existing functionality)
            $productsAutoUpdate.prop('disabled', !isEnabled);
            $ordersSync.prop('disabled', !isEnabled);
            $syncButton.prop('disabled', !isEnabled);
            $downloadProductsButton.prop('disabled', !isEnabled);
            $downloadOrdersButton.prop('disabled', !isEnabled);
            $saveProductFieldsButton.prop('disabled', !isEnabled);
            $saveOrderFieldsButton.prop('disabled', !isEnabled);
            
            // Toggle input fields
            $('.product-field-option input').prop('disabled', !isEnabled);
            $('.order-field-option input').prop('disabled', !isEnabled);
            $productFieldsToggle.toggleClass('disabled', !isEnabled);
            $orderFieldsToggle.toggleClass('disabled', !isEnabled);
            
            // Toggle Products System Instructions visibility
            $('.products-instructions-row').toggle(isEnabled);

            if (!isEnabled) {
                $productsAutoUpdate.val('disabled');
                $ordersSync.val('disabled');
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
                $(this).toggleClass('active');
                $productFieldsContent.slideToggle(300);
                updateProductFieldsSummary();
            }
        });
        
        // Order Fields Toggle Handler
        $orderFieldsToggle.on('click', function() {
            if ($wooCommerceIntegration.is(':checked')) {
                $(this).toggleClass('active');
                $orderFieldsContent.slideToggle(300);
                updateOrderFieldsSummary();
            }
        });

        // Function to update integration status display
        function updateWooCommerceIntegrationStatus(isEnabled) {
            if (isEnabled) {
                $statusDiv.html('<p><strong class="woocommerce-integration-active">Enabled:</strong> You can sync products manually or automate the synchronization of products and orders.</p>').show();
            } else {
                $statusDiv.hide();
            }
        }

        // Function to update assistant details
        function updateAssistantDetails(details) {
            if (details) {
                // Update the main instructions textarea if it exists
                if (details.instructions && $('#edit_assistant_instructions').length) {
                    $('#edit_assistant_instructions').val(details.instructions);
                }
                
                // Get system instructions from database and update specific textareas
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_system_instructions',
                        security: wpikoChatbotAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // Update the Products System Instructions textarea
                            if (response.data.products !== undefined && $('#products_system_instructions').length) {
                                $('#products_system_instructions').val(response.data.products);
                            }
                            
                            // Update other system instructions fields if needed
                            if (response.data.main !== undefined && $('#main_system_instructions').length) {
                                $('#main_system_instructions').val(response.data.main);
                            }
                            
                            if (response.data.specific !== undefined && $('#specific_system_instructions').length) {
                                $('#specific_system_instructions').val(response.data.specific);
                            }
                            
                            if (response.data.knowledge !== undefined && $('#knowledge_system_instructions').length) {
                                $('#knowledge_system_instructions').val(response.data.knowledge);
                            }
                            
                            if (response.data.orders !== undefined && $('#orders_system_instructions').length) {
                                $('#orders_system_instructions').val(response.data.orders);
                            }
                        }
                    }
                });
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
                        
                        // Update progress message
                        let statusText = '';
                        let statusClass = 'status-info';
                        
                        if (syncStatus === 'running' || syncStatus === 'scheduled') {
                            statusText = `Sync in progress: ${progress}%`;
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
            $statusDiv.html('<p><strong class="woocommerce-integration-loading">Loading...</strong></p>').show();

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
                        alert(response.data.message);

                        setTimeout(function() {
                            updateWooCommerceIntegrationStatus(newState);
                            toggleWooCommerceOptions();
                            if (typeof wpikoChatbotFileManagement !== 'undefined') {
                                wpikoChatbotFileManagement.refreshWooCommerceFileList();
                            }
                            if (response.data.assistant_details) {
                                updateAssistantDetails(response.data.assistant_details);
                            }
                        }, 1000);

                        if (!newState) {
                            $productsAutoUpdate.val('disabled');
                            $ordersSync.val('disabled').trigger('change');
                        }
                    } else {
                        alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                        $wooCommerceIntegration.prop('checked', !isEnabled);
                        $statusDiv.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('An error occurred. Please try again.');
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
                timeout: 30000 // 30 seconds timeout
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
                        alert('Products auto-update setting updated successfully.');
                        if (typeof wpikoChatbotFileManagement !== 'undefined') {
                            wpikoChatbotFileManagement.refreshWooCommerceFileList();
                        }
                    } else {
                        alert('Failed to update products auto-update setting.');
                    }
                },
                error: function() {
                    $status.remove();
                    alert('An error occurred while updating products auto-update setting.');
                }
            });
        });

        // Orders Auto-Sync Handler
        $ordersSync.on('change', function() {
            var syncOption = $(this).val();
            var $status = $('<span>').insertAfter($(this))
                .text('Loading...')
                .addClass('status-loading');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_orders_auto_sync',
                    security: wpikoChatbotAdmin.nonce,
                    sync_option: syncOption
                },
                success: function(response) {
                    $status.remove();
        if (response.success) {
            alert(response.data.message);
            
            // Update system instructions and UI
            if (response.data.assistant_details) {
                updateAssistantDetails(response.data.assistant_details);
            }
            
            // Update WooCommerce file list if needed
            if (typeof wpikoChatbotFileManagement !== 'undefined') {
                wpikoChatbotFileManagement.refreshWooCommerceFileList();
            }
            
            // Update Orders System Instructions visibility
            var ordersAutoSync = $('#orders_auto_sync').val();
            var $ordersInstructions = $('#orders_system_instructions').closest('tr');
            if (ordersAutoSync === 'disabled') {
                $ordersInstructions.hide();
            } else {
                $ordersInstructions.show();
            }
        } else {
            alert('Error: ' + response.data.message);
        }
                },
                error: function() {
                    $status.remove();
                    alert('An error occurred while updating orders sync setting.');
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

        // Download Orders JSON Handler
        $downloadOrdersButton.on('click', function() {
            var $status = $('#orders_download_status');
            $(this).prop('disabled', true);
            $status.text('Generating JSON...')
                .removeClass('status-success status-error')
                .addClass('status-info');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'download_orders_json',
                    security: wpikoChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('JSON generated successfully. Downloading...')
                            .removeClass('status-info')
                            .addClass('status-success');
                        
                        var jsonString = JSON.stringify(response.data.orders, null, 2);
                        var blob = new Blob([jsonString], {type: 'application/json'});
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = 'woocommerce_orders.json';
                        link.click();
                        
                        setTimeout(function() {
                            $status.text('Download complete.');
                            $downloadOrdersButton.prop('disabled', false);
                        }, 2000);
                    } else {
                        $status.text('Error: ' + response.data.message)
                            .removeClass('status-info')
                            .addClass('status-error');
                        $downloadOrdersButton.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.text('An error occurred. Please try again.')
                        .removeClass('status-info')
                        .addClass('status-error');
                    $downloadOrdersButton.prop('disabled', false);
                }
            });
        });

        // Product Fields Options Handler
        $saveProductFieldsButton.on('click', function() {
            var $status = $('#product_fields_status');
            $status.text('Saving field preferences...')
                .removeClass('status-success status-error')
                .addClass('status-info');

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
                        $status.text('Field preferences saved successfully.')
                            .removeClass('status-info')
                            .addClass('status-success');
                        
                        // Update summary count
                        updateProductFieldsSummary();
                            
                        setTimeout(function() {
                            $status.text('');
                        }, 3000);
                    } else {
                        $status.text('Error: ' + response.data.message)
                            .removeClass('status-info')
                            .addClass('status-error');
                    }
                },
                error: function() {
                    $status.text('An error occurred. Please try again.')
                        .removeClass('status-info')
                        .addClass('status-error');
                }
            });
        });

        // Order Fields Options Handler
        $saveOrderFieldsButton.on('click', function() {
            var $status = $('#order_fields_status');
            $status.text('Saving field preferences...')
                .removeClass('status-success status-error')
                .addClass('status-info');

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
                        $status.text('Field preferences saved successfully.')
                            .removeClass('status-info')
                            .addClass('status-success');
                        
                        // Update summary count
                        updateOrderFieldsSummary();
                            
                        setTimeout(function() {
                            $status.text('');
                        }, 3000);
                        
                        // Refresh files list
                        if (typeof wpikoChatbotFileManagement !== 'undefined') {
                            wpikoChatbotFileManagement.refreshWooCommerceFileList();
                        }
                    } else {
                        $status.text('Error: ' + response.data.message)
                            .removeClass('status-info')
                            .addClass('status-error');
                    }
                },
                error: function() {
                    $status.text('An error occurred. Please try again.')
                        .removeClass('status-info')
                        .addClass('status-error');
                }
            });
        });

        // Update checkbox count when changed
        $('.product-field-option input').on('change', function() {
            updateProductFieldsSummary();
        });

        // Update checkbox count when changed
        $('.order-field-option input').on('change', function() {
            updateOrderFieldsSummary();
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

        // Set initial Orders System Instructions visibility
        var initialOrdersAutoSync = $ordersSync.val();
        var $ordersInstructions = $('#orders_system_instructions').closest('tr');
        if (initialOrdersAutoSync === 'disabled') {
            $ordersInstructions.hide();
        } else {
            $ordersInstructions.show();
        }
        
        // Initialize tooltips if available
        if (typeof $.fn.tooltip === 'function') {
            $('.tooltip-icon').tooltip({
                position: { my: "left+15 center", at: "right center" }
            });
        }
    }

    // Function to handle Products Instructions visibility
    function handleProductsInstructions() {
        var isEnabled;
        // Use the initial state if available, otherwise check the checkbox
        if (typeof wpikoWooIntegrationEnabled !== 'undefined') {
            isEnabled = wpikoWooIntegrationEnabled;
        } else {
            isEnabled = $('#woocommerce_integration_enabled').is(':checked');
        }
        $('.products-instructions-row').toggle(isEnabled);
    }

    // Initialize on document ready
    $(document).ready(function() {
        handleProductsInstructions();
    });

    // Update visibility when integration state changes
    $(document).on('change', '#woocommerce_integration_enabled', function() {
        wpikoWooIntegrationEnabled = $(this).is(':checked');
        handleProductsInstructions();
    });

    // Initialize when modal content is loaded
    $(document).on('woocommerceIntegrationLoaded', initializeWooCommerceIntegration);
    initializeWooCommerceIntegration();

    // Make functions available globally
    window.wpikoChatbotWooCommerce = {
        initializeWooCommerceIntegration: initializeWooCommerceIntegration,
        handleProductsInstructions: handleProductsInstructions
    };
});
