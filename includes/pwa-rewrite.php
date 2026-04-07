<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PWA Rewrite Rules & Serving
 *
 * Registers /wpiko-app/ rewrite that serves the PWA index.html.
 * Only active when PWA is enabled via settings.
 */

/**
 * Determine whether the PWA is running in a local or development environment.
 */
function wpiko_chatbot_pro_is_local_pwa_environment() {
    $environment = wp_get_environment_type();

    return $environment === 'local' || $environment === 'development';
}

/**
 * Check whether the current request uses an allowed transport for the PWA.
 */
function wpiko_chatbot_pro_is_pwa_request_secure() {
    if (wpiko_chatbot_pro_is_local_pwa_environment()) {
        return true;
    }

    return is_ssl();
}

/**
 * Send security headers for the PWA shell response.
 */
function wpiko_chatbot_pro_send_pwa_shell_headers() {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Vary: Authorization');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https:; connect-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'");
}

/**
 * Enable Application Passwords on non-HTTPS (local dev) environments.
 * WordPress disables them over plain HTTP by default.
 */
function wpiko_chatbot_pro_enable_app_passwords($available) {
    if ($available) {
        return $available;
    }

    if (wpiko_chatbot_pro_is_local_pwa_environment()) {
        return true;
    }

    return $available;
}
add_filter('wp_is_application_passwords_available', 'wpiko_chatbot_pro_enable_app_passwords');

/**
 * Register the rewrite rule for /wpiko-app/
 */
function wpiko_chatbot_pro_pwa_rewrite_rules() {
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        return;
    }

    // Keep the route registered whenever the feature is enabled so rewrite
    // flushes during inactive-license states do not permanently remove it.
    // Access control still happens later in the request lifecycle.
    add_rewrite_rule('^wpiko-app/?$', 'index.php?wpiko_pwa=1', 'top');
}
add_action('init', 'wpiko_chatbot_pro_pwa_rewrite_rules');

/**
 * Register the custom query var
 */
function wpiko_chatbot_pro_pwa_query_vars($vars) {
    $vars[] = 'wpiko_pwa';
    return $vars;
}
add_filter('query_vars', 'wpiko_chatbot_pro_pwa_query_vars');

/**
 * Intercept the template and serve PWA files
 */
function wpiko_chatbot_pro_pwa_template_redirect() {
    // Serve PWA shell
    if (get_query_var('wpiko_pwa') === '1') {
        if (!wpiko_chatbot_pro_is_pwa_request_secure()) {
            wp_die('The Mobile App requires HTTPS on live sites.', 'HTTPS Required', array('response' => 403));
        }
        if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
            wp_die('The Mobile App feature is currently disabled.', 'Feature Disabled', array('response' => 403));
        }
        if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
            wp_die('This feature requires an active premium license.', 'License Required', array('response' => 403));
        }
        $index_file = WPIKO_CHATBOT_PRO_PATH . 'pwa/index.html';
        if (file_exists($index_file)) {
            $app_name = get_option('wpiko_chatbot_pwa_app_name', get_bloginfo('name') . ' Chat');
            $safe_app_name = esc_html($app_name);

            ob_start(
                static function ($html) use ($safe_app_name) {
                    return str_replace(
                        array('<title>WPiko Chat</title>', '<h1>WPiko Chat</h1>'),
                        array('<title>' . $safe_app_name . '</title>', '<h1>' . $safe_app_name . '</h1>'),
                        $html
                    );
                }
            );

            wpiko_chatbot_pro_send_pwa_shell_headers();
            readfile($index_file);
            ob_end_flush();
            exit;
        }
        wp_die('PWA not found', 'Not Found', array('response' => 404));
    }
}
add_action('template_redirect', 'wpiko_chatbot_pro_pwa_template_redirect');

/**
 * Serve PWA static assets (CSS, JS, manifest, service-worker)
 * These need direct file access, not WordPress rewrites.
 */
function wpiko_chatbot_pro_pwa_serve_assets() {
    if (get_option('wpiko_chatbot_enable_pwa', '0') !== '1') {
        return;
    }
    if (!function_exists('wpiko_chatbot_pro_is_license_active') || !wpiko_chatbot_pro_is_license_active()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $parsed = wp_parse_url($request_uri);
    $path = isset($parsed['path']) ? $parsed['path'] : '';

    // Match /wpiko-app/<asset>
    if (!preg_match('#^/wpiko-app/(.+)$#', $path, $matches)) {
        return;
    }

    if (!wpiko_chatbot_pro_is_pwa_request_secure()) {
        status_header(403);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo 'The Mobile App requires HTTPS on live sites.';
        exit;
    }

    $asset = $matches[1];

    // Route map for allowed sub-paths. The extensionless aliases avoid hosts
    // that treat virtual .css/.js paths as missing static files instead of
    // forwarding them through WordPress.
    $routes = array(
        'manifest.json' => array(
            'file' => 'manifest.json',
            'content_type' => 'application/json',
        ),
        'service-worker.js' => array(
            'file' => 'service-worker.js',
            'content_type' => 'application/javascript',
            'service_worker' => true,
        ),
        'css/pwa-style.css' => array(
            'file' => 'css/pwa-style.css',
            'content_type' => 'text/css',
        ),
        'js/app.js' => array(
            'file' => 'js/app.js',
            'content_type' => 'application/javascript',
        ),
        'style' => array(
            'file' => 'css/pwa-style.css',
            'content_type' => 'text/css',
        ),
        'app' => array(
            'file' => 'js/app.js',
            'content_type' => 'application/javascript',
        ),
        'sw' => array(
            'file' => 'service-worker.js',
            'content_type' => 'application/javascript',
            'service_worker' => true,
        ),
        'icons/icon.svg' => array(
            'file' => 'icons/icon.svg',
            'content_type' => 'image/svg+xml',
            'charset' => false,
        ),
        'icons/apple-touch-icon.png' => array(
            'file' => 'icons/apple-touch-icon.png',
            'content_type' => 'image/png',
            'charset' => false,
        ),
        'icons/icon-192.png' => array(
            'file' => 'icons/icon-192.png',
            'content_type' => 'image/png',
            'charset' => false,
        ),
        'icons/icon-512.png' => array(
            'file' => 'icons/icon-512.png',
            'content_type' => 'image/png',
            'charset' => false,
        ),
    );

    if (!isset($routes[$asset])) {
        return;
    }

    $route = $routes[$asset];
    $file = WPIKO_CHATBOT_PRO_PATH . 'pwa/' . $route['file'];
    if (!file_exists($file)) {
        return;
    }

    $content_type = $route['content_type'];
    $append_charset = !isset($route['charset']) || $route['charset'] !== false;

    header('Content-Type: ' . $content_type . ($append_charset ? '; charset=UTF-8' : ''));

    // Service worker must not be cached by the browser
    if (!empty($route['service_worker'])) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Service-Worker-Allowed: /wpiko-app/');
    } else {
        header('Cache-Control: public, max-age=86400');
    }

    // Dynamically inject the saved app name into manifest.json
    if ($route['file'] === 'manifest.json') {
        $app_name = get_option('wpiko_chatbot_pwa_app_name', get_bloginfo('name') . ' Chat');
        $manifest = json_decode(file_get_contents($file), true);
        if (is_array($manifest)) {
            $manifest['name'] = $app_name;
            $manifest['short_name'] = $app_name;
            echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }

    readfile($file);
    exit;
}
// Run very early so WordPress doesn't try to parse these as pages
add_action('parse_request', 'wpiko_chatbot_pro_pwa_serve_assets', 1);

/**
 * Mark rewrite rules for flushing on the next safe request lifecycle point.
 */
function wpiko_chatbot_pro_schedule_pwa_rewrite_flush() {
    update_option('wpiko_chatbot_pro_pending_pwa_flush', '1');
}

/**
 * Flush rewrite rules once WordPress rewrite infrastructure is initialized.
 */
function wpiko_chatbot_pro_maybe_flush_scheduled_pwa_rules() {
    if (get_option('wpiko_chatbot_pro_pending_pwa_flush', '0') !== '1') {
        return;
    }

    wpiko_chatbot_pro_pwa_flush_rules();
    delete_option('wpiko_chatbot_pro_pending_pwa_flush');
}
add_action('init', 'wpiko_chatbot_pro_maybe_flush_scheduled_pwa_rules', 20);

/**
 * Flush rewrite rules on activation
 */
function wpiko_chatbot_pro_pwa_flush_rules() {
    global $wp_rewrite;

    if (!$wp_rewrite instanceof WP_Rewrite) {
        wpiko_chatbot_pro_schedule_pwa_rewrite_flush();
        return;
    }

    wpiko_chatbot_pro_pwa_rewrite_rules();
    flush_rewrite_rules();
}
