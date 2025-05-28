jQuery(document).ready(function($) {

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
                        alert('License deleted successfully');
                        location.reload();
                    } else {
                        alert('Failed to delete license: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert('An error occurred while deleting the license: ' + error);
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
    
});
