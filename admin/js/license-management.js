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

    $('#wpiko-delete-license').on('click', function(e) {
        e.preventDefault();

        if (confirm('Are you sure you want to delete the license key?')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_delete_license',
                    nonce: wpiko_chatbot_license.delete_nonce
                },
                success: function(response) {
                    if (response.success) {
                        wpikoNotify('License deleted successfully. Reloading…', 'success', { dismissible: false });
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        wpikoNotify('Failed to delete license: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    wpikoNotify('An error occurred while deleting the license: ' + error, 'error');
                }
            });
        }
    });

    $('#wpiko-chatbot-manual-license-check').on('click', function(e) {
        e.preventDefault();
        var data = {
            'action': 'wpiko_chatbot_manual_license_check',
            'security': wpiko_chatbot_license.check_nonce
        };
    
        var $resultElement = $('#wpiko-chatbot-license-check-result');
        $resultElement.removeClass('wpiko-success-message wpiko-error-message').text('Checking license...').show();
    
        $.post(ajaxurl, data, function(response) {
            setTimeout(function() {
                $resultElement.removeClass('wpiko-success-message wpiko-error-message');
            
                if (response.success) {
                    $resultElement.text(response.data.message).addClass('license-success-message');
                    setTimeout(function() {
                        location.reload(); // Reload the page after a delay
                    }, 2000); // 2 seconds delay before reloading
                } else {
                    $resultElement.text(response.data.message).addClass('license-error-message');
                    if (response.data.status === 'expired') {
                        setTimeout(function() {
                            location.reload(); // Reload the page after a delay
                        }, 2000); // 2 seconds delay before reloading
                    }
                }
            }, 500); // 0.5 seconds delay before showing the result
        });
    });

    // Connection diagnostic test
    $('#wpiko-test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $results = $('#wpiko-connection-results');
        var $loading = $('#wpiko-connection-loading');
        var $output = $('#wpiko-connection-output');
        
        $button.prop('disabled', true);
        $results.show();
        $loading.show();
        $output.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_test_connection',
                nonce: wpiko_chatbot_license.test_connection_nonce
            },
            timeout: 60000, // 60 second timeout
            success: function(response) {
                $loading.hide();
                $button.prop('disabled', false);
                
                if (response.success) {
                    var data = response.data;
                    var html = '';
                    
                    // Summary box
                    var summaryClass = data.success ? 'notice-success' : 'notice-error';
                    html += '<div class="notice ' + summaryClass + ' wpiko-connection-summary">';
                    html += '<strong>' + (data.success ? '✓ Connection OK' : '✗ Connection Issues Detected') + '</strong><br>';
                    html += data.summary;
                    html += '</div>';
                    
                    // Test results table
                    html += '<table class="widefat wpiko-connection-tests">';
                    html += '<thead><tr><th>Test</th><th>Status</th><th>Details</th><th>Time</th></tr></thead>';
                    html += '<tbody>';
                    
                    for (var testKey in data.tests) {
                        var test = data.tests[testKey];
                        var statusIcon = test.success ? '✓' : '✗';
                        var statusClass = test.success ? 'wpiko-connection-status-ok' : 'wpiko-connection-status-error';
                        
                        html += '<tr>';
                        html += '<td><strong>' + test.name + '</strong></td>';
                        html += '<td class="' + statusClass + '">' + statusIcon + '</td>';
                        html += '<td>' + test.message + '</td>';
                        html += '<td>' + test.time_ms + ' ms</td>';
                        html += '</tr>';
                    }
                    
                    html += '</tbody></table>';
                    
                    // Server info (collapsible)
                    html += '<details class="wpiko-connection-server-info">';
                    html += '<summary>Server Information</summary>';
                    html += '<table class="widefat wpiko-connection-server-table">';
                    html += '<tbody>';
                    html += '<tr><td>PHP Version</td><td>' + data.server_info.php_version + '</td></tr>';
                    html += '<tr><td>WordPress Version</td><td>' + data.server_info.wp_version + '</td></tr>';
                    html += '<tr><td>Server Software</td><td>' + data.server_info.server_software + '</td></tr>';
                    html += '<tr><td>cURL Available</td><td>' + (data.server_info.curl_available ? 'Yes' : 'No') + '</td></tr>';
                    html += '<tr><td>OpenSSL Available</td><td>' + (data.server_info.openssl_available ? 'Yes' : 'No') + '</td></tr>';
                    html += '</tbody></table>';
                    html += '</details>';
                    
                    // Help text if blocked
                    if (!data.success) {
                        html += '<div class="notice notice-warning wpiko-connection-help">';
                        html += '<strong>What to do next:</strong><br>';
                        html += '1. Contact your hosting provider and share these results<br>';
                        html += '2. Ask them to whitelist requests to <code>wpiko.com</code><br>';
                        html += '3. If using ModSecurity or a WAF, ask them to add an exception for the licensing API<br>';
                        html += '4. Try activating your license on a temporary site at <a href="https://tastewp.com" target="_blank">TasteWP</a> to confirm the issue is server-specific';
                        html += '</div>';
                    }
                    
                    $output.html(html);
                } else {
                    $output.html('<div class="notice notice-error wpiko-connection-error">Error: ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $loading.hide();
                $button.prop('disabled', false);
                $output.html('<div class="notice notice-error wpiko-connection-error">Request failed: ' + error + '</div>');
            }
        });
    });
    
});
