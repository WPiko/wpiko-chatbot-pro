/**
 * Email Capture Functionality for WPiko Chatbot Pro
 * 
 * This file contains all the email capture related JavaScript functionality
 * that was moved from the main wpiko-chatbot plugin to the pro version.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Ensure this runs after the main plugin has loaded
    setTimeout(function() {
        initEmailCapture();
    }, 10);
});

function initEmailCapture() {
    // Email capture functionality
    const emailCaptureOverlay = document.getElementById('email-capture-overlay-container');
    const userEmailInput = document.getElementById('user-email');
    const submitEmailButton = document.getElementById('submit-email');
    
    const emailSection = document.getElementById('email-section');
    const emailPreview = document.getElementById('email-preview');
    const emailDetails = document.getElementById('email-details');
    const currentEmailDisplay = document.getElementById('current-email-display');
    const changeEmailOption = document.getElementById('change-email');

    function updateChangeEmailVisibility() {
        const emailCaptureEnabled = wpikoChatbot.enable_email_capture;
        const storedEmail = localStorage.getItem('wpiko_chatbot_user_email');
        
        if (changeEmailOption) {
            if (emailCaptureEnabled && storedEmail) {
                changeEmailOption.style.display = 'block';
            } else {
                changeEmailOption.style.display = 'none';
            }
        }
    }

    function updateEmailDisplay() {
        if (wpikoChatbot.is_user_logged_in) {
            // Clear any stored email/name when user is logged in
            localStorage.removeItem('wpiko_chatbot_user_email');
            localStorage.removeItem('wpiko_chatbot_user_name');
            if (emailCaptureOverlay) emailCaptureOverlay.style.display = 'none';
            if (emailSection) emailSection.style.display = 'none';
            return;
        }

        const storedEmail = localStorage.getItem('wpiko_chatbot_user_email');
        if (storedEmail) {
            if (currentEmailDisplay) currentEmailDisplay.textContent = storedEmail;
            if (emailPreview) emailPreview.textContent = 'Email ▼';
            if (emailSection) emailSection.style.display = 'block';
            if (emailCaptureOverlay) emailCaptureOverlay.style.display = 'none';
            if (emailDetails) emailDetails.style.display = 'none'; // Hide details by default
        } else if (wpikoChatbot.enable_email_capture === '1' && wpikoChatbot.is_license_active) {
            showEmailCaptureOverlay();
            if (emailSection) emailSection.style.display = 'none';
        }
        updateChangeEmailVisibility();
    }

    // Initialize email display
    updateEmailDisplay();
    
    if (emailSection) {
        emailSection.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent closing the dropdown
            if (emailDetails) {
                emailDetails.style.display = emailDetails.style.display === 'none' ? 'block' : 'none';
                if (emailPreview) {
                    emailPreview.textContent = emailDetails.style.display === 'none' ? 'Email ▼' : 'Email ▲';
                }
            }
        });
    }

    if (submitEmailButton) {
        submitEmailButton.addEventListener('click', function() {
            const name = document.getElementById('user-name').value.trim();
            const email = userEmailInput.value.trim();
        
            if (!name) {
                alert('Please enter your name.');
                return;
            }
        
            if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                localStorage.setItem('wpiko_chatbot_user_name', name);
                localStorage.setItem('wpiko_chatbot_user_email', email);
                updateEmailDisplay();
                if (emailCaptureOverlay) {
                    emailCaptureOverlay.style.display = 'none';
                }
                
                // Reload the chatbot interface to refresh with the new user data
                setTimeout(() => {
                    location.reload();
                }, 100);
            } else {
                alert('Please enter a valid email address.');
            }
        });
    }

    // Function to get user email
    function getUserEmail() {
        // Always prioritize logged-in user details
        if (wpikoChatbot.is_user_logged_in) {
            // Clear any stored email when user is logged in
            localStorage.removeItem('wpiko_chatbot_user_email');
            return wpikoChatbot.user_email;
        }
        // Return stored email if email capture is enabled
        return (wpikoChatbot.enable_email_capture === '1') ? localStorage.getItem('wpiko_chatbot_user_email') || '' : '';
    }

    // Function to get user name
    function getUserName() {
        // Always prioritize logged-in user details
        if (wpikoChatbot.is_user_logged_in) {
            // Clear any stored name when user is logged in
            localStorage.removeItem('wpiko_chatbot_user_name');
            return wpikoChatbot.user_name || '';
        }
        // Return stored name if email capture is enabled
        return (wpikoChatbot.enable_email_capture === '1') ? localStorage.getItem('wpiko_chatbot_user_name') || '' : '';
    }
    
    // Make getUserEmail and getUserName available globally for the contact form
    // These will override any fallback functions from the main plugin
    window.getUserEmail = getUserEmail;
    window.getUserName = getUserName;

    // Also ensure these functions are available immediately for any existing code
    if (typeof window.wpikoEmailCapture === 'undefined') {
        window.wpikoEmailCapture = {};
    }
    window.wpikoEmailCapture.getUserEmail = getUserEmail;
    window.wpikoEmailCapture.getUserName = getUserName;

    // Change email option handler
    if (changeEmailOption) {
        changeEmailOption.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent the dropdown from closing immediately
            changeEmail();
        });
    }

    function changeEmail() {
        const currentEmail = localStorage.getItem('wpiko_chatbot_user_email');
        const currentName = localStorage.getItem('wpiko_chatbot_user_name');
        const menuDropdown = document.getElementById('chatbot-menu-dropdown');
        const emailDetails = document.getElementById('email-details');
        
        if (currentEmail) {
            if (confirm(`Your current details are:\nName: ${currentName}\nEmail: ${currentEmail}\n\nDo you want to change them?`)) {
                localStorage.removeItem('wpiko_chatbot_user_email');
                localStorage.removeItem('wpiko_chatbot_user_name');
                if (userEmailInput) {
                    userEmailInput.value = '';
                }
                if (document.getElementById('user-name')) {
                    document.getElementById('user-name').value = '';
                }
                if (emailCaptureOverlay) {
                    emailCaptureOverlay.style.display = 'block';
                }
                setTimeout(() => {
                    location.reload();
                }, 100);
            }
        } else {
            if (emailCaptureOverlay) {
                emailCaptureOverlay.style.display = 'block';
            }
            setTimeout(() => {
                location.reload();
           }, 100);
        }
        if (menuDropdown) {
            menuDropdown.style.display = 'none';
        }
        if (emailDetails) {
            emailDetails.style.display = 'none';
        }
        updateEmailDisplay();
    }
    
    function showEmailCaptureOverlay() {
        if (emailCaptureOverlay) {
            emailCaptureOverlay.style.display = 'block';
            // Force a reflow to ensure the overlay is displayed immediately
            emailCaptureOverlay.offsetHeight;
        }
    }

    // Make functions available globally if needed by other scripts
    window.wpikoEmailCapture = {
        updateEmailDisplay: updateEmailDisplay,
        getUserEmail: getUserEmail,
        getUserName: getUserName,
        changeEmail: changeEmail,
        showEmailCaptureOverlay: showEmailCaptureOverlay
    };

    // Global validation function that the main plugin can call
    window.wpikoProValidateEmailBeforeSend = function() {
        // Check if email capture is enabled
        const emailCaptureEnabled = wpikoChatbot.enable_email_capture === '1';
        const userEmail = getUserEmail();
        
        // Only require email if email capture is enabled and user is not logged in
        if (emailCaptureEnabled && !wpikoChatbot.is_user_logged_in && !userEmail) {
            alert('Please provide your email before sending a message.');
            return false;
        }
        
        return true;
    };

    // Debug: Log that email capture is loaded
    if (typeof wpikoChatbot !== 'undefined' && wpikoChatbot.debug) {
        console.log('WPiko Chatbot Pro: Email capture functionality loaded');
    }
}
