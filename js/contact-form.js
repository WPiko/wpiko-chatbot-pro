// Contact form functionality
document.addEventListener('DOMContentLoaded', function() {
    // Load reCAPTCHA script if enabled
    if (typeof wpikoChatbot !== 'undefined' && wpikoChatbot.enable_recaptcha === '1' && wpikoChatbot.recaptcha_site_key) {
        const recaptchaScript = document.createElement('script');
        recaptchaScript.src = 'https://www.google.com/recaptcha/api.js?render=' + wpikoChatbot.recaptcha_site_key;
        document.head.appendChild(recaptchaScript);
        
        // Hide reCAPTCHA badge if option is enabled
        if (wpikoChatbot.hide_recaptcha_badge === '1') {
            const style = document.createElement('style');
            style.textContent = '.grecaptcha-badge { visibility: hidden !important; }';
            document.head.appendChild(style);
        }
    }
    
    // Global function to open the chatbot and show contact form
    window.wpikoOpenChatbotWithContactForm = function() {
        const floatingContainer = document.getElementById('wpiko-chatbot-floating-container');
        const floatingWrapper = document.getElementById('wpiko-chatbot-floating-wrapper');
        
        // First check if this is a floating chatbot and open it if it's not already open
        if (floatingContainer && floatingWrapper) {
            if (floatingContainer.style.display === 'none') {
                floatingContainer.style.display = 'block';
                floatingWrapper.classList.add('open');
                sessionStorage.setItem('wpiko_chatbot_is_open', 'true');
                // Disable main scroll when chatbot is open
                document.documentElement.classList.add('chatbot-open');
                document.body.classList.add('chatbot-open');
            }
        }
        
        // Hide the menu dropdown if it's open (works for both floating and shortcode chatbots)
        const menuDropdown = document.getElementById('chatbot-menu-dropdown');
        if (menuDropdown) {
            menuDropdown.style.display = 'none';
        }
        
        // Now show the contact form (for both floating and shortcode chatbots)
        showContactForm();
        
        // Scroll to see the form (works for both floating and shortcode chatbots)
        const chatbotMessages = document.getElementById('chatbot-messages');
        if (chatbotMessages) {
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }
    };
    
    // Check for URL parameter to open contact form
    function checkUrlForContactFormParameter() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('wpiko_contact') && urlParams.get('wpiko_contact') === 'open') {
            // Check if license is active and contact form feature is enabled
            if (typeof wpikoChatbot !== 'undefined' && wpikoChatbot.is_license_active && wpikoChatbot.enable_contact_form === '1') {
                // Make sure dropdown settings are available
                wpikoChatbot.enable_dropdown = wpikoChatbot.enable_dropdown || '0';
                wpikoChatbot.dropdown_options = wpikoChatbot.dropdown_options || '';
                // Use a small timeout to ensure the page is fully loaded
                setTimeout(function() {
                    window.wpikoOpenChatbotWithContactForm();
                }, 500);
            } else {
                console.warn('WPiko Chatbot contact form feature is disabled. Enable it in the admin settings.');
            }
        }
    }
    
    // Run the URL parameter check when the page loads
    checkUrlForContactFormParameter();
    
    // Get the elements
    const chatbotContactFormOption = document.getElementById('wpiko-chatbot-contact-form');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const menuDropdown = document.getElementById('chatbot-menu-dropdown');
    
    // Only show contact form option if the feature is enabled and the element exists in the chatbot menu
    if (chatbotContactFormOption) {
        // Check if license is active and contact form feature is enabled
        if (typeof wpikoChatbot !== 'undefined' && wpikoChatbot.is_license_active && wpikoChatbot.enable_contact_form === '1') {
            chatbotContactFormOption.style.display = 'block';
            
            // Add click event listener to the contact form option
            chatbotContactFormOption.addEventListener('click', function(e) {
                e.preventDefault();
                if (menuDropdown) {
                    menuDropdown.style.display = 'none';
                }
                showContactForm();
            });
        } else {
            // Hide the contact form option if feature is disabled
            chatbotContactFormOption.style.display = 'none';
        }
    }
    
    // Look for any "Contact Us" links with the special data attribute
    document.querySelectorAll('[data-wpiko-open-contact-form="true"]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if license is active and contact form feature is enabled
            if (typeof wpikoChatbot !== 'undefined' && wpikoChatbot.is_license_active && wpikoChatbot.enable_contact_form === '1') {
                window.wpikoOpenChatbotWithContactForm();
            } else {
                // Display premium feature notice in chat
                showPremiumFeatureNotice();
            }
        });
    });
    
    // Function to show contact form
    function showContactForm() {
        // Check if contact form already exists
        const existingForm = document.getElementById('contact-form-container');
        if (existingForm) {
            // If form exists, just scroll to it
            existingForm.scrollIntoView({ behavior: 'smooth' });
            return;
        }

        // Create a container for the contact form
        const contactFormContainer = document.createElement('div');
        contactFormContainer.className = 'message-container';
        contactFormContainer.id = 'contact-form-container';
        
        // Get user information - use the global functions if available
        let userName = '';
        let userEmail = '';
        
        // Try to get user information using the global helper functions
        if (typeof window.getUserName === 'function' && typeof window.getUserEmail === 'function') {
            userName = window.getUserName();
            userEmail = window.getUserEmail();
        } else {
            // Fall back to directly accessing localStorage
            userName = localStorage.getItem('wpiko_chatbot_user_name') || '';
            userEmail = localStorage.getItem('wpiko_chatbot_user_email') || '';
        }
        
        // Create the contact form HTML
        let formHTML = `
            <div class="message-wrapper bot-message-wrapper">
                <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                <div class="bot-message contact-form-message">
                    <h3>Contact Form</h3>
                    <p>Please fill out the form below and we'll get back to you as soon as possible.</p>
                    <form id="wpiko-contact-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="contact-name">Name</label>
                            <input type="text" id="contact-name" name="name" value="${userName}" required>
                        </div>
                        <div class="form-group">
                            <label for="contact-email">Email</label>
                            <input type="email" id="contact-email" name="email" value="${userEmail}" required>
                        </div>`;

        // Add dropdown if enabled and options exist
        if (wpikoChatbot.enable_dropdown === '1' && wpikoChatbot.dropdown_options) {
            const options = wpikoChatbot.dropdown_options.split('\n').filter(option => option.trim());
            if (options.length > 0) {
                formHTML += `
                        <div class="form-group">
                            <label for="contact-category">Category</label>
                            <select id="contact-category" name="category" required>
                                <option value="">Select a category</option>
                                ${options.map(option => `<option value="${option.trim()}">${option.trim()}</option>`).join('')}
                            </select>
                        </div>`;
            }
        }

        formHTML += `
                        <div class="form-group">
                            <label for="contact-message">Message</label>
                            <textarea id="contact-message" name="message" rows="4" required></textarea>
                        </div>`;

        // Add honeypot field (hidden from users, bots will fill it)
        formHTML += `
                        <div style="position: absolute; left: -9999px; top: -9999px;">
                            <label for="website_url">Website (leave blank)</label>
                            <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
                        </div>`;
        
        // Add timestamp field to detect instant submissions
        formHTML += `
                        <input type="hidden" name="form_timestamp" value="${Math.floor(Date.now() / 1000)}">`;

        // Add file uploads if enabled
        if (wpikoChatbot.enable_attachments === '1') {
            formHTML += `
                        <div class="form-group">
                            <label>Attach Images (up to 3)</label>
                            <div class="attachment-inputs">
                                <div>
                                    <input type="file" id="contact-attachment-1" name="attachment-1" accept="image/*">
                                </div>
                                <div>
                                    <input type="file" id="contact-attachment-2" name="attachment-2" accept="image/*">
                                </div>
                                <div>
                                    <input type="file" id="contact-attachment-3" name="attachment-3" accept="image/*">
                                </div>
                            </div>
                        </div>`;
        }

        // Add reCAPTCHA if enabled
        if (wpikoChatbot.enable_recaptcha === '1' && wpikoChatbot.recaptcha_site_key) {
            formHTML += `
                        <div class="form-group recaptcha-container">
                            <div id="recaptcha-wrapper" class="g-recaptcha" data-sitekey="${wpikoChatbot.recaptcha_site_key}"></div>
                            <div class="recaptcha-note">This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" target="_blank">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank">Terms of Service</a> apply.</div>
                        </div>`;
        }

        formHTML += `
                        <div class="form-actions">
                            <button type="button" id="contact-form-cancel">Cancel</button>
                            <button type="submit" id="contact-form-submit">Send</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        contactFormContainer.innerHTML = formHTML;
    
        // Add the form to the chatbot messages container
        if (chatbotMessages) {
            chatbotMessages.appendChild(contactFormContainer);
            
            // Scroll to the form
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
            
            // Add event listeners to form buttons
            const cancelButton = document.getElementById('contact-form-cancel');
            const submitButton = document.getElementById('contact-form-submit');
            const contactForm = document.getElementById('wpiko-contact-form');
            
            if (cancelButton) {
                cancelButton.addEventListener('click', function() {
                    // Remove the form from the chat
                    contactFormContainer.remove();
                });
            }
            
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitContactForm();
                });
            }
        }
    }
    
    // Function to submit the contact form
    function submitContactForm() {
        const contactForm = document.getElementById('wpiko-contact-form');
        if (!contactForm) {
            console.error('Contact form not found');
            return;
        }
        
        // Check form validity
        if (!contactForm.checkValidity()) {
            contactForm.reportValidity();
            return;
        }
        
        const nameInput = document.getElementById('contact-name');
        const emailInput = document.getElementById('contact-email');
        const messageInput = document.getElementById('contact-message');
        const contactFormContainer = document.getElementById('contact-form-container');
        
        if (!nameInput || !emailInput || !messageInput || !contactFormContainer) {
            console.error('Form elements not found');
            return;
        }
        
        // Basic validation
        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const message = messageInput.value.trim();
        
        if (!name || !email || !message) {
            alert('Please fill out all required fields.');
            return;
        }
        
        // Store user information in localStorage
        localStorage.setItem('wpiko_chatbot_user_name', name);
        localStorage.setItem('wpiko_chatbot_user_email', email);
        
        // Display loading message
        contactFormContainer.innerHTML = `
            <div class="message-wrapper bot-message-wrapper">
                <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                <div class="bot-message">
                    <div class="loading-dots">
                        <div class="dot"></div>
                        <div class="dot"></div>
                        <div class="dot"></div>
                    </div>
                </div>
            </div>
        `;
        
        // Handle reCAPTCHA if enabled
        if (wpikoChatbot.enable_recaptcha === '1' && wpikoChatbot.recaptcha_site_key && typeof grecaptcha !== 'undefined') {
            grecaptcha.ready(function() {
                grecaptcha.execute(wpikoChatbot.recaptcha_site_key, {action: 'contact_form'})
                .then(function(token) {
                    // Add the token to a hidden input
                    const recaptchaInput = document.createElement('input');
                    recaptchaInput.type = 'hidden';
                    recaptchaInput.name = 'recaptcha_response';
                    recaptchaInput.value = token;
                    contactForm.appendChild(recaptchaInput);
                    
                    // Submit the form
                    submitFormWithAjax(contactForm, contactFormContainer);
                })
                .catch(function(error) {
                    console.error('reCAPTCHA error:', error);
                    showContactFormError('Failed to verify reCAPTCHA. Please try again.');
                });
            });
        } else {
            // Submit the form without reCAPTCHA
            submitFormWithAjax(contactForm, contactFormContainer);
        }
    }
    
    // Function to submit the form using AJAX
    function submitFormWithAjax(form, container) {
        // Add action and security fields
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'wpiko_chatbot_contact_form';
        form.appendChild(actionInput);
        
        const securityInput = document.createElement('input');
        securityInput.type = 'hidden';
        securityInput.name = 'security';
        securityInput.value = wpikoChatbot.nonce;
        form.appendChild(securityInput);
        
        // Add thread_id to form data to link contact form activity with the conversation thread
        const threadId = sessionStorage.getItem('wpiko_chatbot_thread_id') || null;
        if (threadId) {
            const threadInput = document.createElement('input');
            threadInput.type = 'hidden';
            threadInput.name = 'thread_id';
            threadInput.value = threadId;
            form.appendChild(threadInput);
        }
        
        // Create a FormData object directly from the form element
        const formData = new FormData(form);
        
        // Submit using fetch API
        fetch(wpikoChatbot.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                // Show success message
                container.innerHTML = `
                    <div class="message-wrapper bot-message-wrapper">
                        <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                        <div class="bot-message">
                            <p>${response.data.message}</p>
                        </div>
                    </div>
                `;
            } else {
                // Show error message
                showContactFormError(response.data ? response.data.message : 'Failed to send your message. Please try again later.');
            }
            
            // Scroll to see the response
            const chatbotMessages = document.getElementById('chatbot-messages');
            if (chatbotMessages) {
                chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
            }
        })
        .catch(error => {
            console.error('Error submitting form:', error);
            showContactFormError('Failed to send your message. Please try again later.');
        });
    }
    
    // Function to display error message for the contact form
    function showContactFormError(errorMessage) {
        const contactFormContainer = document.getElementById('contact-form-container');
        if (contactFormContainer) {
            contactFormContainer.innerHTML = `
                <div class="message-wrapper bot-message-wrapper">
                    <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                    <div class="bot-message">
                        <p>Error: ${errorMessage}</p>
                        <button id="retry-contact-form" class="button">Try Again</button>
                    </div>
                </div>
            `;
            
            // Add event listener to retry button
            const retryButton = document.getElementById('retry-contact-form');
            if (retryButton) {
                retryButton.addEventListener('click', function() {
                    contactFormContainer.remove();
                    showContactForm();
                });
            }
            
            // Scroll to see the error
            const chatbotMessages = document.getElementById('chatbot-messages');
            if (chatbotMessages) {
                chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
            }
        }
    }
});
