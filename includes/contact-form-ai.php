<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Contact Form AI Integration
 * 
 * Automatically injects contact form instructions into the AI system prompt
 * so the AI can intelligently offer the contact form and pre-fill it with
 * conversation context.
 */

/**
 * Auto-inject contact form instructions into AI system prompt
 * 
 * Hooked into 'wpiko_chatbot_combined_instructions' filter from the base plugin.
 * Only injects when both the contact form and AI response features are enabled.
 */
function wpiko_chatbot_pro_inject_contact_form_instructions($combined) {
    // Check if license is active
    if (!wpiko_chatbot_is_license_active()) {
        return $combined;
    }

    // Check if contact form is enabled
    if (get_option('wpiko_chatbot_enable_contact_form', '0') !== '1') {
        return $combined;
    }

    // Check if AI contact form response is enabled
    if (get_option('wpiko_chatbot_contact_form_ai_response', '0') !== '1') {
        return $combined;
    }

    // Build the contact form instruction block
    $contact_instructions = wpiko_chatbot_pro_build_contact_form_instructions();

    if (!empty($contact_instructions)) {
        if (!empty($combined)) {
            $combined .= "\n\n=====\n\n";
        }
        $combined .= "CONTACT FORM INSTRUCTIONS:\n\n" . $contact_instructions;
    }

    return $combined;
}
add_filter('wpiko_chatbot_combined_instructions', 'wpiko_chatbot_pro_inject_contact_form_instructions', 20);

/**
 * Build the contact form AI instruction text
 * 
 * Generates the instruction block that tells the AI when and how to offer
 * the contact form, including available categories and custom fields.
 */
function wpiko_chatbot_pro_build_contact_form_instructions() {
    // Get the AI trigger behavior
    $trigger_behavior = get_option('wpiko_chatbot_contact_form_ai_trigger', 'support');

    // Get custom trigger instructions if set
    $custom_trigger = get_option('wpiko_chatbot_contact_form_ai_custom_trigger', '');

    // Check if form pre-fill is enabled
    $enable_prefill = get_option('wpiko_chatbot_contact_form_ai_prefill', '1');

    // Build trigger condition text
    switch ($trigger_behavior) {
        case 'explicit':
            $trigger_text = 'Only when the user explicitly asks to contact support, send a message, or reach a human.';
            break;
        case 'custom':
            $trigger_text = !empty($custom_trigger) ? $custom_trigger : 'When the user needs human assistance or wants to contact support.';
            break;
        case 'support':
        default:
            $trigger_text = 'When the user needs human assistance, wants to report a problem, submit a complaint, ask a question that requires human follow-up, or when you cannot adequately help them.';
            break;
    }

    // Build the instruction
    $instructions = "You can offer users a contact form to reach the website team.\n\n";
    $instructions .= "WHEN TO OFFER IT:\n";
    $instructions .= $trigger_text . " Never offer it when you can answer the user's question directly.\n\n";

    // Describe the output format
    $instructions .= "HOW TO OFFER IT:\n";
    $instructions .= "Place this marker on its own line, in exactly this format — never wrapped in code blocks, backticks, or other formatting, at most one per response (it renders as a Contact Form button):\n";

    if ($enable_prefill === '1') {
        // Categories are only pre-fillable via the marker JSON, so they only
        // apply when prefill is on; the example marker mirrors the form config
        $enable_dropdown = get_option('wpiko_chatbot_contact_form_dropdown', '0');
        $dropdown_options = get_option('wpiko_chatbot_contact_form_dropdown_options', '');
        $categories = array();
        if ($enable_dropdown === '1' && !empty($dropdown_options)) {
            $categories = array_filter(array_map('trim', explode("\n", $dropdown_options)));
        }

        if (!empty($categories)) {
            $instructions .= '[wpiko-contact-form:{"message":"Summary of the user\'s inquiry","category":"Best matching category"}]' . "\n\n";
        } else {
            $instructions .= '[wpiko-contact-form:{"message":"Summary of the user\'s inquiry"}]' . "\n\n";
        }
        $instructions .= "The JSON pre-fills the form. Write \"message\" as a concise summary of what the user needs, phrased as if the user is writing to support, in the user's own language. Do not include the user's name or email — those are collected separately. You may add one brief sentence before the marker letting the user know the form is ready.\n";

        if (!empty($categories)) {
            $instructions .= "\nCATEGORIES:\n";
            $instructions .= "Set \"category\" to one of these exact values, or omit it if none fit:\n";
            foreach ($categories as $cat) {
                $instructions .= "- " . $cat . "\n";
            }
        }

        // Add custom field info if enabled
        $custom_fields_info = array();
        for ($i = 1; $i <= 2; $i++) {
            $field_enabled = get_option("wpiko_chatbot_contact_form_custom_field_{$i}", '0');
            $field_label = get_option("wpiko_chatbot_contact_form_custom_field_{$i}_label", '');
            if ($field_enabled === '1' && !empty($field_label)) {
                $custom_fields_info[] = array('key' => "field{$i}", 'label' => $field_label);
            }
        }

        if (!empty($custom_fields_info)) {
            $instructions .= "\nCUSTOM FIELDS:\n";
            $instructions .= "Also pre-fill these keys in the marker JSON when the information appears in the conversation:\n";
            foreach ($custom_fields_info as $info) {
                $instructions .= "- \"" . $info['key'] . "\": " . $info['label'] . "\n";
            }
        }

        $instructions = rtrim($instructions);
    } else {
        $instructions .= '[wpiko-contact-form:{}]' . "\n\n";
        $instructions .= "You may add one brief sentence before the marker letting the user know the form is ready.";
    }

    return $instructions;
}
