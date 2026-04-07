// WPiko Chatbot Pro — Service Worker
const CACHE_NAME = 'wpiko-pwa-shell-v2';
const APP_SCOPE = '/wpiko-app/';
const APP_URLS = [
    '/wpiko-app/',
    '/wpiko-app/manifest.json',
    '/wpiko-app/css/pwa-style.css',
    '/wpiko-app/js/app.js',
];

function buildOfflineResponse() {
    return new Response('The app is temporarily unavailable. Please try again.', {
        status: 503,
        statusText: 'Service Unavailable',
        headers: {
            'Content-Type': 'text/plain; charset=UTF-8',
            'Cache-Control': 'no-store',
        },
    });
}

function cacheAppShell() {
    return caches.open(CACHE_NAME).then(function (cache) {
        return Promise.all(
            APP_URLS.map(function (url) {
                return fetch(new Request(url, { cache: 'reload' })).then(function (response) {
                    if (!response || response.status !== 200) {
                        throw new Error('Failed to precache ' + url);
                    }

                    return cache.put(url, response);
                }).catch(function () {
                    return null;
                });
            })
        );
    });
}

// Install — skip waiting to activate immediately
self.addEventListener('install', function (event) {
    event.waitUntil(
        cacheAppShell().then(function () {
            return self.skipWaiting();
        })
    );
});

// Activate — claim clients immediately, clean stale caches
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(
                names.filter(function (n) { return n !== CACHE_NAME; })
                     .map(function (n) { return caches.delete(n); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

// Fetch — network-first for app files, cache as offline fallback
self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') return;
    // Never intercept API calls
    if (event.request.url.includes('/wp-json/')) return;
    if (event.request.url.includes('admin-ajax.php')) return;
    // Skip non-http(s) schemes (e.g. chrome-extension://)
    if (!event.request.url.startsWith('http')) return;

    var requestUrl = new URL(event.request.url);
    if (requestUrl.origin !== self.location.origin) return;
    if (requestUrl.pathname.indexOf(APP_SCOPE) !== 0) return;

    event.respondWith(
        fetch(event.request).then(function (response) {
            var cacheControl = response.headers.get('Cache-Control') || '';
            var shouldCache = response.status === 200
                && cacheControl.indexOf('no-store') === -1
                && cacheControl.indexOf('no-cache') === -1
                && cacheControl.indexOf('private') === -1;

            if (shouldCache) {
                var clone = response.clone();
                caches.open(CACHE_NAME).then(function (cache) {
                    cache.put(event.request, clone);
                });
            }
            return response;
        }).catch(function () {
            return caches.match(event.request).then(function (cachedResponse) {
                if (cachedResponse) {
                    return cachedResponse;
                }

                if (event.request.mode === 'navigate') {
                    return caches.match(APP_SCOPE, { ignoreSearch: true }).then(function (appShell) {
                        return appShell || buildOfflineResponse();
                    });
                }

                return buildOfflineResponse();
            });
        })
    );
});

// Push notification handler
self.addEventListener('push', function (event) {
    var data = { title: 'New Message', body: 'You have a new chat message', data: {} };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    var options = {
        body: data.body,
        icon: data.icon || '',
        badge: data.icon || '',
        data: data.data || {},
        vibrate: [200, 100, 200],
        actions: [
            { action: 'view', title: 'View Conversation' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click handler — open PWA to the conversation
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var sessionId = event.notification.data && event.notification.data.session_id
        ? event.notification.data.session_id : '';

    var url = './';
    if (sessionId) {
        url += '#conversation/' + sessionId;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // If PWA is already open, focus it and navigate
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if ('focus' in client) {
                    return client.focus().then(function (focusedClient) {
                        if ('navigate' in focusedClient && sessionId) {
                            return focusedClient.navigate(url).then(function () {
                                focusedClient.postMessage({
                                    type: 'NAVIGATE_CONVERSATION',
                                    session_id: sessionId
                                });
                            });
                        }

                        focusedClient.postMessage({
                            type: 'NAVIGATE_CONVERSATION',
                            session_id: sessionId
                        });
                    });
                }
            }
            // Otherwise open a new window
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
