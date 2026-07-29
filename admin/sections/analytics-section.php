<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_analytics_section() {
    $is_premium = wpiko_chatbot_is_license_active();
    $analytics_now = new DateTimeImmutable('now', wpiko_chatbot_pro_get_site_timezone());
    $default_start_date = $analytics_now->modify('-6 days')->format('Y-m-d');
    $default_end_date = $analytics_now->format('Y-m-d');

    // Advanced date range options for premium users
    if ($is_premium) {
        // Check if we're processing form data
        if (isset($_GET['date_range'])) {
            // Verify nonce for security
            $analytics_nonce = isset($_GET['wpiko_analytics_nonce']) ? sanitize_text_field(wp_unslash($_GET['wpiko_analytics_nonce'])) : '';
            if (wp_verify_nonce($analytics_nonce, 'wpiko_analytics_filter')) {
                $date_range = sanitize_text_field(wp_unslash($_GET['date_range']));
                $custom_start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : null;
                $custom_end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : null;
            } else {
                // If nonce verification fails, use default values
                $date_range = '7';
                $custom_start_date = null;
                $custom_end_date = null;
            }
        } else {
            // Default values when not from form
            $date_range = '7';
            $custom_start_date = null;
            $custom_end_date = null;
        }
    } else {
        // Set default date range for free users
        $date_range = '7';
    }

    $allowed_date_ranges = array('7', '30', '90', 'custom');
    if (!in_array($date_range, $allowed_date_ranges, true)) {
        $date_range = '7';
        $custom_start_date = null;
        $custom_end_date = null;
    }

    // Get location view preference (default to country)
    // Validate location view parameter to prevent invalid values
    $allowed_location_views = array('country', 'city', 'region');
    $location_view_param = isset($_GET['location_view']) ? sanitize_text_field(wp_unslash($_GET['location_view'])) : 'country';
    $location_view = in_array($location_view_param, $allowed_location_views, true) ? $location_view_param : 'country';
    
    // Resolve the selected and comparison periods using the shared AJAX rules.
    $analytics_period = wpiko_chatbot_pro_get_analytics_period(
        $date_range,
        $custom_start_date ?? null,
        $custom_end_date ?? null
    );
    $date_range = $analytics_period['date_range'];
    $custom_start_date = $analytics_period['custom_start_date'];
    $custom_end_date = $analytics_period['custom_end_date'];
    $analytics_snapshot = wpiko_chatbot_pro_get_analytics_snapshot(
        $analytics_period,
        $location_view,
        $is_premium
    );
    $has_data = $analytics_snapshot['has_data'];
    $total_conversations = $analytics_snapshot['total_conversations'];
    $total_messages = $analytics_snapshot['total_messages'];
    $total_users = $analytics_snapshot['total_users'];
    $conversation_change = $analytics_snapshot['conversation_change'];
    $avg_messages = $analytics_snapshot['avg_messages'];
    $error_count = $analytics_snapshot['error_count'];
    $error_rate = $analytics_snapshot['error_rate'];
    $daily_messages = $analytics_snapshot['daily_messages'];
    $location_distribution = $analytics_snapshot['locations'];
    $conversation_length_bins = $analytics_snapshot['conversation_length_bins'];
    $busy_hours = $analytics_snapshot['busy_hours'];
    $total_user_messages = $analytics_snapshot['total_user_messages'];
    $device_counts = $analytics_snapshot['device_counts'];
    $device_percentages = $analytics_snapshot['device_percentages'];

    ?>
    <div class="analytics-section">
        <div class="analytics-header">
            <div class="analytics-title-group">
                <h2><span class="dashicons dashicons-chart-bar"></span> Analytics Dashboard</h2>
                <div class="analytics-live-status" data-analytics-live-status>
                    <span class="analytics-live-dot" aria-hidden="true"></span>
                    <span data-analytics-live-label><?php esc_html_e('Live', 'wpiko-chatbot-pro'); ?></span>
                </div>
            </div>

            <div class="analytics-header-controls">
                <?php if ($is_premium): ?>
                    <!-- Premium date range selector -->
                    <div class="date-range">
                        <form id="analytics-date-range" method="get" action="">
                            <input type="hidden" name="page" value="ai-chatbot">
                            <input type="hidden" name="tab" value="analytics">
                            <input type="hidden" name="location_view" value="<?php echo esc_attr($location_view); ?>">
                            <?php wp_nonce_field('wpiko_analytics_filter', 'wpiko_analytics_nonce'); ?>
                            <select name="date_range" id="date_range">
                                <option value="7" <?php selected($date_range, '7'); ?>>Last 7 Days</option>
                                <option value="30" <?php selected($date_range, '30'); ?>>Last 30 Days</option>
                                <option value="90" <?php selected($date_range, '90'); ?>>Last 90 Days</option>
                                <option value="custom" <?php selected($date_range, 'custom'); ?>>Custom Range</option>
                            </select>
                            <div id="custom-date-inputs" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>;">
                                <input type="date" name="start_date" id="start_date"
                                       value="<?php echo esc_attr($custom_start_date ?? $default_start_date); ?>">
                                <span>to</span>
                                <input type="date" name="end_date" id="end_date"
                                       value="<?php echo esc_attr($custom_end_date ?? $default_end_date); ?>">
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Free version date range display -->
                    <div class="date-range">
                        <span class="date-value">
                            <?php
                                echo esc_html(
                                    $analytics_now->modify('-6 days')->format('M j, Y')
                                    . ' - '
                                    . $analytics_now->format('M j, Y')
                                );
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="no-data-message" data-analytics-empty-state<?php if ($has_data): ?> hidden<?php endif; ?>>
            <p>No conversations found for the selected date range</p>
        </div>

        <!-- Basic Analytics Cards (Available to all users) -->
        <div class="analytics-grid" data-analytics-summary<?php if (!$has_data): ?> hidden<?php endif; ?>>
            <div class="analytics-card highlight-card">
                <div class="card-content">
                    <div class="card-header">
                        <h3>Total Conversations</h3>
                        <span class="dashicons dashicons-admin-comments"></span>
                    </div>
                    <div class="analytics-number" data-analytics-total-conversations><?php echo number_format($total_conversations); ?></div>
                    <div class="trend <?php echo $conversation_change >= 0 ? 'positive' : 'negative'; ?>" data-analytics-conversation-trend>
                        <span class="dashicons <?php echo $conversation_change >= 0 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt'; ?>"></span>
                        <span><span data-analytics-conversation-change><?php echo esc_html(abs($conversation_change)); ?></span>% from last period</span>
                    </div>
                </div>
            </div>

            <div class="analytics-card">
                <div class="card-content">
                    <div class="card-header">
                        <h3>Total Messages</h3>
                        <span class="dashicons dashicons-format-chat"></span>
                    </div>
                    <div class="analytics-number" data-analytics-total-messages><?php echo number_format($total_messages); ?></div>
                    <div class="metric-subtitle">
                        <span data-analytics-average-messages><?php echo number_format($avg_messages, 1); ?></span> avg. messages per conversation
                    </div>
                </div>
            </div>

            <div class="analytics-card">
                <div class="card-content">
                    <div class="card-header">
                        <h3>Unique Users</h3>
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="analytics-number" data-analytics-total-users><?php echo number_format($total_users); ?></div>
                    <div class="metric-subtitle">
                        Based on unique emails/sessions
                    </div>
                </div>
            </div>

            <div class="analytics-card">
                <div class="card-content">
                    <div class="card-header">
                        <h3>Error Rate</h3>
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="analytics-number <?php echo $error_rate > 3 ? 'warning' : ''; ?>" data-analytics-error-rate>
                        <?php echo number_format($error_rate, 1); ?>%
                    </div>
                    <div class="metric-subtitle">
                        <span data-analytics-error-count><?php echo number_format($error_count); ?></span> total errors
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_premium): ?>
            <!-- Premium Analytics Section -->
            <div class="analytics-grid charts-grid">
                <div class="analytics-card full-width message-activity-card">
                    <div class="card-header message-activity-header">
                        <div class="message-activity-heading">
                            <h3 id="message-activity-title">Message Activity</h3>
                            <p id="message-activity-summary" class="message-activity-summary" aria-live="polite" aria-atomic="true">
                                Loading message activity…
                            </p>
                        </div>
                        <div class="message-series-toggle" role="group" aria-label="Message activity series">
                            <button type="button" class="message-series-btn active" data-series="total" aria-pressed="true" aria-controls="message-activity-chart">Total</button>
                            <button type="button" class="message-series-btn" data-series="user" aria-pressed="false" aria-controls="message-activity-chart">User</button>
                            <button type="button" class="message-series-btn" data-series="assistant" aria-pressed="false" aria-controls="message-activity-chart">Assistant</button>
                            <button type="button" class="message-series-btn" data-series="error" aria-pressed="false" aria-controls="message-activity-chart">Errors</button>
                        </div>
                    </div>

                    <div
                        id="message-activity-chart"
                        class="message-line-chart"
                        data-message-activity-chart
                        role="region"
                        aria-labelledby="message-activity-title"
                        aria-describedby="message-activity-instructions"
                    >
                        <p id="message-activity-instructions" class="screen-reader-text">
                            Select a message type to update the chart. Use the left and right arrow keys to move between data points.
                        </p>

                        <div class="y-axis-labels" aria-hidden="true"></div>

                        <div class="line-chart-container">
                            <div class="chart-area message-chart-area">
                                <div class="chart-gridlines" aria-hidden="true"></div>

                                <svg class="message-chart-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                    <defs>
                                        <linearGradient id="message-activity-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop class="message-chart-gradient-start" offset="0%" stop-color="#0968FE" stop-opacity="0.2" />
                                            <stop class="message-chart-gradient-end" offset="100%" stop-color="#0968FE" stop-opacity="0" />
                                        </linearGradient>
                                    </defs>
                                    <polygon class="message-chart-fill" points="" fill="url(#message-activity-gradient)" />
                                    <polyline class="message-chart-line" points="" fill="none" />
                                </svg>

                                <div class="chart-points"></div>
                                <div class="vertical-line" hidden aria-hidden="true"></div>
                                <div id="message-activity-tooltip" class="chart-tooltip" role="tooltip" hidden></div>

                                <div class="chart-empty-state" hidden aria-hidden="true">
                                    <strong class="chart-empty-title"></strong>
                                    <span class="chart-empty-description">Try another message type or date range.</span>
                                </div>
                            </div>

                            <div class="date-labels" aria-hidden="true"></div>
                        </div>

                        <table class="screen-reader-text message-activity-data-table">
                            <caption>Message activity by date</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Messages</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>

                        <noscript>
                            <p class="no-data-message">JavaScript is required to display the message activity chart.</p>
                        </noscript>
                    </div>
                </div>

            <div class="analytics-card half-width">
                <div class="card-header">
                    <h3>Top User Locations</h3>
                    <div class="location-view-toggle" role="group" aria-label="Location type">
                        <button type="button" class="location-toggle-btn <?php echo $location_view === 'country' ? 'active' : ''; ?>" data-view="country" aria-pressed="<?php echo $location_view === 'country' ? 'true' : 'false'; ?>" aria-controls="top-user-locations-list">Country</button>
                        <button type="button" class="location-toggle-btn <?php echo $location_view === 'city' ? 'active' : ''; ?>" data-view="city" aria-pressed="<?php echo $location_view === 'city' ? 'true' : 'false'; ?>" aria-controls="top-user-locations-list">City</button>
                        <button type="button" class="location-toggle-btn <?php echo $location_view === 'region' ? 'active' : ''; ?>" data-view="region" aria-pressed="<?php echo $location_view === 'region' ? 'true' : 'false'; ?>" aria-controls="top-user-locations-list">Region</button>
                    </div>
                </div>
                <div id="top-user-locations-list" class="locations-list" aria-live="polite" aria-busy="false">
                    <?php if (!empty($location_distribution)): ?>
                        <?php
                        $max_count = !empty($location_distribution) ? $location_distribution[0]['count'] : 1;
                        foreach ($location_distribution as $location):
                            $location_name = !empty($location['location']) ? $location['location'] : 'Unknown';
                            $percentage = $max_count > 0 ? ($location['count'] / $max_count) * 100 : 0;
                        ?>
                            <div class="location-item">
                                <span class="location-name" title="<?php echo esc_attr($location_name); ?>"><?php echo esc_html($location_name); ?></span>
                                <div class="activity-bar-container">
                                    <div class="activity-bar" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                </div>
                                <span class="count"><?php echo number_format($location['count']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-message">
                            <p>No <?php echo esc_html($location_view); ?> data available for the selected period</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="analytics-card half-width">
                <div class="card-header">
                    <h3>Conversation Length Distribution</h3>
                </div>
                <div class="conversation-length-list">
                    <?php
                    $max_count = !empty($conversation_length_bins) ? max($conversation_length_bins) : 0;
                    foreach ($conversation_length_bins as $label => $count):
                        $percentage = $max_count > 0 ? ($count / $max_count) * 100 : 0;
                    ?>
                        <div class="conversation-length-item">
                            <span class="range"><?php echo esc_html($label); ?></span>
                            <div class="activity-bar-container">
                                 <div class="activity-bar" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                            </div>
                            <span class="count"><?php echo number_format($count); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
             </div>

        <!-- Additional Metrics -->
        <div class="analytics-card half-width">
            <h3>Peak Activity Hours</h3>
            <div class="peak-hours-list">
                <?php
                $peak_hour_maximum = !empty($busy_hours) ? $busy_hours[0]['count'] : 0;
                foreach ($busy_hours as $hour):
                ?>
                    <div class="peak-hour-item">
                        <span class="hour"><?php echo esc_html(gmdate('ga', strtotime($hour['hour'] . ':00'))); ?></span>
                        <div class="activity-bar-container">
                            <div class="activity-bar" style="width: <?php echo esc_attr($peak_hour_maximum > 0 ? ($hour['count'] / $peak_hour_maximum) * 100 : 0); ?>%"></div>
                        </div>
                        <span class="count"><?php echo number_format($hour['count']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <!-- Messages by Device Card -->
    <div class="analytics-card half-width">
        <div class="card-header">
            <h3>Messages by Device</h3>
        </div>
        <div class="device-stats">
            <div class="messages-total">
                <div class="messages-label">User Messages</div>
                <div class="messages-count" data-analytics-device-total><?php echo number_format($total_user_messages); ?></div>
                </div>
                <div class="device-distribution">
                    <div class="device-box" data-analytics-device="desktop">
                        <div class="device-icon">
                            <span class="dashicons dashicons-desktop"></span>
                        </div>
                        <div class="device-info">
                            <div class="device-percentage"><?php echo esc_html(round($device_percentages['desktop'])); ?>%</div>
                            <div class="device-details">Desktop: <span class="device-count"><?php echo esc_html(number_format($device_counts['desktop'])); ?></span></div>
                        </div>
                    </div>
                    <div class="device-box" data-analytics-device="mobile">
                        <div class="device-icon">
                            <span class="dashicons dashicons-smartphone"></span>
                        </div>
                        <div class="device-info">
                            <div class="device-percentage"><?php echo esc_html(round($device_percentages['mobile'])); ?>%</div>
                            <div class="device-details">Mobile: <span class="device-count"><?php echo esc_html(number_format($device_counts['mobile'])); ?></span></div>
                        </div>
                    </div>
                    <div class="device-box" data-analytics-device="tablet">
                        <div class="device-icon">
                            <span class="dashicons dashicons-tablet"></span>
                        </div>
                        <div class="device-info">
                            <div class="device-percentage"><?php echo esc_html(round($device_percentages['tablet'])); ?>%</div>
                            <div class="device-details">Tablet: <span class="device-count"><?php echo esc_html(number_format($device_counts['tablet'])); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
   
    </div>
        <?php endif; ?>

        <?php if (!$is_premium): ?>
            <?php 
                $license_status = wpiko_chatbot_decrypt_data(get_option('wpiko_chatbot_license_status', ''));
                $is_license_expired = $license_status === 'expired';
                
                    if ($is_license_expired): ?>
                        <div class="premium-feature-notice">
                            <h3>🔒 Analytics Dashboard Disabled</h3>
                            <p>Your license has expired. Advanced analytics features have been disabled.</p>
                            <p>Renew your license to regain access to:</p>
                            <ul>
                                <li>📊 Detailed Message Activity Graphs</li>
                                <li>📍 User Location Insights</li>
                                <li>📱 Device Usage Statistics</li>
                                <li>⏰ Peak Activity Hours</li>
                                <li>📈 Custom Date Range Analysis</li>
                            </ul>
                            <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Renew License</a>
                        </div>
                    <?php else: ?>
                        <div class="premium-feature-notice">
                            <h3>📈 Unlock Advanced Analytics</h3>
                            <p>Upgrade to Premium to access:</p>
                            <ul>
                                <li>✨ Real-time Message Activity Tracking</li>
                                <li>🌍 Global User Distribution Maps</li>
                                <li>📱 Cross-device Usage Analytics</li>
                                <li>⚡ Performance Metrics Dashboard</li>
                                <li>🔄 Custom Date Range Filtering</li>
                            </ul>
                            <a href="?page=ai-chatbot&tab=license_activation" class="button button-primary">Upgrade to Premium</a>
                        </div>
                    <?php endif; ?>
        <?php endif; ?>
    
    <?php
    $analytics_asset_version = apply_filters(
        'wpiko_chatbot_pro_asset_version',
        defined('WPIKO_CHATBOT_PRO_VERSION') ? WPIKO_CHATBOT_PRO_VERSION : '1.0.0'
    );

    // Always enqueue the CSS file
    wp_enqueue_style('wpiko-chatbot-analytics-css', plugins_url('/css/analytics-style.css', dirname(__FILE__)), array(), $analytics_asset_version);

    wp_enqueue_script('wpiko-chatbot-analytics', plugins_url('/js/analytics.js', dirname(__FILE__)), array('jquery'), $analytics_asset_version, true);
    wp_localize_script(
        'wpiko-chatbot-analytics',
        'wpikoChatbotAnalytics',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'locationAction' => 'wpiko_chatbot_get_analytics_locations',
            'locationNonce' => wp_create_nonce('wpiko_chatbot_analytics_locations'),
            'locationError' => __('Unable to load location analytics. Please try again.', 'wpiko-chatbot-pro'),
            'liveAction' => 'wpiko_chatbot_get_analytics_snapshot',
            'liveNonce' => wp_create_nonce('wpiko_chatbot_analytics_live'),
            'liveInterval' => (int) apply_filters('wpiko_chatbot_analytics_live_interval', 15000),
            'liveLabel' => __('Live', 'wpiko-chatbot-pro'),
            'liveUpdatingLabel' => __('Updating…', 'wpiko-chatbot-pro'),
            'liveErrorLabel' => __('Reconnecting…', 'wpiko-chatbot-pro'),
        )
    );
    ?>

    <script>
    // Pass PHP data to JavaScript
    var analyticsData = {
        dailyMessages: <?php echo wp_json_encode($daily_messages); ?>,
        locationDistribution: <?php echo wp_json_encode($location_distribution); ?>,
        currentLocationView: '<?php echo esc_js($location_view); ?>'
    };
    </script>
    <?php
}
