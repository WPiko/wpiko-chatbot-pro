(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof wpikoChatbot === 'undefined' || !wpikoChatbot.pro_takeover_frontend) {
            return;
        }

        var inputField = document.getElementById('chatbot-input');
        var messagesContainer = document.getElementById('chatbot-messages');
        var statusDot = document.getElementById('chatbot-status-dot');
        var statusText = document.getElementById('chatbot-status');
        var headerImage = document.getElementById('chatbot-image');
        var chatbotInfo = document.getElementById('chatbot-info');
        var preMadeQuestions = document.getElementById('pre-made-questions');

        if (!messagesContainer || !statusText) {
            return;
        }

        var imageWrapper = document.getElementById('chatbot-image-wrapper');

        var state = {
            currentThreadId: '',
            lastKnownMessageId: 0,
            isTakeoverActive: false,
            takeoverAdminName: '',
            takeoverAdminAvatar: '',
            isPollingRequestInFlight: false,
            hasResolvedState: false,
            heartbeatNeeded: false,
            checkTimer: null,
            pollTimer: null,
            watchTimer: null,
            takeoverHeaderPrefix: typeof wpikoChatbot.takeover_header_prefix === 'string'
                ? wpikoChatbot.takeover_header_prefix
                : 'Live chat with',
            defaultSubtitle: statusText.textContent,
            defaultHeaderAvatar: headerImage ? headerImage.getAttribute('src') : '',
            defaultPlaceholder: inputField ? inputField.getAttribute('placeholder') : '',
        };

        function getTakeoverStatusLabel() {
            var prefix = (state.takeoverHeaderPrefix || '').trim();

            if (state.takeoverAdminName) {
                return prefix ? prefix + ' ' + state.takeoverAdminName : state.takeoverAdminName;
            }

            return prefix || 'Live chat';
        }

        function renderTakeoverState(isOnline) {
            if (isOnline === undefined) {
                isOnline = !!wpikoChatbot.configComplete;
            }

            if (statusDot) {
                statusDot.className = state.isTakeoverActive ? 'live' : (isOnline ? 'online' : 'offline');
            }

            if (state.isTakeoverActive) {
                statusText.textContent = getTakeoverStatusLabel();
                statusText.className = 'live';
            } else {
                statusText.textContent = state.defaultSubtitle;
                statusText.className = isOnline ? 'online' : 'offline';
            }

            if (inputField) {
                inputField.setAttribute(
                    'placeholder',
                    state.isTakeoverActive
                        ? (state.takeoverAdminName ? 'Message ' + state.takeoverAdminName + '...' : 'Message the live agent...')
                        : state.defaultPlaceholder
                );
            }

            if (preMadeQuestions && state.isTakeoverActive) {
                preMadeQuestions.style.display = 'none';
            }

            if (imageWrapper) {
                var statusDotEl = imageWrapper.querySelector('#chatbot-status-dot');
                var existingInitials = imageWrapper.querySelector('.chatbot-header-initials');

                if (state.isTakeoverActive && !state.takeoverAdminAvatar && state.takeoverAdminName) {
                    // No avatar — show initials like the message bubbles do
                    if (headerImage) {
                        headerImage.style.display = 'none';
                    }
                    var getInitials = typeof window.wpikoChatbotGetInitials === 'function'
                        ? window.wpikoChatbotGetInitials
                        : function (n) { return (n || 'A').charAt(0).toUpperCase(); };

                    if (!existingInitials) {
                        existingInitials = document.createElement('div');
                        existingInitials.className = 'chatbot-header-initials';
                        imageWrapper.insertBefore(existingInitials, statusDotEl);
                    }
                    existingInitials.textContent = getInitials(state.takeoverAdminName);
                    existingInitials.style.display = '';
                } else {
                    // Has avatar or not in takeover — use the <img>
                    if (existingInitials) {
                        existingInitials.style.display = 'none';
                    }
                    if (headerImage) {
                        headerImage.style.display = '';
                        headerImage.src = (state.isTakeoverActive && state.takeoverAdminAvatar)
                            ? state.takeoverAdminAvatar
                            : state.defaultHeaderAvatar;
                        headerImage.alt = (state.isTakeoverActive && state.takeoverAdminName)
                            ? state.takeoverAdminName
                            : 'Chatbot Image';
                    }
                }
            }
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function removeWaitingIndicator() {
            var waitingMessages = messagesContainer.querySelectorAll('.message-container.takeover-admin-waiting');

            waitingMessages.forEach(function (message) {
                message.remove();
            });
        }

        function appendWaitingIndicator() {
            var adminName = state.takeoverAdminName || 'Live agent';
            var adminAvatar = state.takeoverAdminAvatar ? escapeHtml(state.takeoverAdminAvatar) : '';
            var label = escapeHtml(adminName);
            var messageElement = document.createElement('div');
            var getInitials = typeof window.wpikoChatbotGetInitials === 'function'
                ? window.wpikoChatbotGetInitials
                : function (name) { return (name || 'A').charAt(0).toUpperCase(); };
            var initials = escapeHtml(getInitials(adminName));

            removeWaitingIndicator();

            messageElement.className = 'message-container takeover-admin-waiting';
            messageElement.innerHTML = `
                <div class="message-wrapper admin-message-wrapper">
                    ${adminAvatar
                        ? `<img src="${adminAvatar}" alt="${label}" class="message-avatar admin-avatar-image">`
                        : `<div class="message-avatar admin-avatar" aria-hidden="true">${initials}</div>`}
                    <div class="admin-message-group takeover-waiting-group">
                        <div class="admin-message-label">${label}</div>
                        <div class="admin-message takeover-waiting-message" role="status" aria-live="polite">
                            <div class="loading-dots" aria-hidden="true">
                                <div class="dot"></div>
                                <div class="dot"></div>
                                <div class="dot"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        window.wpikoChatbotShouldShowLoadingIndicator = function () {
            if (state.isTakeoverActive) {
                appendWaitingIndicator();
                return false;
            }

            return true;
        };

        window.wpikoChatbotHandleTakeoverResponse = function (data) {
            if (!data || !data.human_takeover) {
                removeWaitingIndicator();
                return;
            }

            setTakeoverState(
                true,
                data.takeover_admin_name || state.takeoverAdminName || '',
                data.takeover_admin_avatar || state.takeoverAdminAvatar || '',
                { suppressEvent: true }
            );
            appendWaitingIndicator();
        };

        function appendMessage(type, content, meta, suppressSound) {
            if (typeof window.wpikoChatbotAppendMessage !== 'function') {
                return;
            }

            window.wpikoChatbotAppendMessage(type, content, 'general_error', false, !!suppressSound, meta || {});
        }

        function setTakeoverState(isActive, adminName, adminAvatar, options) {
            options = options || {};

            state.isTakeoverActive = !!isActive;
            state.takeoverAdminName = adminName || '';
            state.takeoverAdminAvatar = adminAvatar || '';

            renderTakeoverState();

            if (!state.isTakeoverActive) {
                removeWaitingIndicator();
            }

            if (!state.hasResolvedState) {
                state.hasResolvedState = true;
            }

            if (options.suppressEvent) {
                return;
            }
        }

        function stopIntervals() {
            if (state.checkTimer) {
                clearInterval(state.checkTimer);
                state.checkTimer = null;
            }

            if (state.pollTimer) {
                clearInterval(state.pollTimer);
                state.pollTimer = null;
            }
        }

        function startPassiveCheck() {
            if (state.checkTimer || state.pollTimer) {
                return;
            }

            state.checkTimer = window.setInterval(function () {
                if (state.pollTimer) {
                    return;
                }

                pollTakeover(false);
            }, 5000);
        }

        function startActivePolling() {
            if (state.pollTimer) {
                return;
            }

            if (state.checkTimer) {
                clearInterval(state.checkTimer);
                state.checkTimer = null;
            }

            state.pollTimer = window.setInterval(function () {
                pollTakeover(false);
            }, 3000);
        }

        function syncThreadId() {
            var nextThreadId = sessionStorage.getItem('wpiko_chatbot_thread_id') || '';

            if (nextThreadId === state.currentThreadId) {
                return;
            }

            state.currentThreadId = nextThreadId;
            state.lastKnownMessageId = 0;
            stopIntervals();

            if (!state.currentThreadId) {
                setTakeoverState(false, '', '', { suppressEvent: true });
                return;
            }

            pollTakeover(true);
            startPassiveCheck();
        }

        function pollTakeover(syncOnly) {
            if (!state.currentThreadId || state.isPollingRequestInFlight) {
                return;
            }

            state.isPollingRequestInFlight = true;

            jQuery.ajax({
                url: wpikoChatbot.ajax_url,
                type: 'post',
                timeout: 15000,
                data: {
                    action: 'wpiko_chatbot_check_new_messages',
                    thread_id: state.currentThreadId,
                    last_message_id: state.lastKnownMessageId,
                    heartbeat: (typeof wpikoChatbot.heartbeat_enabled !== 'undefined' && wpikoChatbot.heartbeat_enabled && state.heartbeatNeeded) ? 1 : 0,
                    security: wpikoChatbot.nonce
                },
                success: function (response) {
                    if (!response.success) {
                        return;
                    }

                    var data = response.data || {};
                    var wasTakeoverActive = state.isTakeoverActive;

                    // Track demand-driven heartbeat flag from server
                    if (typeof data.heartbeat_needed !== 'undefined') {
                        state.heartbeatNeeded = !!data.heartbeat_needed;
                    }

                    setTakeoverState(
                        !!data.human_takeover,
                        data.takeover_admin_name || '',
                        data.takeover_admin_avatar || '',
                        { suppressEvent: syncOnly && !state.hasResolvedState }
                    );

                    if (data.human_takeover) {
                        startActivePolling();
                    } else if (state.pollTimer) {
                        clearInterval(state.pollTimer);
                        state.pollTimer = null;
                        startPassiveCheck();
                    }

                    if (data.human_takeover && !wasTakeoverActive && !syncOnly) {
                        appendWaitingIndicator();
                    }

                    if (!data.messages || !data.messages.length) {
                        return;
                    }

                    data.messages.forEach(function (message) {
                        var messageId = parseInt(message.id, 10);
                        if (messageId > state.lastKnownMessageId) {
                            state.lastKnownMessageId = messageId;
                        }

                        if (syncOnly) {
                            return;
                        }

                        if (message.event_type === 'takeover_notice' || message.event_type === 'release_notice') {
                            appendMessage('system', message.message, {
                                eventType: message.event_type
                            }, true);

                            if (message.event_type === 'takeover_notice') {
                                appendWaitingIndicator();
                            } else {
                                removeWaitingIndicator();
                            }

                            return;
                        }

                        removeWaitingIndicator();
                        appendMessage('admin', message.message, {
                            label: message.user_name || data.takeover_admin_name || 'Live agent',
                            avatarUrl: message.avatar_url || data.takeover_admin_avatar || ''
                        }, false);
                    });
                },
                complete: function () {
                    state.isPollingRequestInFlight = false;
                }
            });
        }

        renderTakeoverState();
        syncThreadId();

        state.watchTimer = window.setInterval(syncThreadId, 1000);
    });
})();