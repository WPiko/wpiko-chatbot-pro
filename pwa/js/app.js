/* WPiko PWA – Main Application */
(function () {
    'use strict';

    const OFFLINE_PRESENCE_NOTE = 'The visitor left the site. If they come back later, they will start a new chat, so live replies sent here will not appear in this thread.';
    const SESSION_CREDENTIAL_KEY = 'wpiko_pwa_session_cred';
    const PERSIST_CREDENTIAL_KEY = 'wpiko_pwa_persist_cred';
    const REMEMBER_ME_KEY       = 'wpiko_pwa_remember_me';

    /* ───────────── State ───────────── */
    let state = {
        siteUrl: '',
        username: '',
        appPassword: '',
        conversations: [],
        currentConversation: null,
        currentMessages: [],
        currentUser: null,
        page: 1,
        totalPages: 1,
        isTakeover: false,
        takeoverAdminId: 0,
        takeoverAdminName: '',
        takeoverOwnedByCurrentAdmin: false,
        isUserOnline: false,
        userLastSeen: null,
        userPresenceState: 'offline',
        userPresenceNote: '',
        pushEnabled: false,
        pollTimer: null,
        listPollTimer: null,
        notificationSessionId: '',
        notificationHighlightTimer: null,
        defaultTakeoverButtonText: '',
    };

    /* ───────────── DOM Refs ───────────── */
    const $ = (s) => document.querySelector(s);
    const $$ = (s) => document.querySelectorAll(s);
    const screens = {
        login: $('#screen-login'),
        conversations: $('#screen-conversations'),
        conversation: $('#screen-conversation'),
        settings: $('#screen-settings'),
    };

    /* ───────────── API Client ───────────── */
    function apiUrl(path) {
        return state.siteUrl.replace(/\/+$/, '') + '/wp-json/wpiko-chatbot/v1' + path;
    }

    function authHeader() {
        return 'Basic ' + btoa(state.username + ':' + state.appPassword);
    }

    function resolveAuthErrorMessage(status, data) {
        const code = data && typeof data.code === 'string' ? data.code : '';
        const message = data && typeof data.message === 'string' ? data.message.trim() : '';
        const normalizedMessage = message.toLowerCase();

        if (code === 'pwa_access_denied' || code === 'pwa_capability_denied' || code === 'pwa_disabled' || code === 'license_inactive') {
            return message;
        }

        if (code === 'rest_not_logged_in') {
            return 'Authentication failed. Check your username and use an Application Password, not your regular WordPress password.';
        }

        if (message) {
            if (normalizedMessage.includes('application password')
                || normalizedMessage.includes('invalid')
                || normalizedMessage.includes('incorrect')
                || normalizedMessage.includes('authentication failed')) {
                return 'Authentication failed. Check your username and use an Application Password, not your regular WordPress password.';
            }

            if (normalizedMessage.includes('not have permission') || normalizedMessage.includes('cannot access the mobile app')) {
                return message;
            }

            if (normalizedMessage !== 'sorry, you are not allowed to do that.') {
                return message;
            }
        }

        if (status === 403) {
            return 'Access denied. This account is missing the required Mobile App permissions.';
        }

        return 'Authentication failed. Check your username and use an Application Password, not your regular WordPress password.';
    }

    async function api(method, path, body) {
        const opts = {
            method: method,
            credentials: 'omit',
            headers: {
                'Authorization': authHeader(),
                'Content-Type': 'application/json',
            },
        };
        if (body) opts.body = JSON.stringify(body);

        // Append a cache-busting parameter to GET requests to bypass
        // server-level caches (e.g. LiteSpeed, Varnish) that may ignore
        // application-level Cache-Control headers.
        let url = apiUrl(path);
        if (method === 'GET') {
            const sep = url.indexOf('?') === -1 ? '?' : '&';
            url += sep + '_=' + Date.now();
        }

        const res = await fetch(url, opts);
        let data = null;

        try {
            data = await res.json();
        } catch (e) {
            data = null;
        }

        if (res.status === 401 || res.status === 403) {
            const isLoggedIn = !!state.conversations.length || !!state.currentConversation;
            if (isLoggedIn) {
                toast('Session expired. Please log in again.', 'error');
                resetAuthenticatedState();
            }
            throw new Error(resolveAuthErrorMessage(res.status, data));
        }

        if (!res.ok) {
            throw new Error(data && data.message ? data.message : 'API error');
        }
        return data;
    }

    /* ───────────── Screen Navigation ───────────── */
    function showScreen(name) {
        Object.values(screens).forEach(s => s.classList.remove('active'));
        if (screens[name]) screens[name].classList.add('active');
        // Stop polling when leaving conversation
        if (name !== 'conversation') stopPolling();
        // Manage list auto-refresh
        if (name === 'conversations') {
            startListPolling();
        } else {
            stopListPolling();
        }
    }

    function getConversationFromHash() {
        var match = window.location.hash.match(/^#conversation\/([^/?#]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    function syncConversationHash(sessionId) {
        if (!sessionId) {
            if (window.location.hash) {
                history.replaceState(null, '', window.location.pathname + window.location.search);
            }
            return;
        }

        var nextHash = '#conversation/' + encodeURIComponent(sessionId);
        if (window.location.hash !== nextHash) {
            window.location.hash = nextHash;
        }
    }

    function setNotificationTarget(sessionId) {
        state.notificationSessionId = sessionId || '';
        if (state.notificationHighlightTimer) {
            clearTimeout(state.notificationHighlightTimer);
            state.notificationHighlightTimer = null;
        }
    }

    function clearNotificationTarget() {
        state.notificationSessionId = '';
        if (state.notificationHighlightTimer) {
            clearTimeout(state.notificationHighlightTimer);
            state.notificationHighlightTimer = null;
        }
    }

    async function navigateToConversation(sessionId, source) {
        if (!sessionId) return;
        syncConversationHash(sessionId);

        if (source === 'notification') {
            setNotificationTarget(sessionId);
        }

        if (!state.username || !state.appPassword) {
            return;
        }

        if (state.currentConversation === sessionId && screens.conversation.classList.contains('active')) {
            return;
        }

        await openConversation(sessionId);
    }

    async function handleHashRoute() {
        var sessionId = getConversationFromHash();
        if (sessionId) {
            await navigateToConversation(sessionId, 'notification');
            return;
        }

        if (screens.conversation.classList.contains('active')) {
            state.currentConversation = null;
            showScreen('conversations');
            loadConversations(1);
        }
    }

    /* ───────────── Toast ───────────── */
    function toast(msg, type) {
        type = type || 'info';
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.textContent = msg;
        $('#toast-container').appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    /* ───────────── Auth / Login ───────────── */
    function isRememberMe() {
        try { return localStorage.getItem(REMEMBER_ME_KEY) === '1'; } catch (e) { return false; }
    }

    function loadCredentials() {
        try {
            // Try persistent storage first, then session
            var raw = localStorage.getItem(PERSIST_CREDENTIAL_KEY) || sessionStorage.getItem(SESSION_CREDENTIAL_KEY);
            if (raw) {
                var parsed = JSON.parse(raw);
                state.siteUrl = detectSiteUrl();
                state.username = parsed.username || '';
                state.appPassword = parsed.appPassword || '';
                return true;
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    function saveCredentials() {
        try {
            var payload = JSON.stringify({
                username: state.username,
                appPassword: state.appPassword,
            });
            if (isRememberMe()) {
                localStorage.setItem(PERSIST_CREDENTIAL_KEY, payload);
                sessionStorage.removeItem(SESSION_CREDENTIAL_KEY);
            } else {
                sessionStorage.setItem(SESSION_CREDENTIAL_KEY, payload);
                localStorage.removeItem(PERSIST_CREDENTIAL_KEY);
            }
        } catch (e) { /* ignore */ }
    }

    function clearCredentials() {
        try {
            sessionStorage.removeItem(SESSION_CREDENTIAL_KEY);
            localStorage.removeItem(PERSIST_CREDENTIAL_KEY);
            localStorage.removeItem(REMEMBER_ME_KEY);
        } catch (e) { /* ignore */ }
    }

    function resetAuthenticatedState() {
        clearCredentials();
        stopPolling();
        stopListPolling();
        clearNotificationTarget();
        syncConversationHash('');
        state.siteUrl = '';
        state.username = '';
        state.appPassword = '';
        state.conversations = [];
        state.currentConversation = null;
        state.currentMessages = [];
        state.currentUser = null;
        state.page = 1;
        state.totalPages = 1;
        state.isTakeover = false;
        state.takeoverAdminId = 0;
        state.takeoverAdminName = '';
        state.takeoverOwnedByCurrentAdmin = false;
        state.isUserOnline = false;
        state.userLastSeen = null;
        state.userPresenceState = 'offline';
        state.userPresenceNote = '';
        state.pushEnabled = false;
        hideUserInfoPanel();
        showScreen('login');
    }

    async function cleanupAnonymousPushSubscription() {
        if (!('serviceWorker' in navigator)) return;

        try {
            const basePath = window.location.pathname.replace(/\/+$/, '');
            const scope = (basePath || '/wpiko-app') + '/';
            const reg = await navigator.serviceWorker.getRegistration(scope);

            if (!reg) {
                return;
            }

            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                await sub.unsubscribe();
            }
        } catch (e) { /* silent */ }
    }

    async function logout() {
        // Capture credentials before clearing so the server-side unsubscribe
        // can still authenticate.  If the API call fails, the browser-side
        // sub.unsubscribe() in unsubscribePush() still stops push delivery.
        const savedUsername = state.username;
        const savedPassword = state.appPassword;
        const savedSiteUrl = state.siteUrl;

        try {
            await unsubscribePush();
        } catch (e) {
            // API call may fail — browser-side unsubscribe already handled
            // inside unsubscribePush().  Fall through to clear state.
        }

        // If unsubscribePush silently skipped the API DELETE (e.g. credentials
        // were already empty), retry once with the saved credentials.
        if (savedUsername && savedPassword) {
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    // Subscription still exists — the server DELETE likely failed.
                    const json = sub.toJSON();
                    await fetch(
                        savedSiteUrl.replace(/\/+$/, '') + '/wp-json/wpiko-chatbot/v1/push/subscribe',
                        {
                            method: 'DELETE',
                            credentials: 'omit',
                            headers: {
                                'Authorization': 'Basic ' + btoa(savedUsername + ':' + savedPassword),
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ endpoint: json.endpoint }),
                        }
                    ).catch(function () {});
                    await sub.unsubscribe().catch(function () {});
                }
            } catch (e) { /* silent */ }
        }

        resetAuthenticatedState();
    }

    function detectSiteUrl() {
        // Auto-detect from current origin since PWA is served from the same WP site
        return window.location.origin;
    }

    async function doLogin(username, password) {
        state.siteUrl = detectSiteUrl();
        state.username = username;
        state.appPassword = password;

        // Validate by fetching first page of conversations
        await api('GET', '/conversations?per_page=1');

        // Persist Remember Me preference before saving credentials
        var rememberBox = $('#remember-me');
        try {
            if (rememberBox && rememberBox.checked) {
                localStorage.setItem(REMEMBER_ME_KEY, '1');
            } else {
                localStorage.removeItem(REMEMBER_ME_KEY);
            }
        } catch (e) { /* ignore */ }

        saveCredentials();
        afterLogin();
    }

    function afterLogin() {
        showScreen('conversations');
        loadConversations(1);
        registerSW();
    }

    /* ───────────── Conversations List ───────────── */
    async function loadConversations(page, silent) {
        const listEl = $('#conversations-list');
        if (page === 1 && !silent) listEl.innerHTML = '<div class="loading-spinner">Loading...</div>';
        try {
            const data = await api('GET', '/conversations?page=' + page + '&per_page=20');
            state.conversations = page === 1 ? data.conversations : state.conversations.concat(data.conversations);
            state.page = data.page;
            state.totalPages = data.total_pages;
            renderConversationList();
        } catch (e) {
            if (!silent) {
                listEl.innerHTML = '<div class="empty-state"><p>' + escapeHtml(e.message) + '</p></div>';
            }
        }
    }

    function renderConversationList() {
        const listEl = $('#conversations-list');
        if (state.conversations.length === 0) {
            listEl.innerHTML = '<div class="empty-state"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><p>No conversations yet</p></div>';
            return;
        }
        let html = '';
        state.conversations.forEach(function (c) {
            const time = relativeTime(c.timestamp);
            const preview = (c.last_message || '').substring(0, 60);
            const onlineClass = normalizePresenceState(c.user_presence_state, !!c.user_online);
            html += '<div class="conversation-card" data-session="' + escapeAttr(c.session_id) + '">'
                + '<div class="conv-avatar-wrap"><img class="conv-avatar" src="' + escapeAttr(c.avatar_url) + '" alt="">'
                + '<span class="user-status-dot ' + onlineClass + '"></span></div>'
                + '<div class="conv-info">'
                + '<div class="conv-name">' + escapeHtml(c.user_name)
                + (c.human_takeover ? ' <span class="takeover-badge">LIVE' + (c.takeover_admin_name ? ' <span class="takeover-agent">&middot; ' + escapeHtml(c.takeover_admin_name) + '</span>' : '') + '</span>' : '')
                + '</div>'
                + '<div class="conv-preview">' + escapeHtml(preview) + '</div>'
                + '</div>'
                + '<div class="conv-meta">' + escapeHtml(time) + '</div>'
                + '</div>';
        });
        listEl.innerHTML = html;

        // Load more toggling
        const lmContainer = $('#load-more-container');
        if (state.page < state.totalPages) {
            lmContainer.style.display = 'block';
        } else {
            lmContainer.style.display = 'none';
        }

        // Attach click handlers
        $$('.conversation-card').forEach(function (el) {
            el.addEventListener('click', function () {
                clearNotificationTarget();
                openConversation(el.dataset.session);
            });
        });
    }

    /* ───────────── Single Conversation ───────────── */
    function getNotificationTargetIndex() {
        if (state.notificationSessionId !== state.currentConversation) {
            return -1;
        }

        for (var index = state.currentMessages.length - 1; index >= 0; index--) {
            if (state.currentMessages[index].role === 'user') {
                return index;
            }
        }

        return -1;
    }

    function applyNotificationFocus() {
        var conversationScreen = $('#screen-conversation');
        var target = $('.message-item.notification-target');

        if (!conversationScreen || !target) {
            clearNotificationTarget();
            return;
        }

        conversationScreen.classList.add('notification-focus');
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });

        state.notificationHighlightTimer = setTimeout(function () {
            conversationScreen.classList.remove('notification-focus');
            target.classList.remove('notification-target');
            clearNotificationTarget();
        }, 3200);
    }

    function normalizePresenceState(rawState, isOnline) {
        switch (rawState) {
            case 'online':
            case 'chat_cleared':
            case 'offline':
                return rawState;
            case 'switching_pages':
                return 'offline';
            default:
                return isOnline ? 'online' : 'offline';
        }
    }

    function normalizePresenceNote(rawState, note) {
        if (rawState === 'switching_pages') {
            return OFFLINE_PRESENCE_NOTE;
        }

        return typeof note === 'string' ? note : '';
    }

    function updateUserPresence(data) {
        state.isUserOnline = !!data.user_online;
        state.userLastSeen = data.user_last_seen || null;
        state.userPresenceState = normalizePresenceState(data.user_presence_state, state.isUserOnline);
        state.userPresenceNote = normalizePresenceNote(data.user_presence_state, data.user_presence_note);
    }

    function hasUserPresenceChanged(data) {
        var nextIsOnline = !!data.user_online;
        var nextState = normalizePresenceState(data.user_presence_state, nextIsOnline);
        var nextNote = normalizePresenceNote(data.user_presence_state, data.user_presence_note);

        return state.isUserOnline !== nextIsOnline
            || state.userPresenceState !== nextState
            || state.userPresenceNote !== nextNote;
    }

    function getPresenceLabel() {
        switch (state.userPresenceState) {
            case 'online':
                return 'Online now';
            case 'chat_cleared':
                return 'Chat cleared';
            default:
                return state.userLastSeen ? 'Last seen ' + relativeTime(state.userLastSeen) : 'Offline';
        }
    }

    function getPresenceAlertTitle() {
        switch (state.userPresenceState) {
            case 'chat_cleared':
                return 'Visitor cleared this chat';
            default:
                return 'Visitor is offline';
        }
    }

    function renderPresenceAlert() {
        var alertEl = $('#presence-alert');
        var titleEl = $('#presence-alert-title');
        var noteEl = $('#presence-alert-note');

        if (!alertEl || !titleEl || !noteEl) {
            return;
        }

        if (!state.userPresenceNote) {
            alertEl.style.display = 'none';
            alertEl.className = 'presence-alert';
            titleEl.textContent = '';
            noteEl.textContent = '';
            return;
        }

        alertEl.style.display = 'block';
        alertEl.className = 'presence-alert ' + state.userPresenceState;
        titleEl.textContent = getPresenceAlertTitle();
        noteEl.textContent = state.userPresenceNote;
    }

    function updateTakeoverState(data) {
        state.isTakeover = !!data.human_takeover;
        state.takeoverAdminId = data.takeover_admin_id || 0;
        state.takeoverAdminName = data.takeover_admin_name || '';
        state.takeoverOwnedByCurrentAdmin = !!data.takeover_owned_by_current_admin;
    }

    function getTakeoverButtonLabel() {
        return state.defaultTakeoverButtonText || 'Take Over';
    }

    async function openConversation(sessionId) {
        syncConversationHash(sessionId);
        state.currentConversation = sessionId;
        showScreen('conversation');
        $('#messages-container').innerHTML = '<div class="loading-spinner">Loading...</div>';
        $('#takeover-bar').style.display = 'none';
        $('#reply-container').style.display = 'none';
        state.isUserOnline = false;
        state.userLastSeen = null;
        state.userPresenceState = 'offline';
        state.userPresenceNote = '';
        renderPresenceAlert();
        try {
            const data = await api('GET', '/conversations/' + encodeURIComponent(sessionId));
            state.currentMessages = data.messages;
            state.currentUser = data.user;
            updateTakeoverState(data);
            updateUserPresence(data);
            renderConversation();
            startPolling();
        } catch (e) {
            $('#messages-container').innerHTML = '<div class="empty-state"><p>' + escapeHtml(e.message) + '</p></div>';
        }
    }

    function renderConversation() {
        var takeoverButton = $('#btn-takeover');
        var releaseButton = $('#btn-release');
        var replyContainer = $('#reply-container');

        if (!state.defaultTakeoverButtonText && takeoverButton) {
            state.defaultTakeoverButtonText = takeoverButton.textContent;
        }

        // Header
        $('#conv-user-name').textContent = state.currentUser ? state.currentUser.name : '';
        $('#conv-avatar').src = state.currentUser ? state.currentUser.avatar_url : '';
        $('#conv-takeover-badge').style.display = state.isTakeover ? 'inline-block' : 'none';
        if (state.isTakeover && state.takeoverAdminName) {
            $('#conv-takeover-badge').innerHTML = 'LIVE <span class="takeover-agent">&middot; ' + escapeHtml(state.takeoverAdminName) + '</span>';
        } else {
            $('#conv-takeover-badge').textContent = 'LIVE';
        }

        // User online status in header
        var statusEl = $('#conv-user-status');
        if (statusEl) {
            statusEl.textContent = getPresenceLabel();
            statusEl.className = 'conv-user-status ' + state.userPresenceState;
        }

        renderPresenceAlert();

        // Messages
        const container = $('#messages-container');
        const notificationTargetIndex = getNotificationTargetIndex();
        let html = '';
        state.currentMessages.forEach(function (m, index) {
            const role = m.role;
            const isSystemNotice = m.event_type === 'takeover_notice' || m.event_type === 'release_notice';
            const cls = isSystemNotice
                ? 'system'
                : (role === 'user' ? 'user' : (role === 'admin' ? 'admin' : (role === 'error' ? 'error' : 'assistant')));
            const timeStr = formatTime(m.timestamp);
            html += '<div class="message-item ' + cls + (index === notificationTargetIndex ? ' notification-target' : '') + '">';
            if (isSystemNotice) {
                html += '<div class="message-bubble ' + cls + '">' + escapeHtml(m.message) + '</div>';
                html += '<div class="message-time center">' + timeStr + '</div>';
                html += '</div>';
                return;
            }

            if (role === 'admin') {
                var adminName = m.user_name || 'Admin';
                html += '<div class="message-label admin-label">' + escapeHtml(adminName) + '</div>';
            } else if (role === 'assistant') {
                html += '<div class="message-label bot-label">Bot</div>';
            }
            // Assistant messages are sanitized server-side before being returned by the REST API.
            var messageContent = (role === 'assistant') ? String(m.message || '') : escapeHtml(m.message);
            html += '<div class="message-bubble ' + cls + '">' + messageContent + '</div>';
            html += '<div class="message-time ' + (role === 'user' ? 'right' : 'left') + '">' + timeStr + '</div>';
            html += '</div>';
        });
        container.innerHTML = html;
        if (notificationTargetIndex !== -1) {
            applyNotificationFocus();
        } else {
            container.scrollTop = container.scrollHeight;
        }

        // Takeover bar
        $('#takeover-bar').style.display = 'flex';
        if (state.isTakeover && state.takeoverOwnedByCurrentAdmin) {
            takeoverButton.style.display = 'none';
            takeoverButton.disabled = false;
            takeoverButton.textContent = getTakeoverButtonLabel();
            releaseButton.style.display = 'block';
            replyContainer.style.display = 'flex';
        } else if (state.isTakeover) {
            takeoverButton.style.display = 'block';
            takeoverButton.disabled = true;
            takeoverButton.textContent = state.takeoverAdminName ? 'Handled by ' + state.takeoverAdminName : 'Handled by another admin';
            releaseButton.style.display = 'none';
            replyContainer.style.display = 'none';
        } else {
            takeoverButton.style.display = 'block';
            takeoverButton.disabled = false;
            takeoverButton.textContent = getTakeoverButtonLabel();
            releaseButton.style.display = 'none';
            replyContainer.style.display = 'none';
        }
    }

    /* ───────────── Polling for new messages ───────────── */
    /**
     * Get the highest real DB message ID (ignores optimistic local messages
     * which use Date.now() and have very large IDs).
     */
    function getLastMessageId() {
        if (!state.currentMessages || !state.currentMessages.length) return 0;
        var maxId = 0;
        for (var i = state.currentMessages.length - 1; i >= 0; i--) {
            var id = parseInt(state.currentMessages[i].id, 10) || 0;
            // Real DB IDs are sequential integers; optimistic IDs use Date.now() (13+ digits)
            if (id > 0 && id < 1e12) {
                maxId = id;
                break;
            }
        }
        return maxId;
    }

    function startPolling() {
        stopPolling();
        state.pollTimer = setInterval(async function () {
            if (!state.currentConversation) return;
            try {
                var sinceId = getLastMessageId();
                var url = '/conversations/' + encodeURIComponent(state.currentConversation);
                if (sinceId > 0) {
                    url += '?since_id=' + sinceId;
                }
                const data = await api('GET', url);

                // Update online status on every poll cycle
                var presenceChanged = hasUserPresenceChanged(data);
                var takeoverChanged = state.isTakeover !== !!data.human_takeover
                    || state.takeoverAdminId !== (data.takeover_admin_id || 0)
                    || state.takeoverAdminName !== (data.takeover_admin_name || '')
                    || state.takeoverOwnedByCurrentAdmin !== !!data.takeover_owned_by_current_admin;
                updateUserPresence(data);
                updateTakeoverState(data);

                // Incremental mode: append new messages and replace optimistic ones
                if (data.incremental) {
                    if (data.messages.length > 0 || presenceChanged || takeoverChanged) {
                        // Remove optimistic (local) messages — they'll be replaced by real DB versions
                        state.currentMessages = state.currentMessages.filter(function (m) {
                            return parseInt(m.id, 10) < 1e12;
                        });
                        // Deduplicate: skip messages already in state
                        var existingIds = {};
                        state.currentMessages.forEach(function (m) { existingIds[m.id] = true; });
                        data.messages.forEach(function (msg) {
                            if (!existingIds[msg.id]) {
                                state.currentMessages.push(msg);
                            }
                        });
                        renderConversation();
                    }
                } else {
                    // Full response (first load or fallback)
                    if (data.messages.length !== state.currentMessages.length || presenceChanged || takeoverChanged) {
                        state.currentMessages = data.messages;
                        renderConversation();
                    }
                }
            } catch (e) { /* silent */ }
        }, 5000);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    /* ───────────── List auto-refresh (20s) ───────────── */
    function startListPolling() {
        stopListPolling();
        state.listPollTimer = setInterval(function () {
            if (!screens.conversations.classList.contains('active')) return;
            loadConversations(1, true);
        }, 20000);
    }

    function stopListPolling() {
        if (state.listPollTimer) {
            clearInterval(state.listPollTimer);
            state.listPollTimer = null;
        }
    }

    // Pause polling when the tab/app is hidden (saves battery and bandwidth)
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopPolling();
            stopListPolling();
        } else {
            if (state.currentConversation && screens.conversation.classList.contains('active')) {
                startPolling();
            }
            if (screens.conversations.classList.contains('active')) {
                startListPolling();
            }
        }
    });

    /* ───────────── Takeover / Release ───────────── */
    async function activateTakeover() {
        try {
            var result = await api('POST', '/conversations/' + encodeURIComponent(state.currentConversation) + '/takeover');
            updateTakeoverState(result);
            renderConversation();
            toast('Takeover activated', 'success');
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function releaseTakeover() {
        try {
            var result = await api('POST', '/conversations/' + encodeURIComponent(state.currentConversation) + '/release');
            updateTakeoverState(result);
            renderConversation();
            toast('Released to AI', 'success');
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    /* ───────────── Send Reply ───────────── */
    async function sendReply() {
        const input = $('#reply-input');
        const msg = input.value.trim();
        if (!msg) return;

        if (!state.isTakeover || !state.takeoverOwnedByCurrentAdmin) {
            toast(state.takeoverAdminName ? 'This conversation is currently handled by ' + state.takeoverAdminName + '.' : 'Activate takeover before replying.', 'error');
            return;
        }

        const btn = $('#btn-send-reply');
        btn.disabled = true;
        input.value = '';
        autoResizeTextarea(input);

        try {
            await api('POST', '/conversations/' + encodeURIComponent(state.currentConversation) + '/reply', { message: msg });
            // Add locally for instant feedback
            state.currentMessages.push({
                id: Date.now(),
                role: 'admin',
                message: msg,
                user_name: state.takeoverAdminName || state.username,
                timestamp: new Date().toISOString().replace('T', ' ').substring(0, 19),
            });
            renderConversation();
        } catch (e) {
            toast(e.message, 'error');
            input.value = msg;
        } finally {
            btn.disabled = false;
        }
    }

    /* ───────────── User Info Panel ───────────── */
    function showUserInfoPanel() {
        if (!state.currentUser) return;
        const u = state.currentUser;
        $('#info-name').textContent = u.name;
        $('#info-email').textContent = u.email || 'N/A';
        $('#info-avatar').src = u.avatar_url || '';
        $('#info-status').textContent = u.status || 'Unknown';
        $('#info-device').textContent = u.device_type || 'Unknown';
        const loc = [u.city, u.region, u.country].filter(Boolean).join(', ');
        $('#info-location').textContent = loc || 'Unknown';

        // Show online presence
        var presenceEl = $('#info-presence');
        if (presenceEl) {
            presenceEl.textContent = getPresenceLabel();
            presenceEl.className = 'info-value ' + state.userPresenceState;
        }

        $('#panel-user-info').style.display = 'block';
    }

    function hideUserInfoPanel() {
        $('#panel-user-info').style.display = 'none';
    }

    /* ───────────── Settings ───────────── */
    function showSettings() {
        $('#setting-site-url').textContent = state.siteUrl;
        $('#setting-username').textContent = state.username;
        $('#setting-push').checked = state.pushEnabled;
        showScreen('settings');
    }

    /* ───────────── Push Notifications ───────────── */
    function getPushSupportIssue() {
        if (!window.isSecureContext) {
            return 'Push notifications require HTTPS with a trusted certificate, or localhost.';
        }
        if (!('serviceWorker' in navigator)) {
            return 'This browser does not support Service Workers.';
        }
        if (!('PushManager' in window)) {
            return 'This browser does not support the Push API.';
        }
        if (!('Notification' in window)) {
            return 'This browser does not support notifications.';
        }
        return '';
    }

    function truncateText(value, maxLength) {
        value = value || '';
        return value.length > maxLength ? value.slice(0, maxLength) : value;
    }

    function detectPushDeviceName() {
        var ua = (navigator.userAgent || '').toLowerCase();

        if (ua.indexOf('iphone') !== -1) return 'iPhone';
        if (ua.indexOf('ipad') !== -1) return 'iPad';
        if (ua.indexOf('android') !== -1) return 'Android';
        if (ua.indexOf('macintosh') !== -1 || ua.indexOf('mac os x') !== -1) return 'Mac';
        if (ua.indexOf('windows') !== -1) return 'Windows';
        if (ua.indexOf('linux') !== -1) return 'Linux';
        return 'Unknown device';
    }

    function detectPushBrowserName() {
        var ua = (navigator.userAgent || '').toLowerCase();

        if (ua.indexOf('edg/') !== -1 || ua.indexOf('edge/') !== -1) return 'Edge';
        if (ua.indexOf('firefox') !== -1 || ua.indexOf('fxios') !== -1) return 'Firefox';
        if (ua.indexOf('chrome') !== -1 || ua.indexOf('crios') !== -1) return 'Chrome';
        if (ua.indexOf('safari') !== -1) return 'Safari';
        return 'Browser';
    }

    function getPushDeviceMetadata() {
        var deviceName = detectPushDeviceName();
        var browserName = detectPushBrowserName();
        var labelParts = [];

        if (deviceName) {
            labelParts.push(deviceName);
        }

        if (browserName && browserName !== deviceName) {
            labelParts.push(browserName);
        }

        return {
            label: truncateText(labelParts.join(' / ') || 'Unknown device', 191),
            user_agent: truncateText(navigator.userAgent || '', 1000),
        };
    }

    async function syncExistingPushSubscription(subscription) {
        if (!subscription || !state.username || !state.appPassword) {
            return;
        }

        try {
            var json = subscription.toJSON();
            if (!json || !json.endpoint || !json.keys) {
                return;
            }

            await api('POST', '/push/subscribe', {
                endpoint: json.endpoint,
                keys: json.keys,
                device: getPushDeviceMetadata(),
            });
        } catch (e) {
            console.warn('Push sync failed', e);
        }
    }

    async function registerSW() {
        if (!('serviceWorker' in navigator)) return;
        try {
            const basePath = window.location.pathname.replace(/\/+$/, '');
            const swUrl = (basePath || '/wpiko-app') + '/sw';
            const scope = (basePath || '/wpiko-app') + '/';
            const reg = await navigator.serviceWorker.register(swUrl, { scope: scope });
            // Listen for push navigate events from SW
            navigator.serviceWorker.addEventListener('message', function (e) {
                if (!e.data || !e.data.session_id) {
                    return;
                }

                if (e.data.type === 'NAVIGATE_CONVERSATION' || e.data.type === 'notification-click') {
                    navigateToConversation(e.data.session_id, 'notification');
                }
            });
            // Check existing push state
            const sub = await reg.pushManager.getSubscription();
            state.pushEnabled = !!sub;
            if (sub) {
                syncExistingPushSubscription(sub);
            }
        } catch (e) {
            console.warn('SW registration failed', e);
        }
    }

    async function subscribePush() {
        const supportIssue = getPushSupportIssue();
        if (supportIssue) {
            toast(supportIssue, 'error');
            return false;
        }
        try {
            const vapidResp = await api('GET', '/push/vapid-public-key');
            const publicKey = vapidResp.public_key;

            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey),
            });

            const json = sub.toJSON();
            await api('POST', '/push/subscribe', {
                endpoint: json.endpoint,
                keys: json.keys,
                device: getPushDeviceMetadata(),
            });
            state.pushEnabled = true;
            toast('Push notifications enabled', 'success');
            return true;
        } catch (e) {
            console.error('Push subscribe error', e);
            toast('Failed to enable push notifications', 'error');
            return false;
        }
    }

    async function unsubscribePush() {
        if (!('serviceWorker' in navigator)) return;
        try {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                const json = sub.toJSON();
                await api('DELETE', '/push/subscribe', { endpoint: json.endpoint }).catch(function () {});
                await sub.unsubscribe();
            }
            state.pushEnabled = false;
        } catch (e) { /* silent */ }
    }

    /* ───────────── Helpers ───────────── */
    function escapeHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function relativeTime(dateStr) {
        if (!dateStr) return '';
        var now = Date.now();
        var then = new Date(dateStr.replace(' ', 'T')).getTime();
        var diff = Math.floor((now - then) / 1000);
        if (diff < 60) return 'now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd';
        return new Date(then).toLocaleDateString();
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T'));
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; i++) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function autoResizeTextarea(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }

    /* ───────────── Event Bindings ───────────── */
    function updateRememberHint() {
        var hint = $('#remember-hint');
        if (!hint) return;
        var box = $('#remember-me');
        hint.textContent = box && box.checked
            ? 'Your credentials will be saved on this device until you log out.'
            : 'Your Application Password is stored only for the current browser session.';
    }

    function bindEvents() {
        // Remember Me checkbox
        var rememberBox = $('#remember-me');
        if (rememberBox) {
            rememberBox.addEventListener('change', updateRememberHint);
        }

        // Login form
        $('#login-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            var btn = $('#login-btn');
            var errEl = $('#login-error');
            errEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Connecting...';

            try {
                var username = $('#username').value.trim();
                var password = $('#app-password').value.trim();
                await doLogin(username, password);
            } catch (err) {
                errEl.textContent = err.message || 'Connection failed. Check your credentials.';
                errEl.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Connect';
            }
        });

        // Refresh
        $('#btn-refresh').addEventListener('click', function () { loadConversations(1); });

        // Load more
        $('#btn-load-more').addEventListener('click', function () {
            if (state.page < state.totalPages) loadConversations(state.page + 1);
        });

        // Settings
        $('#btn-settings').addEventListener('click', showSettings);
        $('#btn-back-settings').addEventListener('click', function () { showScreen('conversations'); });

        // Back from conversation
        $('#btn-back').addEventListener('click', function () {
            clearNotificationTarget();
            syncConversationHash('');
            showScreen('conversations');
            loadConversations(1); // refresh list
        });

        // User info panel
        $('#btn-user-info').addEventListener('click', showUserInfoPanel);
        $('#btn-close-panel').addEventListener('click', hideUserInfoPanel);
        $('.panel-overlay').addEventListener('click', hideUserInfoPanel);

        // Takeover / Release
        $('#btn-takeover').addEventListener('click', activateTakeover);
        $('#btn-release').addEventListener('click', releaseTakeover);

        // Reply input
        var replyInput = $('#reply-input');
        replyInput.addEventListener('input', function () {
            autoResizeTextarea(replyInput);
            $('#btn-send-reply').disabled = !replyInput.value.trim();
        });
        replyInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendReply();
            }
        });
        $('#btn-send-reply').addEventListener('click', sendReply);

        // Push toggle
        $('#setting-push').addEventListener('change', async function () {
            if (this.checked) {
                var ok = await subscribePush();
                if (!ok) this.checked = false;
            } else {
                await unsubscribePush();
                toast('Push notifications disabled', 'info');
            }
        });

        // Logout
        $('#btn-logout').addEventListener('click', async function () {
            if (confirm('Disconnect from this site?')) {
                await logout();
            }
        });
    }

    /* ───────────── Init ───────────── */
    function init() {
        bindEvents();

        // Restore Remember Me checkbox state
        var rememberBox = $('#remember-me');
        if (rememberBox) {
            rememberBox.checked = isRememberMe();
            updateRememberHint();
        }

        window.addEventListener('hashchange', function () {
            handleHashRoute();
        });
        if (loadCredentials()) {
            afterLogin();
            handleHashRoute();
        } else {
            cleanupAnonymousPushSubscription();
            showScreen('login');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
