<?php
/**
 * Shared analytics queries and AJAX handlers.
 *
 * @package WPiko_Chatbot_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the timezone configured in WordPress.
 *
 * The fallback keeps compatibility with WordPress versions before wp_timezone()
 * was introduced.
 *
 * @return DateTimeZone
 */
function wpiko_chatbot_pro_get_site_timezone() {
    if (function_exists('wp_timezone')) {
        return wp_timezone();
    }

    $timezone_string = get_option('timezone_string');
    if ($timezone_string) {
        return new DateTimeZone($timezone_string);
    }

    $offset = (float) get_option('gmt_offset', 0);
    $hours = (int) $offset;
    $minutes = (int) round(abs($offset - $hours) * 60);
    $timezone_offset = sprintf(
        '%s%02d:%02d',
        $offset < 0 ? '-' : '+',
        abs($hours),
        $minutes
    );

    return new DateTimeZone($timezone_offset);
}

/**
 * Resolve a supported analytics date range into database timestamps.
 *
 * @param string      $date_range       Preset day count or "custom".
 * @param string|null $custom_start_date Custom start date in Y-m-d format.
 * @param string|null $custom_end_date   Custom end date in Y-m-d format.
 * @return array
 */
function wpiko_chatbot_pro_get_analytics_period($date_range, $custom_start_date = null, $custom_end_date = null) {
    $allowed_date_ranges = array('7', '30', '90', 'custom');
    $date_range = in_array($date_range, $allowed_date_ranges, true) ? $date_range : '7';
    $custom_start_date = wpiko_chatbot_pro_validate_analytics_date($custom_start_date);
    $custom_end_date = wpiko_chatbot_pro_validate_analytics_date($custom_end_date);
    $site_timezone = wpiko_chatbot_pro_get_site_timezone();

    if ('custom' === $date_range && $custom_start_date && $custom_end_date) {
        if ($custom_end_date < $custom_start_date) {
            $temporary_date = $custom_end_date;
            $custom_end_date = $custom_start_date;
            $custom_start_date = $temporary_date;
        }

        $current_period_start_date = new DateTimeImmutable($custom_start_date . ' 00:00:00', $site_timezone);
        $current_period_end_date = new DateTimeImmutable($custom_end_date . ' 23:59:59', $site_timezone);
    } else {
        if ('custom' === $date_range) {
            $date_range = '7';
            $custom_start_date = null;
            $custom_end_date = null;
        }

        $days = max(1, intval($date_range));
        $start_offset = $days - 1;
        $current_period_end_date = new DateTimeImmutable('now', $site_timezone);
        $current_period_start_date = $current_period_end_date
            ->modify("-{$start_offset} days")
            ->setTime(0, 0, 0);
    }

    $period_length = $current_period_end_date->getTimestamp() - $current_period_start_date->getTimestamp();
    $previous_period_end_date = $current_period_start_date->modify('-1 second');
    $previous_period_start_date = $previous_period_end_date->setTimestamp(
        $previous_period_end_date->getTimestamp() - $period_length
    );

    return array(
        'date_range' => $date_range,
        'custom_start_date' => $custom_start_date,
        'custom_end_date' => $custom_end_date,
        'current_period_start' => $current_period_start_date->format('Y-m-d H:i:s'),
        'current_period_end' => $current_period_end_date->format('Y-m-d H:i:s'),
        'previous_period_start' => $previous_period_start_date->format('Y-m-d H:i:s'),
        'previous_period_end' => $previous_period_end_date->format('Y-m-d H:i:s'),
    );
}

/**
 * Validate an analytics date.
 *
 * @param string|null $date Date in Y-m-d format.
 * @return string|null
 */
function wpiko_chatbot_pro_validate_analytics_date($date) {
    if (!is_string($date) || '' === $date) {
        return null;
    }

    $parsed_date = DateTime::createFromFormat('!Y-m-d', $date);
    $date_errors = DateTime::getLastErrors();
    $has_errors = is_array($date_errors) && (0 < $date_errors['warning_count'] || 0 < $date_errors['error_count']);

    if (!$parsed_date || $has_errors || $parsed_date->format('Y-m-d') !== $date) {
        return null;
    }

    return $date;
}

/**
 * Get the top five user locations for an analytics period.
 *
 * @param string $location_view       country, city, or region.
 * @param string $current_period_start Period start timestamp.
 * @param string $current_period_end   Period end timestamp.
 * @return array
 */
function wpiko_chatbot_pro_get_location_distribution($location_view, $current_period_start, $current_period_end) {
    global $wpdb;

    $location_columns = array(
        'country' => 'country',
        'city' => 'city',
        'region' => 'region',
    );
    $location_view = isset($location_columns[$location_view]) ? $location_view : 'country';
    $location_column = $location_columns[$location_view];
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT {$location_column} AS location, COUNT(DISTINCT session_id) AS count
            FROM {$table_name}
            WHERE {$location_column} IS NOT NULL
                AND {$location_column} != ''
                AND timestamp >= %s
                AND timestamp <= %s
            GROUP BY {$location_column}
            ORDER BY count DESC
            LIMIT 5",
            $current_period_start,
            $current_period_end
        )
    );
}

/**
 * Return every dataset used by the Analytics dashboard.
 *
 * @param array  $period          Resolved analytics period.
 * @param string $location_view   country, city, or region.
 * @param bool   $include_premium Whether to include chart and breakdown data.
 * @return array
 */
function wpiko_chatbot_pro_get_analytics_snapshot($period, $location_view = 'country', $include_premium = true) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    $current_period_start = $period['current_period_start'];
    $current_period_end = $period['current_period_end'];
    $previous_period_start = $period['previous_period_start'];
    $previous_period_end = $period['previous_period_end'];

    $overall = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COUNT(DISTINCT session_id) AS total_conversations,
                SUM(CASE WHEN role IN ('user', 'assistant') THEN 1 ELSE 0 END) AS total_messages,
                COUNT(DISTINCT CASE WHEN user_email != '' THEN user_email ELSE session_id END) AS total_users,
                SUM(CASE WHEN role = 'error' THEN 1 ELSE 0 END) AS error_count,
                COUNT(*) AS total_count
            FROM {$table_name}
            WHERE timestamp >= %s AND timestamp <= %s",
            $current_period_start,
            $current_period_end
        ),
        ARRAY_A
    );

    $total_conversations = isset($overall['total_conversations']) ? (int) $overall['total_conversations'] : 0;
    $total_messages = isset($overall['total_messages']) ? (int) $overall['total_messages'] : 0;
    $total_users = isset($overall['total_users']) ? (int) $overall['total_users'] : 0;
    $error_count = isset($overall['error_count']) ? (int) $overall['error_count'] : 0;
    $total_count = isset($overall['total_count']) ? (int) $overall['total_count'] : 0;

    $previous_period_conversations = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id)
            FROM {$table_name}
            WHERE timestamp >= %s AND timestamp <= %s",
            $previous_period_start,
            $previous_period_end
        )
    );

    $conversation_change = 0;
    if ($previous_period_conversations > 0) {
        $conversation_change = round(
            (($total_conversations - $previous_period_conversations) / $previous_period_conversations) * 100,
            1
        );
    } elseif ($total_conversations > 0) {
        $conversation_change = 100;
    }

    $avg_messages = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT AVG(message_count) FROM (
                SELECT session_id, COUNT(*) AS message_count
                FROM {$table_name}
                WHERE role IN ('user', 'assistant')
                    AND timestamp >= %s
                    AND timestamp <= %s
                GROUP BY session_id
            ) AS conversation_counts",
            $current_period_start,
            $current_period_end
        )
    );

    $snapshot = array(
        'has_data' => $total_conversations > 0,
        'total_conversations' => $total_conversations,
        'total_messages' => $total_messages,
        'total_users' => $total_users,
        'conversation_change' => $conversation_change,
        'avg_messages' => $avg_messages,
        'error_count' => $error_count,
        'error_rate' => $total_count > 0 ? ($error_count / $total_count) * 100 : 0,
        'daily_messages' => array(),
        'locations' => array(),
        'conversation_length_bins' => array(),
        'busy_hours' => array(),
        'total_user_messages' => 0,
        'device_counts' => array(
            'desktop' => 0,
            'mobile' => 0,
            'tablet' => 0,
        ),
        'device_percentages' => array(
            'desktop' => 0,
            'mobile' => 0,
            'tablet' => 0,
        ),
    );

    if (!$include_premium) {
        return $snapshot;
    }

    $daily_results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                DATE(timestamp) AS date,
                SUM(CASE WHEN role IN ('user', 'assistant') THEN 1 ELSE 0 END) AS total_count,
                SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) AS user_count,
                SUM(CASE WHEN role = 'assistant' THEN 1 ELSE 0 END) AS assistant_count,
                SUM(CASE WHEN role = 'error' THEN 1 ELSE 0 END) AS error_count
            FROM {$table_name}
            WHERE timestamp >= %s AND timestamp <= %s
            GROUP BY DATE(timestamp)",
            $current_period_start,
            $current_period_end
        ),
        ARRAY_A
    );
    $daily_lookup = array();
    foreach ($daily_results as $row) {
        $daily_lookup[$row['date']] = array(
            'date' => $row['date'],
            'total_count' => (int) $row['total_count'],
            'user_count' => (int) $row['user_count'],
            'assistant_count' => (int) $row['assistant_count'],
            'error_count' => (int) $row['error_count'],
        );
    }

    $site_timezone = wpiko_chatbot_pro_get_site_timezone();
    $current_date = new DateTimeImmutable($current_period_start, $site_timezone);
    $end_date = new DateTimeImmutable($current_period_end, $site_timezone);
    while ($current_date <= $end_date) {
        $date_key = $current_date->format('Y-m-d');
        $snapshot['daily_messages'][] = isset($daily_lookup[$date_key])
            ? $daily_lookup[$date_key]
            : array(
                'date' => $date_key,
                'total_count' => 0,
                'user_count' => 0,
                'assistant_count' => 0,
                'error_count' => 0,
            );
        $current_date = $current_date->modify('+1 day');
    }

    $conversation_lengths = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT COUNT(*) AS message_count
            FROM {$table_name}
            WHERE role IN ('user', 'assistant')
                AND timestamp >= %s
                AND timestamp <= %s
            GROUP BY session_id",
            $current_period_start,
            $current_period_end
        )
    );
    $conversation_length_bins = array(
        '1-2 msgs' => 0,
        '3-5 msgs' => 0,
        '6-10 msgs' => 0,
        '11-20 msgs' => 0,
        '20+ msgs' => 0,
    );
    foreach ($conversation_lengths as $message_count) {
        $message_count = (int) $message_count;
        if ($message_count <= 2) {
            $conversation_length_bins['1-2 msgs']++;
        } elseif ($message_count <= 5) {
            $conversation_length_bins['3-5 msgs']++;
        } elseif ($message_count <= 10) {
            $conversation_length_bins['6-10 msgs']++;
        } elseif ($message_count <= 20) {
            $conversation_length_bins['11-20 msgs']++;
        } else {
            $conversation_length_bins['20+ msgs']++;
        }
    }
    $snapshot['conversation_length_bins'] = $conversation_length_bins;

    $locations = wpiko_chatbot_pro_get_location_distribution(
        $location_view,
        $current_period_start,
        $current_period_end
    );
    foreach ($locations as $location) {
        $snapshot['locations'][] = array(
            'location' => (string) $location->location,
            'count' => (int) $location->count,
        );
    }

    $busy_hours = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT HOUR(timestamp) AS hour, COUNT(*) AS count
            FROM {$table_name}
            WHERE timestamp >= %s AND timestamp <= %s
            GROUP BY HOUR(timestamp)
            ORDER BY count DESC
            LIMIT 3",
            $current_period_start,
            $current_period_end
        ),
        ARRAY_A
    );
    foreach ($busy_hours as $hour) {
        $snapshot['busy_hours'][] = array(
            'hour' => (int) $hour['hour'],
            'count' => (int) $hour['count'],
        );
    }

    $device_stats = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COALESCE(device_type, 'desktop') AS device_type, COUNT(*) AS count
            FROM {$table_name}
            WHERE timestamp >= %s
                AND timestamp <= %s
                AND role = 'user'
            GROUP BY device_type",
            $current_period_start,
            $current_period_end
        ),
        ARRAY_A
    );
    foreach ($device_stats as $device_stat) {
        $device_type = $device_stat['device_type'];
        $device_count = (int) $device_stat['count'];
        $snapshot['total_user_messages'] += $device_count;
        if (isset($snapshot['device_counts'][$device_type])) {
            $snapshot['device_counts'][$device_type] += $device_count;
        }
    }
    if ($snapshot['total_user_messages'] > 0) {
        foreach ($snapshot['device_counts'] as $device_type => $device_count) {
            $snapshot['device_percentages'][$device_type] =
                ($device_count / $snapshot['total_user_messages']) * 100;
        }
    }

    return $snapshot;
}

/**
 * Return a current Analytics dashboard snapshot for live updates.
 */
function wpiko_chatbot_pro_ajax_get_analytics_snapshot() {
    check_ajax_referer('wpiko_chatbot_analytics_live', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(
            array('message' => __('You are not allowed to view chatbot analytics.', 'wpiko-chatbot-pro')),
            403
        );
    }

    $is_premium = function_exists('wpiko_chatbot_is_license_active') && wpiko_chatbot_is_license_active();
    $date_range = $is_premium && isset($_POST['date_range'])
        ? sanitize_key(wp_unslash($_POST['date_range']))
        : '7';
    $custom_start_date = $is_premium && isset($_POST['start_date'])
        ? sanitize_text_field(wp_unslash($_POST['start_date']))
        : null;
    $custom_end_date = $is_premium && isset($_POST['end_date'])
        ? sanitize_text_field(wp_unslash($_POST['end_date']))
        : null;
    $allowed_location_views = array('country', 'city', 'region');
    $location_view = isset($_POST['location_view'])
        ? sanitize_key(wp_unslash($_POST['location_view']))
        : 'country';
    if (!in_array($location_view, $allowed_location_views, true)) {
        $location_view = 'country';
    }

    $period = wpiko_chatbot_pro_get_analytics_period(
        $date_range,
        $custom_start_date,
        $custom_end_date
    );
    $snapshot = wpiko_chatbot_pro_get_analytics_snapshot($period, $location_view, $is_premium);

    wp_send_json_success(
        array(
            'snapshot' => $snapshot,
            'period' => $period,
            'location_view' => $location_view,
        )
    );
}
add_action('wp_ajax_wpiko_chatbot_get_analytics_snapshot', 'wpiko_chatbot_pro_ajax_get_analytics_snapshot');

/**
 * Return one Top User Locations dataset without reloading the Analytics page.
 */
function wpiko_chatbot_pro_ajax_get_analytics_locations() {
    check_ajax_referer('wpiko_chatbot_analytics_locations', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(
            array('message' => __('You are not allowed to view chatbot analytics.', 'wpiko-chatbot-pro')),
            403
        );
    }

    if (!function_exists('wpiko_chatbot_is_license_active') || !wpiko_chatbot_is_license_active()) {
        wp_send_json_error(
            array('message' => __('An active license is required to view location analytics.', 'wpiko-chatbot-pro')),
            403
        );
    }

    $allowed_location_views = array('country', 'city', 'region');
    $location_view = isset($_POST['location_view']) ? sanitize_key(wp_unslash($_POST['location_view'])) : '';

    if (!in_array($location_view, $allowed_location_views, true)) {
        wp_send_json_error(
            array('message' => __('Invalid location view.', 'wpiko-chatbot-pro')),
            400
        );
    }

    $date_range = isset($_POST['date_range']) ? sanitize_key(wp_unslash($_POST['date_range'])) : '7';
    $custom_start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : null;
    $custom_end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : null;
    $period = wpiko_chatbot_pro_get_analytics_period($date_range, $custom_start_date, $custom_end_date);
    $locations = wpiko_chatbot_pro_get_location_distribution(
        $location_view,
        $period['current_period_start'],
        $period['current_period_end']
    );

    wp_send_json_success(
        array(
            'view' => $location_view,
            'locations' => $locations,
        )
    );
}
add_action('wp_ajax_wpiko_chatbot_get_analytics_locations', 'wpiko_chatbot_pro_ajax_get_analytics_locations');
