<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_get_date_range_array($start_date, $end_date) {
    $dates = array();
    $current = new DateTime($start_date);
    $end = new DateTime($end_date);

    while ($current <= $end) {
        $dates[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
    return $dates;
}

function wpiko_chatbot_analytics_section() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    $is_premium = wpiko_chatbot_is_license_active();
    
    // Basic date range for free users
    $days = 7; // Default to last 7 days for free users
    $current_period_end = gmdate('Y-m-d H:i:s');
    $current_period_start = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

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

    // Set the date range based on selection
    if ($date_range === 'custom' && $custom_start_date && $custom_end_date) {
        // Ensure end date is not before start date
        if (strtotime($custom_end_date) < strtotime($custom_start_date)) {
            $temp = $custom_end_date;
            $custom_end_date = $custom_start_date;
            $custom_start_date = $temp;
        }
    
        $current_period_start = $custom_start_date . ' 00:00:00';
        $current_period_end = $custom_end_date . ' 23:59:59';

        // Calculate previous period for custom range
        $period_length = strtotime($current_period_end) - strtotime($current_period_start);
        if ($period_length == 0) {
            // If same day selected, set previous period to previous day
            $previous_period_end = gmdate('Y-m-d H:i:s', strtotime($current_period_start));
            $previous_period_start = gmdate('Y-m-d H:i:s', strtotime('-1 day', strtotime($current_period_start)));
        } else {
            $previous_period_end = $current_period_start;
            $previous_period_start = gmdate('Y-m-d H:i:s', strtotime($current_period_start) - $period_length);
        }
    } else {
        // Default date range
        $days = intval($date_range);
        $current_period_end = gmdate('Y-m-d H:i:s');
        $current_period_start = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Calculate previous period
        $previous_period_end = $current_period_start;
        $previous_period_start = gmdate('Y-m-d H:i:s', strtotime("-{$days} days", strtotime($previous_period_end)));
    }

    // Overall Statistics FOR THE SELECTED PERIOD
    $total_conversations = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT session_id) FROM {$wpdb->prefix}wpiko_chatbot_conversations 
        WHERE timestamp >= %s AND timestamp <= %s",
        $current_period_start,
        $current_period_end
    ));

    $total_messages = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpiko_chatbot_conversations 
        WHERE timestamp >= %s AND timestamp <= %s",
        $current_period_start,
        $current_period_end
    ));

    $total_users = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT CASE WHEN user_email != '' THEN user_email ELSE session_id END) 
        FROM {$wpdb->prefix}wpiko_chatbot_conversations 
        WHERE timestamp >= %s AND timestamp <= %s",
        $current_period_start,
        $current_period_end
    ));

    // Check if there's data
    $has_data = $total_conversations > 0;
    
    // Current period stats
    $current_period_conversations = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT session_id) FROM {$wpdb->prefix}wpiko_chatbot_conversations WHERE timestamp >= %s",
        $current_period_start
    ));
    
    // Previous period stats for comparison
    $previous_period_conversations = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT session_id) FROM {$wpdb->prefix}wpiko_chatbot_conversations WHERE timestamp >= %s AND timestamp < %s",
        $previous_period_start,
        $previous_period_end
    ));

    // Calculate percentage change
    $conversation_change = 0;
    if ($previous_period_conversations > 0) {
        $conversation_change = round((($current_period_conversations - $previous_period_conversations) / $previous_period_conversations) * 100, 1);
    } elseif ($previous_period_conversations == 0 && $current_period_conversations > 0) {
        $conversation_change = 100;
    } elseif ($previous_period_conversations == 0 && $current_period_conversations == 0) {
        $conversation_change = 0;
    }

    // Average messages per conversation
    $avg_messages = $wpdb->get_var("
        SELECT AVG(message_count) FROM (
            SELECT session_id, COUNT(*) as message_count 
            FROM {$wpdb->prefix}wpiko_chatbot_conversations 
            GROUP BY session_id
        ) as conversation_counts
    ");

    // Get messages by type
    $message_types = $wpdb->get_results($wpdb->prepare("
        SELECT role, COUNT(*) as count 
        FROM {$wpdb->prefix}wpiko_chatbot_conversations 
        WHERE timestamp >= %s AND timestamp <= %s
        GROUP BY role
    ", $current_period_start, $current_period_end), ARRAY_A);

    // Error rate calculation
    $error_count = 0;
    $total_count = 0;
    foreach ($message_types as $type) {
        if ($type['role'] === 'error') {
            $error_count = $type['count'];
        }
        $total_count += $type['count'];
    }
    $error_rate = $total_count > 0 ? ($error_count / $total_count) * 100 : 0;

    // Get busiest hours
    $busy_hours = $wpdb->get_results($wpdb->prepare("
        SELECT HOUR(timestamp) as hour, COUNT(*) as count 
        FROM {$wpdb->prefix}wpiko_chatbot_conversations 
        WHERE timestamp >= %s AND timestamp <= %s
        GROUP BY HOUR(timestamp) 
        ORDER BY count DESC 
        LIMIT 3
    ", $current_period_start, $current_period_end));

    // Get messages per day for the selected date range
    $date_range_array = wpiko_chatbot_get_date_range_array($current_period_start, $current_period_end);
    $daily_messages_results = $wpdb->get_results($wpdb->prepare("
        SELECT 
            DATE(timestamp) as date, 
            COUNT(*) as total_count,
            SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
            SUM(CASE WHEN role = 'assistant' THEN 1 ELSE 0 END) as assistant_count,
            SUM(CASE WHEN role = 'error' THEN 1 ELSE 0 END) as error_count
        FROM {$wpdb->prefix}wpiko_chatbot_conversations 
        WHERE timestamp >= %s AND timestamp <= %s
        GROUP BY DATE(timestamp)",
        $current_period_start,
        $current_period_end), ARRAY_A);

    // Create a lookup array for quick access to daily counts
    $daily_messages_lookup = array();
    foreach ($daily_messages_results as $row) {
        $daily_messages_lookup[$row['date']] = $row;
    }

    // Create the final array with all dates, including zeros
    $daily_messages = array();
    foreach ($date_range_array as $date) {
        if (isset($daily_messages_lookup[$date])) {
            $daily_messages[] = $daily_messages_lookup[$date];
        } else {
            $daily_messages[] = array(
                'date' => $date,
                'total_count' => 0,
                'user_count' => 0,
                'assistant_count' => 0,
                'error_count' => 0
            );
        }
    }
    
    // Get conversation length query
    $conversation_lengths = $wpdb->get_results($wpdb->prepare("
        SELECT session_id, COUNT(*) as message_count
        FROM {$wpdb->prefix}wpiko_chatbot_conversations 
        WHERE timestamp >= %s AND timestamp <= %s
        GROUP BY session_id
    ", $current_period_start, $current_period_end));

    // Get conversation country 
    $country_distribution = $wpdb->get_results($wpdb->prepare("
        SELECT 
            country,
            COUNT(DISTINCT session_id) as count
        FROM {$wpdb->prefix}wpiko_chatbot_conversations 
        WHERE country IS NOT NULL
            AND timestamp >= %s AND timestamp <= %s
        GROUP BY country
        ORDER BY count DESC
        LIMIT 5
    ", $current_period_start, $current_period_end));

    $analyticsData = [
        'dailyMessages' => $daily_messages,
        'countryDistribution' => $country_distribution,
        'conversationLengths' => $conversation_lengths
    ];

    ?>
    <div class="analytics-section">
        <div class="analytics-header">        
            <h2><span class="dashicons dashicons-chart-bar"></span> Analytics Dashboard</h2>
        
            <?php if ($is_premium): ?>
                <!-- Premium date range selector -->
                <div class="date-range">
                    <form id="analytics-date-range" method="get" action="">
                        <input type="hidden" name="page" value="ai-chatbot">
                        <input type="hidden" name="tab" value="analytics">
                        <?php wp_nonce_field('wpiko_analytics_filter', 'wpiko_analytics_nonce'); ?>
                        <select name="date_range" id="date_range">
                            <option value="7" <?php selected($date_range, '7'); ?>>Last 7 Days</option>
                            <option value="30" <?php selected($date_range, '30'); ?>>Last 30 Days</option>
                            <option value="90" <?php selected($date_range, '90'); ?>>Last 90 Days</option>
                            <option value="custom" <?php selected($date_range, 'custom'); ?>>Custom Range</option>
                        </select>
                        <div id="custom-date-inputs" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>;">
                            <input type="date" name="start_date" id="start_date" 
                                   value="<?php echo esc_attr($custom_start_date ?? gmdate('Y-m-d', strtotime('-7 days'))); ?>">
                            <span>to</span>
                            <input type="date" name="end_date" id="end_date" 
                                   value="<?php echo esc_attr($custom_end_date ?? gmdate('Y-m-d')); ?>">
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Free version date range display -->
                <div class="date-range">
                    <span class="date-value">
                        <?php 
                            echo esc_html(gmdate('M j, Y', strtotime('-7 days')) . ' - ' . gmdate('M j, Y'));
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$has_data): ?>
            <div class="no-data-message">
                <p>No conversations found for the selected date range</p>
            </div>
        <?php endif; ?>

        <?php if ($has_data): ?>
        <!-- Basic Analytics Cards (Available to all users) -->
        <div class="analytics-grid">
            <div class="analytics-card highlight-card">
                <div class="card-content">
                    <div class="card-header">
                        <h3>Total Conversations</h3>
                        <span class="dashicons dashicons-admin-comments"></span>
                    </div>
                    <div class="analytics-number"><?php echo number_format($total_conversations); ?></div>
                    <div class="trend <?php echo $conversation_change >= 0 ? 'positive' : 'negative'; ?>">
                        <span class="dashicons <?php echo $conversation_change >= 0 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt'; ?>"></span>
                        <?php echo esc_html(abs($conversation_change)); ?>% from last period
                    </div>
                </div>
            </div>

            <div class="analytics-card">
                <div class="card-content">
                    <div class="card-header">
                        <h3>Total Messages</h3>
                        <span class="dashicons dashicons-format-chat"></span>
                    </div>
                    <div class="analytics-number"><?php echo number_format($total_messages); ?></div>
                    <div class="metric-subtitle">
                        <?php echo number_format($avg_messages, 1); ?> avg. messages per conversation
                    </div>
                </div>
            </div>

            <div class="analytics-card">
                <div class="card-content">
                    <div class="card-header">
                        <h3>Unique Users</h3>
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="analytics-number"><?php echo number_format($total_users); ?></div>
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
                    <div class="analytics-number <?php echo $error_rate > 3 ? 'warning' : ''; ?>">
                        <?php echo number_format($error_rate, 1); ?>%
                    </div>
                    <div class="metric-subtitle">
                        <?php echo number_format($error_count); ?> total errors
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($is_premium): ?>
            <!-- Premium Analytics Section -->
            <div class="analytics-grid charts-grid">
                <div class="analytics-card full-width">
                    <div class="card-header">
                        <h3>Message Activity</h3>
                   </div>
                   <div class="message-line-chart">
                   <?php                                                                               
                   
                    // Get min and max values for scaling
                   $max_count = 0;
                   $values = array();
                   foreach ($daily_messages as $day) {
                        $max_count = max($max_count, $day['user_count']);
                        $values[] = $day['user_count'];
                    }
                    
                    // Calculate y-axis labels
                   $label_count = 5; // Number of labels to show
                   $step = $max_count > 0 ? ceil($max_count / ($label_count - 1)) : 1;
                   $max_label = ceil($max_count / $step) * $step;
                   
                   // Determine label interval based on date range
                   $interval = 1; // Default interval
                   
                   if ($date_range === 'custom') {
                       // Calculate days between start and end date
                       $days_difference = ceil((strtotime($current_period_end) - strtotime($current_period_start)) / (60 * 60 * 24));
                       
                       if ($days_difference <= 7) {
                           $interval = 1;      // Show all dates for ≤ 7 days
                        } elseif ($days_difference <= 30) {
                            $interval = 5;      // Show every 5th day for 8-30 days
                        } elseif ($days_difference <= 90) {
                            $interval = 10;     // Show every 10th day for 31-90 days
                        } elseif ($days_difference <= 180) {
                            $interval = 15;     // Show every 15th day for 91-180 days
                        } elseif ($days_difference <= 365) {
                            $interval = 30;     // Show monthly for 181-365 days
                        } else {
                            // For ranges > 1 year, show fewer points to prevent overcrowding
                            $interval = ceil($days_difference / 12); // Aim for roughly 12 points on the graph
                        }
                    } else {
                   
                       // Determine label interval based on date range
                       if ($date_range == '90') {
                           $interval = 10; // Show every 10th day
                        } elseif ($date_range == '30') {
                           $interval = 5;  // Show every 5th day
                        } elseif ($date_range == '7') {
                            $interval = 1;
                        }
                    }
                    
                    // Calculate points for the line
                    $points = '';
                    $dots = '';
                    $dates = '';
                    $total_points = count($values);
                    $chart_width = 100; // percentage
                    $chart_height = 100; // percentage
                     
            ?>
            <div class="y-axis-labels">
                <?php
                for ($i = $max_label; $i >= 0; $i -= $step) {
                    echo "<div class='y-label'>" . number_format($i) . "</div>";
                }
                ?>
            </div>
            
            <?php
            // Check if it's a single day view
            $is_single_day = strtotime(gmdate('Y-m-d', strtotime($current_period_end))) === strtotime(gmdate('Y-m-d', strtotime($current_period_start)));
            ?>
        
            <div class="line-chart-container<?php echo $is_single_day ? ' single-day-view' : ''; ?>">
                <div class="chart-area">
                    <?php
                    
                if ($total_points > 0) {
                    // Generate points and dots
                    
                    foreach ($values as $index => $value) {
                        if ($is_single_day) {
                            // For single day, center the point
                            $x = 50; // Center point
                         } else {
                             $x = ($index / max(1, $total_points - 1)) * $chart_width;
                         }
                          
                        $y = $max_label > 0 ? (1 - ($value / $max_label)) * $chart_height : 100;
        
                        $points .= "$x,$y ";
                        
                        // Add zero-value class for dots with no messages
                        $zero_class = $value == 0 ? ' zero-value' : '';
                        // Define date format before using it
                        $date_format = 'M j';
                        $date = gmdate($date_format, strtotime($daily_messages[$index]['date']));
                        $dots .= "<div class='chart-dot{$zero_class}' style='left: {$x}%; top: {$y}%;' data-value='{$value}' data-date='{$date}'></div>";
        
                        // Add date labels
                        if ($date_range === 'custom') {
                            if ($days_difference > 365) {
                                $date_format = 'M Y'; // Show only month and year for ranges > 1 year
                            } elseif ($days_difference > 90) {
                                $date_format = 'M j'; // Show month and day for ranges > 90 days
                            }
                        }
                                    
                            if ($index === 0 || $index % $interval === 0 || $index === $total_points - 1) {
                                $date = gmdate($date_format, strtotime($daily_messages[$index]['date']));
                                $dates .= "<div class='date-label' style='left: {$x}%;'>{$date}</div>";
                        }
                    }
                    ?>
                
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" style="stop-color:#0968FE;stop-opacity:0.2" />
                                <stop offset="100%" style="stop-color:#0968FE;stop-opacity:0" />
                            </linearGradient>
                        </defs>
                
                        <!-- Area fill -->
                        <polygon points="0,100 <?php echo esc_attr($points); ?> <?php echo esc_attr($total_points-1); ?>,100" fill="url(#gradient)" />
                
                        <!-- Line -->
                        <polyline points="<?php echo esc_attr($points); ?>" fill="none" stroke="#0968FE" stroke-width="0.5" />
                    </svg>
                
                    <!-- Dots and tooltips -->
                    <?php echo wp_kses_post($dots); ?>
                </div>

                <!-- Date labels -->
                <div class="date-labels">
                    <?php echo wp_kses_post($dates); ?>
                </div>
                       
                      </div> 
                <?php } else { ?>
                    <div class="no-data-message">
                        <p>Not enough data available for the selected period</p>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="analytics-card half-width">
                <div class="card-header">
                    <h3>Top User Locations</h3>
                </div>
                <div class="locations-list">
                    <?php foreach ($country_distribution as $country): ?>
                        <div class="location-item">
                            <span class="country"><?php echo esc_html($country->country); ?></span>
                            <div class="activity-bar-container">
                                <div class="activity-bar" style="width: <?php echo esc_attr(($country->count / $country_distribution[0]->count) * 100); ?>%"></div>
                            </div>
                            <span class="count"><?php echo number_format($country->count); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="analytics-card half-width">
                <div class="card-header">
                    <h3>Conversation Length Distribution</h3>
                </div>
                <div class="conversation-length-list">
                    <?php
                    // Calculate bins for conversation lengths
                    $bins = array(
                        '1-2 msgs' => 0,
                        '3-5 msgs' => 0,
                        '6-10 msgs' => 0,
                        '11-20 msgs' => 0,
                        '20+ msgs' => 0
                    );
        
                    foreach ($conversation_lengths as $conv) {
                        $count = intval($conv->message_count);
                        if ($count <= 2) $bins['1-2 msgs']++;
                        elseif ($count <= 5) $bins['3-5 msgs']++;
                        elseif ($count <= 10) $bins['6-10 msgs']++;
                        elseif ($count <= 20) $bins['11-20 msgs']++;
                        else $bins['20+ msgs']++;
                    }
        
                    $max_count = max($bins);
                    foreach ($bins as $label => $count): 
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
                <?php foreach ($busy_hours as $hour): ?>
                    <div class="peak-hour-item">
                        <span class="hour"><?php echo esc_html(gmdate('ga', strtotime($hour->hour . ':00'))); ?></span>
                        <div class="activity-bar-container">
                            <div class="activity-bar" style="width: <?php echo esc_attr(($hour->count / $busy_hours[0]->count) * 100); ?>%"></div>
                        </div>
                        <span class="count"><?php echo number_format($hour->count); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php
        // Get device distribution
        $device_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                COALESCE(device_type, 'desktop') as device_type,
                COUNT(*) as count
            FROM {$wpdb->prefix}wpiko_chatbot_conversations 
            WHERE timestamp >= %s AND timestamp <= %s
            AND role = 'user'
            GROUP BY device_type
        ", $current_period_start, $current_period_end));

        // Calculate totals and percentages
        $total_user_messages = 0;
        $device_percentages = array(
            'desktop' => 0,
            'mobile' => 0,
            'tablet' => 0 
        );

        foreach ($device_stats as $stat) {
            $total_user_messages += $stat->count;
            if (isset($device_percentages[$stat->device_type])) {
                $device_percentages[$stat->device_type] += $stat->count;
            }
        }

        // Convert counts to percentages
        if ($total_user_messages > 0) {
            foreach ($device_percentages as $device => $count) {
                $device_percentages[$device] = ($count / $total_user_messages) * 100;
            }
        }
        ?>

    <!-- Messages by Device Card -->
    <div class="analytics-card half-width">
        <div class="card-header">
            <h3>Messages by Device</h3>
        </div>
        <div class="device-stats">
            <div class="messages-total">
                <div class="messages-label">User Messages</div>
                <div class="messages-count"><?php echo number_format($total_user_messages); ?></div>
                </div>
                <div class="device-distribution">
                    <div class="device-box">
                        <div class="device-icon">
                            <span class="dashicons dashicons-desktop"></span>
                        </div>
                        <div class="device-info">
                            <div class="device-percentage"><?php echo esc_html(round($device_percentages['desktop'])); ?>%</div>
                            <div class="device-details">Desktop: <?php echo esc_html(number_format(($device_percentages['desktop'] * $total_user_messages / 100))); ?></div>
                        </div>
                    </div>
                    <div class="device-box">
                        <div class="device-icon">
                            <span class="dashicons dashicons-smartphone"></span>
                        </div>
                        <div class="device-info">
                            <div class="device-percentage"><?php echo esc_html(round($device_percentages['mobile'])); ?>%</div>
                            <div class="device-details">Mobile: <?php echo esc_html(number_format(($device_percentages['mobile'] * $total_user_messages / 100))); ?></div>
                        </div>
                    </div>
                    <div class="device-box">
                        <div class="device-icon">
                            <span class="dashicons dashicons-tablet"></span>
                        </div>
                        <div class="device-info">
                            <div class="device-percentage"><?php echo esc_html(round($device_percentages['tablet'])); ?>%</div>
                            <div class="device-details">Tablet: <?php echo esc_html(number_format(($device_percentages['tablet'] * $total_user_messages / 100))); ?></div>
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
    // Always enqueue the CSS file
    wp_enqueue_style('wpiko-chatbot-analytics-css', plugins_url('/css/analytics-style.css', dirname(__FILE__)), array(), '1.0');

    // Only enqueue JavaScript files if premium or has data
    if ($is_premium || $has_data) {
        wp_enqueue_script('wpiko-chatbot-analytics', plugins_url('/js/analytics.js', dirname(__FILE__)), array('jquery'), '1.0', true);
    }
    ?>

    <script>
    // Pass PHP data to JavaScript
    var analyticsData = {
        dailyMessages: <?php echo wp_json_encode($daily_messages); ?>,
        countryDistribution: <?php echo wp_json_encode($country_distribution); ?>,
        conversationLengths: <?php echo wp_json_encode($conversation_lengths); ?>
    };
    </script>
    <?php
}