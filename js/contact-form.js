// Contact form functionality
document.addEventListener('DOMContentLoaded', function () {
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
    window.wpikoOpenChatbotWithContactForm = function () {
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
                setTimeout(function () {
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
            chatbotContactFormOption.style.display = 'flex';

            // Add click event listener to the contact form option
            chatbotContactFormOption.addEventListener('click', function (e) {
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
    document.querySelectorAll('[data-wpiko-open-contact-form="true"]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            // Check if license is active and contact form feature is enabled
            if (typeof wpikoChatbot !== 'undefined' && wpikoChatbot.is_license_active && wpikoChatbot.enable_contact_form === '1') {
                window.wpikoOpenChatbotWithContactForm();
            } else {
                // Display premium feature notice in chat
                const chatbotMessages = document.getElementById('chatbot-messages');
                if (chatbotMessages) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'message-container';
                    messageDiv.innerHTML = `
                        <div class="message-wrapper bot-message-wrapper">
                            <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                            <div class="bot-message">
                                <p>${wpikoChatbot.errors.feature_restricted}</p>
                            </div>
                        </div>`;
                    chatbotMessages.appendChild(messageDiv);
                    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

                    // Ensure chat is open
                    const floatingContainer = document.getElementById('wpiko-chatbot-floating-container');
                    if (floatingContainer && floatingContainer.style.display === 'none') {
                        floatingContainer.style.display = 'block';
                        const floatingWrapper = document.getElementById('wpiko-chatbot-floating-wrapper');
                        if (floatingWrapper) floatingWrapper.classList.add('open');
                    }
                } else {
                    alert(wpikoChatbot.errors.feature_restricted);
                }
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
        // Get text defaults if not available
        const text = wpikoChatbot.contact_form_text || {
            title: 'Contact Form',
            intro: "Please fill out the form below and we'll get back to you as soon as possible.",
            name_label: 'Name',
            email_label: 'Email',
            category_label: 'Category',
            message_label: 'Message',
            cancel_btn: 'Cancel',
            send_btn: 'Send',
            attachment_label: 'Attachments <span style="font-size:12px; font-weight:400; opacity:0.7;">(Max 3MB each)</span>',
            recaptcha_html: 'This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" target="_blank">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank">Terms of Service</a> apply.'
        };

        let formHTML = `
            <div class="message-wrapper bot-message-wrapper">
                <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                <div class="bot-message contact-form-message">
                    <h3>${text.title}</h3>
                    <p>${text.intro}</p>
                    <form id="wpiko-contact-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="contact-name" style="display:none;">${text.name_label}</label>
                            <input type="text" id="contact-name" name="name" value="${userName}" placeholder="${text.name_label}" required>
                        </div>
                        <div class="form-group">
                            <label for="contact-email" style="display:none;">${text.email_label}</label>
                            <input type="email" id="contact-email" name="email" value="${userEmail}" placeholder="${text.email_label}" required>
                        </div>`;

        // Add dropdown if enabled and options exist
        if (wpikoChatbot.enable_dropdown === '1' && wpikoChatbot.dropdown_options) {
            const options = wpikoChatbot.dropdown_options.split('\n').filter(option => option.trim());
            if (options.length > 0) {
                formHTML += `
                        <div class="form-group">
                            <label for="contact-category" style="display:none;">${text.category_label}</label>
                            <select id="contact-category" name="category" required>
                                <option value="">${text.category_label}</option>
                                ${options.map(option => `<option value="${option.trim()}">${option.trim()}</option>`).join('')}
                            </select>
                        </div>`;
            }
        }

        formHTML += `
                        <div class="form-group">
                            <label for="contact-message" style="display:none;">${text.message_label}</label>
                            <textarea id="contact-message" name="message" rows="4" placeholder="${text.message_label}" required></textarea>
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
                        <div class="form-group attachment-group">
                            <!-- Hidden inputs -->
                            <input type="file" id="contact-attachment-1" name="attachment-1" accept="image/*" class="wpiko-file-input" style="display:none">
                            <input type="file" id="contact-attachment-2" name="attachment-2" accept="image/*" class="wpiko-file-input" style="display:none">
                            <input type="file" id="contact-attachment-3" name="attachment-3" accept="image/*" class="wpiko-file-input" style="display:none">
                            
                            <label style="display:block; margin-bottom:5px; font-size:12px; font-weight:500; color:var(--bot-text-color);">${text.attachment_label}</label>
                            
                            <div class="attachment-slots">
                                <div class="attachment-slot" data-target="contact-attachment-1">
                                    <div class="slot-placeholder">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 5V19" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M5 12H19" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="slot-preview" style="display:none;"></div>
                                    <button type="button" class="remove-attachment" style="display:none;">&times;</button>
                                </div>
                                
                                <div class="attachment-slot" data-target="contact-attachment-2">
                                    <div class="slot-placeholder">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 5V19" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M5 12H19" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="slot-preview" style="display:none;"></div>
                                    <button type="button" class="remove-attachment" style="display:none;">&times;</button>
                                </div>
                                
                                <div class="attachment-slot" data-target="contact-attachment-3">
                                    <div class="slot-placeholder">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 5V19" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M5 12H19" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="slot-preview" style="display:none;"></div>
                                    <button type="button" class="remove-attachment" style="display:none;">&times;</button>
                                </div>
                            </div>
                            <!-- <div class="attachment-label">Optional, max 3MB each</div> -->
                        </div>`;
        }

        // Add reCAPTCHA if enabled
        if (wpikoChatbot.enable_recaptcha === '1' && wpikoChatbot.recaptcha_site_key) {
            formHTML += `
                        <div class="form-group recaptcha-container">
                            <div id="recaptcha-wrapper" class="g-recaptcha" data-sitekey="${wpikoChatbot.recaptcha_site_key}"></div>
                            <div class="recaptcha-note">${text.recaptcha_html}</div>
                        </div>`;
        }

        formHTML += `
                        <div class="form-actions">
                            <button type="button" id="contact-form-cancel">${text.cancel_btn}</button>
                            <button type="submit" id="contact-form-submit">${text.send_btn}</button>
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
                cancelButton.addEventListener('click', function () {
                    // Remove the form from the chat
                    contactFormContainer.remove();
                });
            }

            if (contactForm) {
                contactForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    submitContactForm();
                });
            }

            // Initialize attachment handlers
            initAttachmentHandlers(contactFormContainer);
        }
    }

    function initAttachmentHandlers(container) {
        if (typeof wpikoChatbot === 'undefined' || wpikoChatbot.enable_attachments !== '1') return;

        const slots = container.querySelectorAll('.attachment-slot');
        slots.forEach(slot => {
            const inputId = slot.dataset.target;
            const input = document.getElementById(inputId);
            const removeBtn = slot.querySelector('.remove-attachment');
            const preview = slot.querySelector('.slot-preview');
            const placeholder = slot.querySelector('.slot-placeholder');

            if (!input) return;

            // Click on slot triggers input (if not clicking remove)
            slot.addEventListener('click', (e) => {
                if (e.target !== removeBtn && !slot.classList.contains('has-file')) {
                    input.click();
                }
            });

            // Input change
            input.addEventListener('change', (e) => {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        preview.style.backgroundImage = `url(${e.target.result})`;
                        preview.style.display = 'block';
                        placeholder.style.display = 'none';
                        removeBtn.style.display = 'flex';
                        slot.classList.add('has-file');
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            });

            // Remove button
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // prevent triggering click on slot
                input.value = ''; // clear input
                preview.style.backgroundImage = '';
                preview.style.display = 'none';
                placeholder.style.display = 'block';
                removeBtn.style.display = 'none';
                slot.classList.remove('has-file');
            });
        });
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
            alert(wpikoChatbot.errors.validation_error);
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
            grecaptcha.ready(function () {
                grecaptcha.execute(wpikoChatbot.recaptcha_site_key, { action: 'contact_form' })
                    .then(function (token) {
                        // Add the token to a hidden input
                        const recaptchaInput = document.createElement('input');
                        recaptchaInput.type = 'hidden';
                        recaptchaInput.name = 'recaptcha_response';
                        recaptchaInput.value = token;
                        contactForm.appendChild(recaptchaInput);

                        // Submit the form
                        submitFormWithAjax(contactForm, contactFormContainer);
                    })
                    .catch(function (error) {
                        console.error('reCAPTCHA error:', error);
                        showContactFormError(wpikoChatbot.errors.general_error);
                    });
            });
        } else {
            // Submit the form without reCAPTCHA
            submitFormWithAjax(contactForm, contactFormContainer);
        }
    }

    // Function to submit the form using AJAX
    function submitFormWithAjax(form, container, isRetry) {
        isRetry = isRetry || false;

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

        // Create AbortController for timeout handling
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 second timeout for form submission

        // Submit using fetch API with timeout
        fetch(wpikoChatbot.ajax_url, {
            method: 'POST',
            body: formData,
            signal: controller.signal
        })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    // Handle HTTP errors
                    if (response.status === 429) {
                        throw new Error(wpikoChatbot.errors.server_busy);
                    } else if (response.status >= 500) {
                        throw new Error(wpikoChatbot.errors.server_busy);
                    } else if (response.status === 403) {
                        // Check if this is first attempt - we can retry with fresh nonce
                        if (!isRetry) {
                            // Signal to the catch handler to attempt nonce refresh
                            const error = new Error('nonce_expired');
                            error.isNonceError = true;
                            throw error;
                        }
                        throw new Error(wpikoChatbot.errors.auth_failed);
                    }
                    throw new Error(wpikoChatbot.errors.general_error + ' (Status: ' + response.status + ')');
                }
                return response.json();
            })
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
                    let errorMsg = response.data ? response.data.message : wpikoChatbot.errors.general_error;

                    // Start of File Upload Error Mapping
                    if (errorMsg && (
                        errorMsg.includes('Invalid file type') ||
                        errorMsg.includes('too large') ||
                        errorMsg.includes('Failed to upload file')
                    )) {
                        errorMsg = wpikoChatbot.errors.upload_error;
                    }
                    // End of File Upload Error Mapping

                    showContactFormError(errorMsg);
                }

                // Scroll to see the response
                const chatbotMessages = document.getElementById('chatbot-messages');
                if (chatbotMessages) {
                    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);

                // Check if this is a nonce expiration error and we haven't retried yet
                if (error.isNonceError && !isRetry) {
                    console.log('WPiko Chatbot: Contact form nonce may be expired, attempting to refresh...');

                    // Try to refresh the nonce and retry
                    refreshContactFormNonce()
                        .then(function () {
                            console.log('WPiko Chatbot: Retrying contact form with new nonce...');
                            // Remove the old hidden fields before retrying
                            const oldAction = form.querySelector('input[name="action"]');
                            const oldSecurity = form.querySelector('input[name="security"]');
                            const oldThread = form.querySelector('input[name="thread_id"]');
                            if (oldAction) oldAction.remove();
                            if (oldSecurity) oldSecurity.remove();
                            if (oldThread) oldThread.remove();

                            submitFormWithAjax(form, container, true);
                        })
                        .catch(function () {
                            showContactFormError(wpikoChatbot.errors.auth_failed);
                        });
                    return;
                }

                console.error('Error submitting form:', error);

                // Provide specific error messages based on error type
                let errorMessage = wpikoChatbot.errors.general_error;

                if (error.name === 'AbortError') {
                    errorMessage = wpikoChatbot.errors.connection_issue;
                } else if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                    errorMessage = wpikoChatbot.errors.connection_issue;
                } else if (error.message && error.message !== 'nonce_expired') {
                    errorMessage = error.message;
                }

                showContactFormError(errorMessage);
            });
    }

    /**
     * Refresh the nonce for contact form submissions
     * @returns {Promise} Resolves with the new nonce, or rejects on error
     */
    function refreshContactFormNonce() {
        return new Promise(function (resolve, reject) {
            jQuery.ajax({
                url: wpikoChatbot.ajax_url,
                type: 'post',
                timeout: 10000,
                data: {
                    action: 'wpiko_chatbot_refresh_nonce'
                },
                success: function (response) {
                    if (response.success && response.data && response.data.nonce) {
                        // Update the global nonce
                        wpikoChatbot.nonce = response.data.nonce;
                        // Update contact form nonce if available
                        if (response.data.contact_form_nonce) {
                            wpikoChatbot.contact_form_nonce = response.data.contact_form_nonce;
                        }
                        console.log('WPiko Chatbot: Contact form nonce refreshed successfully');
                        resolve(response.data.nonce);
                    } else {
                        reject(new Error('Invalid nonce refresh response'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('WPiko Chatbot: Failed to refresh contact form nonce:', error);
                    reject(error);
                }
            });
        });
    }

    // Function to display error message for the contact form
    function showContactFormError(errorMessage) {
        const contactFormContainer = document.getElementById('contact-form-container');
        if (contactFormContainer) {
            // Get customizable text or use default
            const text = wpikoChatbot.contact_form_text || {};
            const tryAgainText = text.try_again_btn || 'Try Again';

            contactFormContainer.innerHTML = `
                <div class="message-wrapper bot-message-wrapper">
                    <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                    <div class="bot-message">
                        <p>Error: ${errorMessage}</p>
                        <button id="retry-contact-form" class="button">${tryAgainText}</button>
                    </div>
                </div>
            `;

            // Add event listener to retry button
            const retryButton = document.getElementById('retry-contact-form');
            if (retryButton) {
                retryButton.addEventListener('click', function () {
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
