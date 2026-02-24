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
    $instructions = "You have access to a contact form that users can fill out to reach the website team.\n\n";
    $instructions .= "WHEN TO OFFER THE CONTACT FORM:\n";
    $instructions .= $trigger_text . "\n\n";

    // Describe the output format
    $instructions .= "HOW TO OFFER THE CONTACT FORM:\n";
    $instructions .= "When you decide the user should be offered the contact form, include the following marker in your response exactly as shown, on its own line:\n";

    if ($enable_prefill === '1') {
        $instructions .= '[wpiko-contact-form:{"message":"A clear summary of the user inquiry based on the conversation","category":"The best matching category if available"}]' . "\n\n";
        $instructions .= "The marker must contain valid JSON inside the square brackets after the colon. The form will be pre-filled with the data you provide.\n\n";
        $instructions .= "IMPORTANT RULES FOR THE MARKER:\n";
        $instructions .= "- Always output the marker exactly as shown: opening bracket, wpiko-contact-form:, then JSON, then closing bracket.\n";
        $instructions .= "- The \"message\" field should summarize what the user needs help with based on the conversation so far. Write it as if the user is writing to support.\n";
        $instructions .= "- Write the message in the same language the user is communicating in.\n";
        $instructions .= "- Do NOT include the user's name or email in the message — those are collected separately.\n";
        $instructions .= "- Do NOT wrap the marker in code blocks, backticks, or any formatting.\n";
    } else {
        $instructions .= '[wpiko-contact-form:{}]' . "\n\n";
        $instructions .= "The marker will display a contact form for the user to fill out.\n";
        $instructions .= "- Do NOT wrap the marker in code blocks, backticks, or any formatting.\n\n";
    }

    // Add available categories if dropdown is enabled
    $enable_dropdown = get_option('wpiko_chatbot_contact_form_dropdown', '0');
    $dropdown_options = get_option('wpiko_chatbot_contact_form_dropdown_options', '');

    if ($enable_dropdown === '1' && !empty($dropdown_options)) {
        $categories = array_filter(array_map('trim', explode("\n", $dropdown_options)));
        if (!empty($categories)) {
            $instructions .= "AVAILABLE CATEGORIES:\n";
            $instructions .= "The \"category\" field in the marker should be one of the following (use exact text):\n";
            foreach ($categories as $cat) {
                $instructions .= "- " . $cat . "\n";
            }
            $instructions .= "Choose the category that best matches the user's inquiry. If none fit well, omit the category field.\n\n";
        }
    }

    // Add custom field info if enabled and prefill is on
    if ($enable_prefill === '1') {
        $custom_fields_info = array();
        for ($i = 1; $i <= 2; $i++) {
            $field_enabled = get_option("wpiko_chatbot_contact_form_custom_field_{$i}", '0');
            $field_label = get_option("wpiko_chatbot_contact_form_custom_field_{$i}_label", '');
            if ($field_enabled === '1' && !empty($field_label)) {
                $custom_fields_info[] = array('key' => "field{$i}", 'label' => $field_label);
            }
        }

        if (!empty($custom_fields_info)) {
            $instructions .= "CUSTOM FIELDS:\n";
            $instructions .= "The contact form also has these custom fields that you can pre-fill if the information is available in the conversation:\n";
            foreach ($custom_fields_info as $info) {
                $instructions .= "- \"" . $info['key'] . "\": " . $info['label'] . "\n";
            }
            $instructions .= "\n";
        }
    }

    $instructions .= "GUIDELINES:\n";
    $instructions .= "- You can include a brief, helpful message before the marker to provide context to the user (e.g., \"I've prepared a contact form with your details — feel free to review and send it.\").\n";
    $instructions .= "- Only include ONE marker per response.\n";
    $instructions .= "- Do NOT use the marker if the user's question can be answered directly.\n";
    $instructions .= "- The marker will be replaced with a Contact Form button that the user can see and click.";

    return $instructions;
}
