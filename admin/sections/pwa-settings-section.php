<?php
if (!defined('ABSPATH')) {
    exit;
}

function wpiko_chatbot_pwa_settings_section() {
    $settings_saved = false;
    $license_error = false;

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

            $settings_saved = true;
        } else {
            $license_error = true;
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
    <div class="wpiko-chatbot-settings-page wpiko-mobile-app-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>Mobile App<?php if (!$is_license_active): ?> <span class="mobile-app-pro-badge">PRO</span><?php endif; ?></h1>
            <p class="wpiko-chatbot-settings-subtitle">Access conversations and reply from your phone as an Administrator or Live Agent.</p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p>PWA settings updated successfully.</p></div>
        <?php endif; ?>
        <?php if ($license_error): ?>
            <div class="wpiko-chatbot-settings-notice is-error" role="alert"><p>PWA settings cannot be saved without an active license.</p></div>
        <?php endif; ?>
        <?php if ($is_license_active): ?>
            <form method="post" action="" class="wpiko-chatbot-settings-form">
                <input type="hidden" name="action" value="save_pwa_settings">
                <?php wp_nonce_field('save_pwa_settings', 'pwa_settings_nonce'); ?>
                <section class="wpiko-chatbot-settings-card" aria-labelledby="mobile-app-settings-title">
                    <h2 class="wpiko-chatbot-settings-card-title" id="mobile-app-settings-title">App settings</h2>
                    <div class="wpiko-chatbot-settings-field-row">
                        <div class="wpiko-chatbot-settings-field-label">Mobile web app</div>
                        <div class="wpiko-chatbot-settings-field-control">
                            <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_enable_pwa">
                                <input type="checkbox" role="switch" id="wpiko_chatbot_enable_pwa" name="wpiko_chatbot_enable_pwa" value="1" aria-controls="mobile-app-options" aria-describedby="mobile-app-enabled-description" <?php checked($enable_pwa, '1'); ?>>
                                <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                                <span class="wpiko-chatbot-settings-switch__label">Enable the mobile web app</span>
                            </label>
                            <p class="wpiko-chatbot-settings-field-description" id="mobile-app-enabled-description">Let Administrators and Live Agents access the chat app on a phone or tablet.</p>
                        </div>
                    </div>
                </section>
                <div class="wpiko-chatbot-settings-content" id="mobile-app-options" <?php if ($enable_pwa !== '1'): ?>hidden<?php endif; ?>>
                    <section class="wpiko-chatbot-settings-card" aria-labelledby="mobile-app-general-title">
                        <h2 class="wpiko-chatbot-settings-card-title" id="mobile-app-general-title">General settings</h2>
                        <div class="wpiko-chatbot-settings-field-row">
                            <div class="wpiko-chatbot-settings-field-label">App URL</div>
                            <div class="wpiko-chatbot-settings-field-control">
                                <code class="wpiko-chatbot-settings-code"><?php echo esc_html($pwa_url); ?></code>
                                <p class="wpiko-chatbot-settings-field-description">Open this URL on your phone and add it to your home screen.</p>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_pwa_app_name">App name</label>
                            <div class="wpiko-chatbot-settings-field-control">
                                <input type="text" id="wpiko_chatbot_pwa_app_name" name="wpiko_chatbot_pwa_app_name" value="<?php echo esc_attr($pwa_app_name); ?>" aria-describedby="mobile-app-name-description">
                                <p class="wpiko-chatbot-settings-field-description" id="mobile-app-name-description">Shown on the home screen icon.</p>
                            </div>
                        </div>
                    </section>
                    <section class="wpiko-chatbot-settings-card" aria-labelledby="mobile-app-push-title">
                        <h2 class="wpiko-chatbot-settings-card-title" id="mobile-app-push-title">Push notifications</h2>
                        <div class="wpiko-chatbot-settings-field-row">
                            <div class="wpiko-chatbot-settings-field-label">New message alerts</div>
                            <div class="wpiko-chatbot-settings-field-control">
                                <label class="wpiko-chatbot-settings-switch" for="wpiko_chatbot_enable_push">
                                    <input type="checkbox" role="switch" id="wpiko_chatbot_enable_push" name="wpiko_chatbot_enable_push" value="1" aria-describedby="mobile-app-push-description" <?php checked($enable_push, '1'); ?>>
                                    <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                                    <span class="wpiko-chatbot-settings-switch__label">Enable push notifications for new messages</span>
                                </label>
                                <p class="wpiko-chatbot-settings-field-description" id="mobile-app-push-description">Get notified on your phone when a visitor sends a message. VAPID keys are generated automatically.</p>
                                <details class="mobile-app-delivery-note">
                                    <summary>About notification delivery</summary>
                                    <p>Web push notifications are delivered through Apple (APNs) and Google (FCM) services, which may sometimes delay delivery by a few minutes depending on your device's battery state, power-saving mode, network conditions, and OS-level notification batching. This is a platform limitation that affects all PWA-based apps — not something that can be controlled from the server side. Notifications sent while the app is actively open will always appear instantly.</p>
                                </details>
                            </div>
                        </div>
                        <div class="wpiko-chatbot-settings-field-row">
                            <div class="wpiko-chatbot-settings-field-label">Test notification</div>
                            <div class="wpiko-chatbot-settings-field-control wpiko-test-push-controls">
                                <button type="button" class="button" id="wpiko-send-test-push" <?php echo $enable_push !== '1' ? 'disabled' : ''; ?>>Send Test Notification</button>
                                <p class="wpiko-chatbot-settings-field-description">Send a test notification to verify that your device is set up correctly.</p>
                                <div id="wpiko-test-push-status" class="wpiko-inline-status" aria-live="polite" role="status"></div>
                            </div>
                        </div>
                    </section>
                    <section class="wpiko-chatbot-settings-card" aria-labelledby="mobile-app-devices-title">
                        <div class="mobile-app-devices-header">
                            <h2 class="wpiko-chatbot-settings-card-title" id="mobile-app-devices-title">Registered devices</h2>
                            <span class="mobile-app-device-count" aria-label="Registered device count"><strong id="wpiko-registered-device-count"><?php echo esc_html((string) $subscription_count); ?></strong> devices</span>
                        </div>
                        <p class="wpiko-chatbot-settings-field-description">Devices with push notifications enabled in the Mobile App settings.</p>
                        <p class="wpiko-chatbot-settings-field-description mobile-app-device-help">Subscriptions are removed automatically only when the push provider reports the endpoint as expired or gone, such as HTTP 404 or 410. Devices that are simply offline are kept and can be removed manually if the app was deleted without turning push off first.</p>
                        <div class="wpiko-registered-devices-wrap" role="region" aria-label="Registered devices" tabindex="0">
<table class="widefat" id="wpiko-registered-devices-table" <?php echo empty($subscriptions) ? 'style="display:none;"' : ''; ?>>
                                                <caption class="screen-reader-text">Registered push notification devices</caption>
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
                                                                <span class="wpiko-chatbot-settings-field-description"><?php echo esc_html($subscription['endpoint_host'] . ' / ' . $subscription['endpoint_reference']); ?></span>
                                                            </td>
                                                            <td><?php echo esc_html($subscription['last_seen_text']); ?></td>
                                                            <td><?php echo esc_html($subscription['last_delivery_text']); ?></td>
                                                            <td><?php echo esc_html($subscription['created_at_text']); ?></td>
                                                            <td>
                                                                <button type="button" class="button wpiko-chatbot-settings-danger wpiko-remove-device" data-subscription-id="<?php echo esc_attr((string) $subscription['id']); ?>">Remove</button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                            <p class="wpiko-chatbot-settings-field-description mobile-app-empty-devices" id="wpiko-registered-devices-empty" <?php echo !empty($subscriptions) ? 'style="display:none;"' : ''; ?>>No registered devices yet.</p>
                        </div>
                    </section>
                    <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure">
                        <summary class="wpiko-chatbot-settings-disclosure-title">Customizable text</summary>
                        <div class="wpiko-chatbot-settings-disclosure-content">
                            <div class="wpiko-chatbot-settings-field-row">
                                <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_takeover_header_prefix">Header text</label>
                                <div class="wpiko-chatbot-settings-field-control">
                                    <div class="mobile-app-text-template"><input type="text" id="wpiko_chatbot_takeover_header_prefix" name="wpiko_chatbot_takeover_header_prefix" value="<?php echo esc_attr($takeover_header_prefix); ?>" aria-describedby="wpiko_chatbot_takeover_header_prefix-description"><span class="mobile-app-agent-placeholder">Agent name</span></div>
                                    <p class="wpiko-chatbot-settings-field-description" id="wpiko_chatbot_takeover_header_prefix-description">Shown in the chatbot header during admin takeover. Example: <em><?php echo esc_html($takeover_header_prefix); ?> John</em></p>
                                </div>
                            </div>
                            <div class="wpiko-chatbot-settings-field-row">
                                <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_takeover_join_text">Agent joined text</label>
                                <div class="wpiko-chatbot-settings-field-control">
                                    <div class="mobile-app-text-template"><span class="mobile-app-agent-placeholder">Agent name</span><input type="text" id="wpiko_chatbot_takeover_join_text" name="wpiko_chatbot_takeover_join_text" value="<?php echo esc_attr($takeover_join_text); ?>" aria-describedby="wpiko_chatbot_takeover_join_text-description"></div>
                                    <p class="wpiko-chatbot-settings-field-description" id="wpiko_chatbot_takeover_join_text-description">Appended automatically after the agent name when an agent joins. Example: <em>John <?php echo esc_html($takeover_join_text); ?></em></p>
                                </div>
                            </div>
                            <div class="wpiko-chatbot-settings-field-row">
                                <label class="wpiko-chatbot-settings-field-label" for="wpiko_chatbot_takeover_release_text">Agent left text</label>
                                <div class="wpiko-chatbot-settings-field-control">
                                    <div class="mobile-app-text-template"><span class="mobile-app-agent-placeholder">Agent name</span><input type="text" id="wpiko_chatbot_takeover_release_text" name="wpiko_chatbot_takeover_release_text" value="<?php echo esc_attr($takeover_release_text); ?>" aria-describedby="wpiko_chatbot_takeover_release_text-description"></div>
                                    <p class="wpiko-chatbot-settings-field-description" id="wpiko_chatbot_takeover_release_text-description">Appended automatically after the agent name when an agent leaves. Example: <em>John <?php echo esc_html($takeover_release_text); ?></em> Also shown when nobody from the team has replied to or opened the conversation for 12 hours: the visitor's next visit or message then hands it back to the AI.</p>
                                </div>
                            </div>
                        </div>
                    </details>
                <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure">
                    <summary class="wpiko-chatbot-settings-disclosure-title">Quick setup guide</summary>
                    <div class="wpiko-chatbot-settings-disclosure-content">
<div class="mobile-app-guide pwa-setup-guide">
                                <p>Use this checklist for the fastest setup flow before installing the Mobile App on a phone or tablet.</p>

                                <div class="wpiko-pwa-guide-block">
                                    <h4>Quick setup steps</h4>
                                    <ol>
                                        <li>Log in as an <strong>Administrator</strong> or <strong>Live Agent</strong> and create an <strong>Application Password</strong> from <strong>WordPress &rarr; Users &rarr; Profile</strong>.</li>
                                        <li>Open <code><?php echo esc_html($pwa_url); ?></code> on your phone.</li>
                                        <li>Add the app to your home screen from the browser menu. On iPhone/iPad use <strong>Add to Home Screen</strong> in Safari, and on Android look for <strong>Install app</strong> or <strong>Add to Home screen</strong> in Chrome.</li>
                                        <li>Sign in with your <strong>WordPress username</strong> and the <strong>Application Password</strong>.</li>
                                        <li>Open the installed app and enable <strong>Push Notifications</strong> from the app settings if you want alerts for new visitor messages.</li>
                                    </ol>
                                </div>

                                <div class="wpiko-pwa-guide-block">
                                    <h4>Access and permissions</h4>
                                    <ul class="wpiko-pwa-requirements-list">
                                        <li><strong>Administrator</strong> and <strong>Live Agent</strong> users can sign in to the Mobile App.</li>
                                        <li><strong>Live Agent</strong> can view conversations, send replies, take over chats, release takeover, and manage push notifications.</li>
                                    </ul>
                                </div>
                            </div>
                    </div>
                </details>
                <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure">
                    <summary class="wpiko-chatbot-settings-disclosure-title">Install guide for iPhone & Android</summary>
                    <div class="wpiko-chatbot-settings-disclosure-content">
<div class="mobile-app-guide wpiko-pwa-install-guide">
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
                                        <li><strong>Application Password required:</strong> admins and Live Agents should sign in with a WordPress username plus an Application Password, not their normal site password.</li>
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
                </details>
                </div>
                <div class="wpiko-chatbot-settings-actions"><button type="submit" class="button wpiko-chatbot-settings-save">Save Settings</button></div>
            </form>
        <?php elseif ($is_license_expired): ?>
            <section class="wpiko-chatbot-settings-card wpiko-chatbot-settings-upgrade" aria-labelledby="mobile-app-license-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="mobile-app-license-title">Mobile App Disabled</h2>
                <div class="wpiko-chatbot-settings-notice is-warning"><p>Your license has expired. The Mobile App (PWA) feature is currently unavailable.</p></div>
                <p class="wpiko-chatbot-settings-field-description">Get a lifetime license to manage conversations on your phone, reply to visitors, receive push alerts, and take over chats.</p>
                <ul class="wpiko-chatbot-settings-benefits">
                    <li>Reply to conversations from your phone</li>
                    <li>Receive push notifications for new messages</li>
                    <li>Take over chats as an Administrator or Live Agent</li>
                    <li>Customize agent join and leave messages</li>
                </ul>
                <a href="https://wpiko.com/chatbot-pricing/" target="_blank" rel="noopener" class="button wpiko-chatbot-settings-save">Get Lifetime License</a>
            </section>
        <?php else: ?>
            <section class="wpiko-chatbot-settings-card wpiko-chatbot-settings-upgrade" aria-labelledby="mobile-app-license-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="mobile-app-license-title">Unlock Mobile App</h2>
                <p class="wpiko-chatbot-settings-field-description">Activate Pro to manage chatbot conversations from your phone.</p>
                <ul class="wpiko-chatbot-settings-benefits">
                    <li>Reply to conversations from your phone</li>
                    <li>Receive push notifications for new messages</li>
                    <li>Take over chats as an Administrator or Live Agent</li>
                    <li>Customize agent join and leave messages</li>
                </ul>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-chatbot&tab=license_activation')); ?>" class="button wpiko-chatbot-settings-save">Activate Pro</a>
            </section>
        <?php endif; ?>
        <script>
        (function () {
            var enablePwaCheckbox = document.getElementById('wpiko_chatbot_enable_pwa');
            var options = document.getElementById('mobile-app-options');
            if (enablePwaCheckbox && options) {
                enablePwaCheckbox.addEventListener('change', function () {
                    options.hidden = !enablePwaCheckbox.checked;
                });
            }
        }());
        </script>
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

            var pushCheckbox = document.getElementById('wpiko_chatbot_enable_push');
            var savedPushEnabled = pushCheckbox ? pushCheckbox.checked : false;

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

        }());
        </script>
        <?php endif; ?>
    </div>
    <?php
}
