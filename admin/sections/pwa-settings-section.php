<?php
if (!defined('ABSPATH')) {
    exit;
}

function wpiko_chatbot_pwa_settings_section() {
    // Handle form submission
    if (isset($_POST['action']) && $_POST['action'] === 'save_pwa_settings') {
        check_admin_referer('save_pwa_settings', 'pwa_settings_nonce');

        if (wpiko_chatbot_pro_is_license_active()) {
            $enable_pwa = isset($_POST['wpiko_chatbot_enable_pwa']) ? '1' : '0';
            update_option('wpiko_chatbot_enable_pwa', $enable_pwa);

            if (isset($_POST['wpiko_chatbot_pwa_app_name'])) {
                update_option('wpiko_chatbot_pwa_app_name', sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_pwa_app_name'])));
            }

            update_option(
                'wpiko_chatbot_takeover_header_prefix',
                sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_takeover_header_prefix'] ?? 'Live chat with'))
            );
            update_option(
                'wpiko_chatbot_takeover_join_text',
                sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_takeover_join_text'] ?? 'joined the conversation.'))
            );
            update_option(
                'wpiko_chatbot_takeover_release_text',
                sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_takeover_release_text'] ?? 'left the conversation. AI assistant is back.'))
            );

            $enable_push = isset($_POST['wpiko_chatbot_enable_push']) ? '1' : '0';
            update_option('wpiko_chatbot_enable_push', $enable_push);
            update_option('wpiko_chatbot_pwa_push_enabled', $enable_push);

            // Generate VAPID keys if enabling push and they don't exist yet
            if ($enable_push === '1' && empty(get_option('wpiko_chatbot_vapid_public_key', ''))) {
                if (function_exists('wpiko_chatbot_pro_generate_vapid_keys')) {
                    wpiko_chatbot_pro_generate_vapid_keys();
                }
            }

            // Re-register the PWA route before flushing so the updated toggle state
            // is reflected in the persisted rewrite rules for this same request.
            if (function_exists('wpiko_chatbot_pro_pwa_flush_rules')) {
                wpiko_chatbot_pro_pwa_flush_rules();
            } else {
                flush_rewrite_rules();
            }

            echo '<div class="updated"><p>PWA settings updated successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>PWA settings cannot be saved without an active license.</p></div>';
        }
    }

    $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
    $is_license_active = wpiko_chatbot_pro_is_license_active();
    $is_license_expired = $license_status === 'expired';
    $enable_pwa = get_option('wpiko_chatbot_enable_pwa', '0');
    $pwa_app_name = get_option('wpiko_chatbot_pwa_app_name', get_bloginfo('name') . ' Chat');
    $takeover_header_prefix = get_option('wpiko_chatbot_takeover_header_prefix', 'Live chat with');
    $takeover_join_text = get_option('wpiko_chatbot_takeover_join_text', 'joined the conversation.');
    $takeover_release_text = get_option('wpiko_chatbot_takeover_release_text', 'left the conversation. AI assistant is back.');
    $enable_push = get_option('wpiko_chatbot_pwa_push_enabled', get_option('wpiko_chatbot_enable_push', '0'));
    $pwa_url = home_url('/wpiko-app/');
    global $wpdb;
    $subscription_count = function_exists('wpiko_chatbot_pro_count_push_subscriptions')
        ? wpiko_chatbot_pro_count_push_subscriptions()
        : (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}wpiko_chatbot_push_subscriptions`");
    $subscriptions = function_exists('wpiko_chatbot_pro_get_push_subscriptions')
        ? wpiko_chatbot_pro_get_push_subscriptions()
        : array();
    ?>

    <div class="pwa-settings-section">
        <div class="pwa-settings-header">
            <h2>
                <span class="dashicons dashicons-smartphone"></span>
                Mobile App (PWA)
                <?php if (!$is_license_active): ?>
                    <span class="premium-feature-badge">Premium</span>
                <?php endif; ?>
            </h2>
            <p class="description">Access conversations and reply to users directly from your phone as an Administrator or WPiko Agent.</p>
        </div>

        <?php if ($is_license_active): ?>
            <form method="post" action="">
                <input type="hidden" name="action" value="save_pwa_settings">
                <?php wp_nonce_field('save_pwa_settings', 'pwa_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Enable PWA</th>
                        <td>
                            <label class="wpiko-switch">
                                <input type="checkbox" id="wpiko_chatbot_enable_pwa" name="wpiko_chatbot_enable_pwa" value="1"
                                       <?php checked($enable_pwa, '1'); ?>>
                                <span class="wpiko-slider round"></span>
                            </label>
                            <label for="wpiko_chatbot_enable_pwa">Enable the mobile web app</label>
                            <p class="description">When enabled, Administrators and WPiko Agents can access the chat app at the URL below.</p>
                        </td>
                    </tr>
                </table>

                <div class="pwa-settings-collapsible <?php echo $enable_pwa === '1' ? 'active' : ''; ?>">
                    <div class="wpiko-accordion-item">
                        <h3 class="collapsible-header active">
                            <span><span class="dashicons dashicons-admin-settings"></span> General Settings</span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </h3>
                        <div class="collapsible-content active">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">App URL</th>
                                    <td>
                                        <code><?php echo esc_html($pwa_url); ?></code>
                                        <p class="description">Open this URL on your phone and add it to your home screen.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">App Name</th>
                                    <td>
                                        <input type="text" name="wpiko_chatbot_pwa_app_name" class="regular-text"
                                               value="<?php echo esc_attr($pwa_app_name); ?>">
                                        <p class="description">Shown on the home screen icon.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Push Notifications</th>
                                    <td>
                                        <label class="wpiko-switch">
                                            <input type="checkbox" id="wpiko_chatbot_enable_push" name="wpiko_chatbot_enable_push" value="1"
                                                   <?php checked($enable_push, '1'); ?>>
                                            <span class="wpiko-slider round"></span>
                                        </label>
                                        <label for="wpiko_chatbot_enable_push">Enable push notifications for new messages</label>
                                        <p class="description">Get notified on your phone when a visitor sends a message. VAPID keys are generated automatically.</p>
                                        <p class="description" style="margin-top: 8px; color: #826200; background: #fff8e1; border-left: 4px solid #ffb300; padding: 8px 12px;">
                                            <strong>Delivery timing note:</strong> Web push notifications are delivered through Apple (APNs) and Google (FCM) services, which may sometimes delay delivery by a few minutes depending on your device's battery state, power-saving mode, network conditions, and OS-level notification batching. This is a platform limitation that affects all PWA-based apps — not something that can be controlled from the server side. Notifications sent while the app is actively open will always appear instantly.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Registered Devices</th>
                                    <td>
                                        <strong id="wpiko-registered-device-count"><?php echo esc_html((string) $subscription_count); ?></strong>
                                        <p class="description">Devices with push notifications enabled in the Mobile App settings.</p>
                                        <p class="description">Subscriptions are removed automatically only when the push provider reports the endpoint as expired or gone, such as HTTP 404 or 410. Devices that are simply offline are kept and can be removed manually if the app was deleted without turning push off first.</p>
                                        <div class="wpiko-registered-devices-wrap" style="margin-top: 12px; max-width: 980px;">
                                            <table class="widefat striped" id="wpiko-registered-devices-table" <?php echo empty($subscriptions) ? 'style="display:none;"' : ''; ?>>
                                                <thead>
                                                    <tr>
                                                        <th>Owner</th>
                                                        <th>Device</th>
                                                        <th>Last Seen</th>
                                                        <th>Last Push Result</th>
                                                        <th>Registered</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($subscriptions as $subscription): ?>
                                                        <tr data-subscription-id="<?php echo esc_attr((string) $subscription['id']); ?>">
                                                            <td><?php echo esc_html($subscription['user_name']); ?></td>
                                                            <td>
                                                                <strong><?php echo esc_html($subscription['device_label']); ?></strong><br>
                                                                <span class="description"><?php echo esc_html($subscription['endpoint_host'] . ' / ' . $subscription['endpoint_reference']); ?></span>
                                                            </td>
                                                            <td><?php echo esc_html($subscription['last_seen_text']); ?></td>
                                                            <td><?php echo esc_html($subscription['last_delivery_text']); ?></td>
                                                            <td><?php echo esc_html($subscription['created_at_text']); ?></td>
                                                            <td>
                                                                <button type="button" class="button-link-delete wpiko-remove-device" data-subscription-id="<?php echo esc_attr((string) $subscription['id']); ?>">Remove</button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <p class="description" id="wpiko-registered-devices-empty" <?php echo !empty($subscriptions) ? 'style="display:none;"' : ''; ?>>No registered devices yet.</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Test Push Notification</th>
                                    <td class="wpiko-test-push-cell">
                                        <div class="wpiko-test-push-controls">
                                            <button type="button" class="button button-primary" id="wpiko-send-test-push"
                                                    <?php echo $enable_push !== '1' ? 'disabled' : ''; ?>>Send Test Notification</button>
                                            <p class="description wpiko-test-push-description">Send a test notification to verify that your device is set up correctly.</p>
                                            <div id="wpiko-test-push-status" class="wpiko-inline-status" aria-live="polite" role="status"></div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="wpiko-accordion-item">
                        <h3 class="collapsible-header">
                            <span><span class="dashicons dashicons-edit"></span> Customizable Text</span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </h3>
                        <div class="collapsible-content">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Header Text</th>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            <input type="text" name="wpiko_chatbot_takeover_header_prefix" class="regular-text"
                                                   value="<?php echo esc_attr($takeover_header_prefix); ?>" style="flex: 1;">
                                            Agent name
                                        </div>
                                        <p class="description">Shown in the chatbot header during admin takeover. Example: <em><?php echo esc_html($takeover_header_prefix); ?> John</em></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Agent Joined Text</th>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            Agent name
                                            <input type="text" name="wpiko_chatbot_takeover_join_text" class="regular-text"
                                                   value="<?php echo esc_attr($takeover_join_text); ?>" style="flex: 1;">
                                        </div>
                                        <p class="description">Appended automatically after the agent name. Example: <em>John <?php echo esc_html($takeover_join_text); ?></em></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Agent Left Text</th>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            Agent name
                                            <input type="text" name="wpiko_chatbot_takeover_release_text" class="regular-text"
                                                   value="<?php echo esc_attr($takeover_release_text); ?>" style="flex: 1;">
                                        </div>
                                        <p class="description">Appended automatically after the agent name. Example: <em>John <?php echo esc_html($takeover_release_text); ?></em></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="wpiko-accordion-item">
                        <h3 class="collapsible-header">
                            <span><span class="dashicons dashicons-info"></span> Quick Setup Guide</span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </h3>
                        <div class="collapsible-content">
                            <div class="instruction-content pwa-setup-guide">
                                <p>Use this checklist for the fastest setup flow before installing the Mobile App on a phone or tablet.</p>

                                <div class="wpiko-pwa-guide-block">
                                    <h4>Quick setup steps</h4>
                                    <ol>
                                        <li>Log in as an <strong>Administrator</strong> or <strong>WPiko Agent</strong> and create an <strong>Application Password</strong> from <strong>WordPress &rarr; Users &rarr; Profile</strong>.</li>
                                        <li>Open <code><?php echo esc_html($pwa_url); ?></code> on your phone.</li>
                                        <li>Add the app to your home screen from the browser menu. On iPhone/iPad use <strong>Add to Home Screen</strong> in Safari, and on Android look for <strong>Install app</strong> or <strong>Add to Home screen</strong> in Chrome.</li>
                                        <li>Sign in with your <strong>WordPress username</strong> and the <strong>Application Password</strong>.</li>
                                        <li>Open the installed app and enable <strong>Push Notifications</strong> from the app settings if you want alerts for new visitor messages.</li>
                                    </ol>
                                </div>

                                <div class="wpiko-pwa-guide-block">
                                    <h4>Access and permissions</h4>
                                    <ul class="wpiko-pwa-requirements-list">
                                        <li><strong>Administrator</strong> and <strong>WPiko Agent</strong> users can sign in to the Mobile App.</li>
                                        <li><strong>WPiko Agent</strong> can view conversations, send replies, take over chats, release takeover, and manage push notifications.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wpiko-accordion-item">
                        <h3 class="collapsible-header">
                            <span><span class="dashicons dashicons-smartphone"></span> Install Guide for iPhone & Android</span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </h3>
                        <div class="collapsible-content">
                            <div class="instruction-content wpiko-pwa-install-guide">
                                <p>Use the instructions below to add the Mobile App to a phone or tablet and review the main requirements before sharing it with your team.</p>

                                <div class="wpiko-pwa-guide-block">
                                    <h4>Adding Web Apps on iPhone/iPad (Safari)</h4>
                                    <ol>
                                        <li>Open <code><?php echo esc_html($pwa_url); ?></code> in <strong>Safari</strong>.</li>
                                        <li>Tap the <strong>Share</strong> button.</li>
                                        <li>Select <strong>Add to Home Screen</strong>. If you do not see it, scroll down, tap <strong>Edit Actions</strong>, and add it first.</li>
                                        <li>Confirm the app name and tap <strong>Add</strong>.</li>
                                        <li>Sign in with your <strong>WordPress username</strong> and <strong>Application Password</strong>.</li>
                                        <li>Open the new home screen icon and enable <strong>Push Notifications</strong> from the app settings if you want push alerts.</li>
                                    </ol>
                                    <p>Apple requires manual installation from Safari on iPhone and iPad. There is no automatic install prompt like the one available on some Android devices.</p>
                                </div>

                                <div class="wpiko-pwa-guide-block">
                                    <h4>Adding Web Apps on Android (Chrome)</h4>
                                    <ol>
                                        <li>Open <code><?php echo esc_html($pwa_url); ?></code> in <strong>Chrome</strong>.</li>
                                        <li>Open the Chrome menu.</li>
                                        <li>Tap <strong>Install app</strong> or <strong>Add to Home screen</strong>. The wording can vary by Android device and Chrome version.</li>
                                        <li>Confirm the install prompt.</li>
                                        <li>Sign in with your <strong>WordPress username</strong> and <strong>Application Password</strong>.</li>
                                        <li>Open the installed app from your home screen or app launcher, then enable <strong>Push Notifications</strong> from the app settings if you want push alerts.</li>
                                    </ol>
                                    <p>Chrome on Android may install the app with a more app-like experience, but the exact wording and prompt can differ slightly by device.</p>
                                </div>

                                <div class="wpiko-pwa-guide-block">
                                    <h4>Requirements and limitations</h4>
                                    <ul class="wpiko-pwa-requirements-list">
                                        <li><strong>Use the correct browser:</strong> Safari is recommended on iPhone/iPad, and Chrome is recommended on Android.</li>
                                        <li><strong>Application Password required:</strong> admins and WPiko Agents should sign in with a WordPress username plus an Application Password, not their normal site password.</li>
                                        <li><strong>Push notifications require user permission:</strong> users must allow notifications on their phone after opening the installed app.</li>
                                        <li><strong>iPhone/iPad installs are isolated:</strong> the installed app can have a separate session from Safari, so logging in again after installation is normal.</li>
                                        <li><strong>Install wording varies on Android:</strong> some devices show <em>Install app</em> and others show <em>Add to Home screen</em>.</li>
                                        <li><strong>Capability differences exist across platforms:</strong> iPhone/iPad home screen web apps support fewer PWA features than Android, so the experience may not be identical on every device.</li>
                                        <li><strong>Internet connection still matters:</strong> live conversations, takeover, and push delivery still depend on network access and site availability.</li>
                                        <li><strong>Push notifications may arrive with a short delay:</strong> unlike native apps, web push notifications on both iPhone and Android are delivered through Apple (APNs) and Google (FCM) push services which may delay delivery by a few minutes depending on device battery state, network conditions, and system load. This is a platform limitation that affects all PWA-based apps and cannot be controlled from the server side. To minimize delays: keep the device connected to Wi-Fi, disable battery optimization/Low Power Mode for the browser, and avoid force-closing the browser app.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>
        <?php elseif ($is_license_expired): ?>
            <div class="premium-feature-notice pwa-settings-notice">
                <h3>📱 Mobile App Disabled</h3>
                <p>Your license has expired. The Mobile App (PWA) feature is currently unavailable.</p>
                <p>Renew your license to regain access to these features:</p>
                <ul>
                    <li>📲 Install the chatbot as a mobile app on your phone</li>
                    <li>💬 Reply to conversations away from your desktop</li>
                    <li>🔔 Receive push notifications for new visitor messages</li>
                    <li>🛠️ See live agent join and leave notices in conversations</li>
                    <li>🧪 Send test notifications to verify device setup</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Renew License</a>
            </div>
        <?php else: ?>
            <div class="premium-feature-notice pwa-settings-notice">
                <h3>📱 Unlock Mobile App</h3>
                <p>Upgrade to Premium to manage chatbot conversations directly from your phone:</p>
                <ul>
                    <li>📲 Install the chatbot as a mobile app on your phone</li>
                    <li>💬 Reply to conversations away from your desktop</li>
                    <li>🔔 Receive push notifications for new visitor messages</li>
                    <li>🛠️ See live agent join and leave notices in conversations</li>
                    <li>🧪 Send test notifications to verify device setup</li>
                </ul>
                <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Upgrade to Premium</a>
            </div>
        <?php endif; ?>

        <?php if ($enable_pwa === '1' && $is_license_active): ?>
        <script>
        (function () {
            var button = document.getElementById('wpiko-send-test-push');
            var statusEl = document.getElementById('wpiko-test-push-status');
            var devicesTable = document.getElementById('wpiko-registered-devices-table');
            var devicesTableBody = devicesTable ? devicesTable.querySelector('tbody') : null;
            var devicesEmptyEl = document.getElementById('wpiko-registered-devices-empty');
            var deviceCountEl = document.getElementById('wpiko-registered-device-count');
            var adminAjaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            var testPushNonce = '<?php echo esc_js(wp_create_nonce('wpiko_chatbot_send_test_push')); ?>';
            var managePushNonce = '<?php echo esc_js(wp_create_nonce('wpiko_chatbot_manage_push_subscriptions')); ?>';
            if (!button || !statusEl) {
                return;
            }

            function setPushStatus(type, message) {
                statusEl.classList.remove('success', 'error', 'info');

                if (!message) {
                    statusEl.textContent = '';
                    return;
                }

                statusEl.textContent = message;
                statusEl.classList.add(type);
            }

            function getRegisteredDeviceRowCount() {
                return devicesTableBody ? devicesTableBody.querySelectorAll('tr[data-subscription-id]').length : 0;
            }

            function parseCount(value) {
                var count = parseInt(value, 10);
                return isNaN(count) ? null : count;
            }

            function updateRegisteredDevicesUi(nextCount) {
                var count = typeof nextCount === 'number' && !isNaN(nextCount)
                    ? nextCount
                    : getRegisteredDeviceRowCount();

                if (deviceCountEl) {
                    deviceCountEl.textContent = String(Math.max(count, 0));
                }

                if (devicesTable) {
                    devicesTable.style.display = count > 0 ? '' : 'none';
                }

                if (devicesEmptyEl) {
                    devicesEmptyEl.style.display = count > 0 ? 'none' : '';
                }
            }

            function removeRegisteredDeviceRows(ids) {
                if (devicesTableBody && Array.isArray(ids)) {
                    ids.forEach(function (id) {
                        var row = devicesTableBody.querySelector('tr[data-subscription-id="' + String(id) + '"]');
                        if (row) {
                            row.remove();
                        }
                    });
                }

                updateRegisteredDevicesUi();
            }

            function applyPushResponseDetails(details) {
                if (!details) {
                    return;
                }

                if (Array.isArray(details.removed_subscription_ids) && details.removed_subscription_ids.length) {
                    removeRegisteredDeviceRows(details.removed_subscription_ids);
                }

                if (typeof details.remaining_subscription_count !== 'undefined') {
                    var remainingCount = parseCount(details.remaining_subscription_count);
                    if (remainingCount !== null) {
                        updateRegisteredDevicesUi(remainingCount);
                    }
                }
            }

            button.addEventListener('click', function () {
                button.disabled = true;
                setPushStatus('info', 'Sending...');

                var body = new URLSearchParams();
                body.append('action', 'wpiko_chatbot_send_test_push');
                body.append('nonce', testPushNonce);

                fetch(adminAjaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString(),
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        var payload = result.data && result.data.data ? result.data.data : {};
                        applyPushResponseDetails(payload.details || null);

                        if (typeof payload.subscription_count !== 'undefined') {
                            var currentCount = parseCount(payload.subscription_count);
                            if (currentCount !== null) {
                                updateRegisteredDevicesUi(currentCount);
                            }
                        }

                        var isSuccess = !!(result.data && result.data.success);
                        var message = payload && payload.message
                            ? payload.message
                            : (result.data && result.data.success ? 'Test notification sent.' : 'Failed to send test notification.');

                        setPushStatus(isSuccess ? 'success' : 'error', message);
                    })
                    .catch(function () {
                        setPushStatus('error', 'Failed to send test notification.');
                    })
                    .finally(function () {
                        button.disabled = <?php echo $enable_push !== '1' ? 'true' : 'false'; ?>;
                    });
            });

            if (devicesTableBody) {
                devicesTableBody.addEventListener('click', function (event) {
                    var target = event.target;

                    if (!target || !target.classList.contains('wpiko-remove-device')) {
                        return;
                    }

                    event.preventDefault();

                    var subscriptionId = target.getAttribute('data-subscription-id');
                    if (!subscriptionId) {
                        return;
                    }

                    if (!window.confirm('Remove this device from push notifications?')) {
                        return;
                    }

                    target.disabled = true;
                    setPushStatus('info', 'Removing device...');

                    var body = new URLSearchParams();
                    body.append('action', 'wpiko_chatbot_remove_push_subscription');
                    body.append('nonce', managePushNonce);
                    body.append('subscription_id', subscriptionId);

                    fetch(adminAjaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: body.toString(),
                        credentials: 'same-origin'
                    })
                        .then(function (response) {
                            return response.json().then(function (data) {
                                return { ok: response.ok, data: data };
                            });
                        })
                        .then(function (result) {
                            var isSuccess = !!(result.data && result.data.success);
                            var payload = result.data && result.data.data ? result.data.data : {};
                            var message = payload && payload.message
                                ? payload.message
                                : (isSuccess ? 'Device removed.' : 'Failed to remove the device.');

                            if (!isSuccess) {
                                setPushStatus('error', message);
                                return;
                            }

                            removeRegisteredDeviceRows([subscriptionId]);

                            if (typeof payload.subscription_count !== 'undefined') {
                                var count = parseCount(payload.subscription_count);
                                if (count !== null) {
                                    updateRegisteredDevicesUi(count);
                                }
                            }

                            setPushStatus('success', message);
                        })
                        .catch(function () {
                            setPushStatus('error', 'Failed to remove the device.');
                        })
                        .finally(function () {
                            if (document.body.contains(target)) {
                                target.disabled = false;
                            }
                        });
                });
            }

            updateRegisteredDevicesUi(<?php echo (int) $subscription_count; ?>);

            var enablePwaCheckbox = document.getElementById('wpiko_chatbot_enable_pwa');
            var pwaSettingsCollapsible = document.querySelector('.pwa-settings-collapsible');
            var pushCheckbox = document.getElementById('wpiko_chatbot_enable_push');
            var savedPushEnabled = pushCheckbox ? pushCheckbox.checked : false;
            var accordionHeaders = document.querySelectorAll('.pwa-settings-section .collapsible-header');

            if (enablePwaCheckbox && pwaSettingsCollapsible) {
                enablePwaCheckbox.addEventListener('change', function () {
                    pwaSettingsCollapsible.classList.toggle('active', enablePwaCheckbox.checked);
                });
            }

            if (pushCheckbox && button) {
                pushCheckbox.addEventListener('change', function () {
                    if (pushCheckbox.checked !== savedPushEnabled) {
                        button.disabled = true;
                        setPushStatus('', '');
                    } else {
                        button.disabled = !savedPushEnabled;
                        setPushStatus('', '');
                    }
                });
            }

            accordionHeaders.forEach(function (header) {
                header.addEventListener('click', function () {
                    header.classList.toggle('active');
                    header.nextElementSibling.classList.toggle('active');
                });
            });
        }());
        </script>
        <?php else: ?>
        <script>
        (function () {
            var enablePwaCheckbox = document.getElementById('wpiko_chatbot_enable_pwa');
            var pwaSettingsCollapsible = document.querySelector('.pwa-settings-collapsible');
            var accordionHeaders = document.querySelectorAll('.pwa-settings-section .collapsible-header');

            if (enablePwaCheckbox && pwaSettingsCollapsible) {
                enablePwaCheckbox.addEventListener('change', function () {
                    pwaSettingsCollapsible.classList.toggle('active', enablePwaCheckbox.checked);
                });
            }

            accordionHeaders.forEach(function (header) {
                header.addEventListener('click', function () {
                    header.classList.toggle('active');
                    header.nextElementSibling.classList.toggle('active');
                });
            });
        }());
        </script>
        <?php endif; ?>
    </div>
    <?php
}
