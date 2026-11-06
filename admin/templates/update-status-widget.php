<?php
/**
 * "Plugin updates" card on the License page (and on the fallback page when the
 * base plugin is missing).
 *
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show the update status card.
 */
function wpiko_chatbot_pro_update_status_widget()
{
    $status = WPiko_Chatbot_Pro_Updater::instance()->status();
    $allowed = array('a' => array('href' => array(), 'target' => array(), 'rel' => array()), 'strong' => array());
    ?>
    <div class="wpiko-chatbot-settings-card wpiko-github-status-widget">
        <h2 class="wpiko-chatbot-settings-card-title"><?php esc_html_e('Plugin updates', 'wpiko-chatbot-pro'); ?></h2>

        <?php if ($status['error'] !== '') : ?>
            <div class="github-status-banner is-error">
                <span class="github-status-banner__icon dashicons dashicons-dismiss"></span>
                <div class="github-status-banner__content">
                    <span class="github-status-banner__title"><?php esc_html_e('Could not check for updates', 'wpiko-chatbot-pro'); ?></span>
                    <p class="github-status-banner__text"><?php echo esc_html($status['error']); ?></p>
                </div>
            </div>
        <?php elseif ($status['update_available'] && $status['can_download']) : ?>
            <div class="github-status-banner is-update">
                <span class="github-status-banner__icon dashicons dashicons-download"></span>
                <div class="github-status-banner__content">
                    <span class="github-status-banner__title">
                        <?php
                        /* translators: %s: version */
                        printf(esc_html__('Version %s is available', 'wpiko-chatbot-pro'), esc_html($status['latest']));
                        ?>
                    </span>
                    <p class="github-status-banner__text">
                        <a href="<?php echo esc_url(admin_url('plugins.php')); ?>"><?php esc_html_e('Update it on the Plugins page →', 'wpiko-chatbot-pro'); ?></a>
                    </p>
                </div>
            </div>
        <?php elseif ($status['update_available']) : ?>
            <div class="github-status-banner is-error">
                <span class="github-status-banner__icon dashicons dashicons-lock"></span>
                <div class="github-status-banner__content">
                    <span class="github-status-banner__title">
                        <?php
                        /* translators: %s: version */
                        printf(esc_html__('Version %s is available', 'wpiko-chatbot-pro'), esc_html($status['latest']));
                        ?>
                    </span>
                    <p class="github-status-banner__text"><?php echo wp_kses($status['message'], $allowed); ?></p>
                </div>
            </div>
        <?php else : ?>
            <div class="github-status-banner is-success">
                <span class="github-status-banner__icon dashicons dashicons-yes-alt"></span>
                <div class="github-status-banner__content">
                    <span class="github-status-banner__title"><?php esc_html_e('You’re up to date', 'wpiko-chatbot-pro'); ?></span>
                    <p class="github-status-banner__text">
                        <?php
                        echo in_array($status['license_status'], array('active'), true)
                            ? esc_html__('Updates come from wpiko.com and are checked for the WPiko signature before they are installed.', 'wpiko-chatbot-pro')
                            : esc_html__('Updates come from wpiko.com. Activate your license to install them.', 'wpiko-chatbot-pro');
                        ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="github-status-footer">
            <div class="github-status-meta">
                <span class="github-status-version">
                    <span class="github-status-version__label"><?php esc_html_e('Current version', 'wpiko-chatbot-pro'); ?></span>
                    <span class="github-status-version__value">v<?php echo esc_html(WPIKO_CHATBOT_PRO_VERSION); ?></span>
                </span>
                <?php if ($status['checked_at']) : ?>
                    <span class="github-status-version">
                        <span class="github-status-version__label"><?php esc_html_e('Last checked', 'wpiko-chatbot-pro'); ?></span>
                        <span class="github-status-version__value">
                            <?php
                            /* translators: %s: time difference, e.g. "5 mins" */
                            printf(esc_html__('%s ago', 'wpiko-chatbot-pro'), esc_html(human_time_diff($status['checked_at'])));
                            ?>
                        </span>
                    </span>
                <?php endif; ?>
            </div>
            <button type="button" id="force-update-check-btn" class="button button-secondary github-force-check-btn">
                <span class="dashicons dashicons-update github-force-check-btn__icon"></span>
                <span class="github-force-check-btn__label"><?php esc_html_e('Check for updates', 'wpiko-chatbot-pro'); ?></span>
            </button>
        </div>
        <div id="github-update-check-result" role="status" aria-live="polite"></div>
    </div>

    <script>
    (function () {
        var button = document.getElementById('force-update-check-btn');
        if (!button || !window.jQuery) {
            return;
        }
        button.addEventListener('click', function () {
            var $ = window.jQuery;
            var $btn = $(button);
            var $label = $btn.find('.github-force-check-btn__label');
            var $icon = $btn.find('.github-force-check-btn__icon');
            var $result = $('#github-update-check-result');
            var label = $label.text();

            $btn.prop('disabled', true);
            $icon.addClass('is-spinning');
            $label.text(<?php echo wp_json_encode(__('Checking…', 'wpiko-chatbot-pro')); ?>);
            $result.removeClass('github-update-success-message github-update-error-message').text('').show();

            $.post(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                action: 'wpiko_chatbot_pro_force_update_check',
                nonce: <?php echo wp_json_encode(wp_create_nonce('wpiko_chatbot_pro_update_check')); ?>
            }).done(function (response) {
                var ok = response && response.success;
                var message = response && response.data && response.data.message ? response.data.message : <?php echo wp_json_encode(__('Update check failed. Please try again.', 'wpiko-chatbot-pro')); ?>;
                $result.text(message).addClass(ok ? 'github-update-success-message' : 'github-update-error-message');
                if (ok) {
                    setTimeout(function () { window.location.reload(); }, 1500);
                }
            }).fail(function () {
                $result.text(<?php echo wp_json_encode(__('Network error. Please check your connection and try again.', 'wpiko-chatbot-pro')); ?>).addClass('github-update-error-message');
            }).always(function () {
                $btn.prop('disabled', false);
                $icon.removeClass('is-spinning');
                $label.text(label);
            });
        });
    })();
    </script>
    <?php
}

/**
 * AJAX: check for updates now.
 */
function wpiko_chatbot_pro_ajax_force_update_check()
{
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'wpiko_chatbot_pro_update_check')) {
        wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'wpiko-chatbot-pro')));
    }
    if (!current_user_can('update_plugins')) {
        wp_send_json_error(array('message' => __('You are not allowed to update plugins.', 'wpiko-chatbot-pro')));
    }

    $status = WPiko_Chatbot_Pro_Updater::instance()->check_now();

    if ($status['error'] !== '') {
        wp_send_json_error(array('message' => $status['error']));
    }
    if ($status['update_available'] && $status['can_download']) {
        /* translators: %s: version */
        $message = sprintf(__('Version %s is available. Reloading…', 'wpiko-chatbot-pro'), $status['latest']);
    } elseif ($status['update_available']) {
        $message = wp_strip_all_tags($status['message']);
    } else {
        $message = __('You’re up to date.', 'wpiko-chatbot-pro');
    }
    wp_send_json_success(array(
        'message'          => $message,
        'update_available' => $status['update_available'],
        'can_download'     => $status['can_download'],
        'latest_version'   => $status['latest'],
        'current_version'  => $status['current'],
    ));
}
add_action('wp_ajax_wpiko_chatbot_pro_force_update_check', 'wpiko_chatbot_pro_ajax_force_update_check');
