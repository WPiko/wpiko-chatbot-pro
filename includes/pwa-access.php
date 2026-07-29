<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the capability map used for PWA conversation access.
 */
function wpiko_chatbot_pro_get_pwa_capabilities() {
    return array(
        'wpiko_chatbot_use_pwa' => true,
        'wpiko_chatbot_view_conversations' => true,
        'wpiko_chatbot_reply_conversations' => true,
        'wpiko_chatbot_takeover_conversations' => true,
        'wpiko_chatbot_manage_push' => true,
    );
}

/**
 * Ensure the Live Agent role and required capabilities exist.
 *
 * The role and capabilities are intentionally left in place on deactivation so
 * existing users keep a valid WordPress role if Pro is later removed.
 */
function wpiko_chatbot_pro_sync_pwa_access_role() {
    $pwa_caps = wpiko_chatbot_pro_get_pwa_capabilities();
    $agent_caps = array_merge(array('read' => true), $pwa_caps);

    $administrator_role = get_role('administrator');
    if ($administrator_role) {
        foreach ($agent_caps as $capability => $grant) {
            if ($grant) {
                $administrator_role->add_cap($capability);
            }
        }
    }

    $agent_role = get_role('wpiko_chatbot_agent');
    if (!$agent_role) {
        add_role('wpiko_chatbot_agent', 'Live Agent', $agent_caps);
        return;
    }

    foreach ($agent_caps as $capability => $grant) {
        if ($grant) {
            $agent_role->add_cap($capability);
        }
    }

    // Sites where the role predates the rename have an old display name
    // stored in the database; update it once in place.
    $old_labels = array('WPiko Agent', 'Support Agent');
    $wp_roles = wp_roles();
    if (isset($wp_roles->roles['wpiko_chatbot_agent']['name'])
        && in_array($wp_roles->roles['wpiko_chatbot_agent']['name'], $old_labels, true)) {
        $wp_roles->roles['wpiko_chatbot_agent']['name'] = 'Live Agent';
        $wp_roles->role_names['wpiko_chatbot_agent'] = 'Live Agent';
        update_option($wp_roles->role_key, $wp_roles->roles);
    }
}

/**
 * Check whether a user can access the WPiko PWA.
 */
function wpiko_chatbot_pro_user_can_access_pwa($user = null) {
    if ($user === null) {
        return current_user_can('manage_options') || current_user_can('wpiko_chatbot_use_pwa');
    }

    return user_can($user, 'manage_options') || user_can($user, 'wpiko_chatbot_use_pwa');
}

/**
 * Keep Application Passwords available for PWA users.
 */
function wpiko_chatbot_pro_enable_app_passwords_for_pwa_users($available, $user) {
    if ($available) {
        return $available;
    }

    if (!$user instanceof WP_User) {
        return $available;
    }

    return wpiko_chatbot_pro_user_can_access_pwa($user);
}
add_filter('wp_is_application_passwords_available_for_user', 'wpiko_chatbot_pro_enable_app_passwords_for_pwa_users', 10, 2);

add_action('init', 'wpiko_chatbot_pro_sync_pwa_access_role', 5);