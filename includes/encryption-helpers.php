<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption helpers for sensitive data at rest.
 *
 * Uses AES-256-GCM with a key derived from the WordPress auth salt.
 * Encrypted values are prefixed with "enc:" to separate IV, tag, and ciphertext.
 */

define('WPIKO_CHATBOT_ENC_PREFIX', 'enc:');
define('WPIKO_CHATBOT_ENC_CIPHER', 'aes-256-gcm');

/**
 * Derive a 256-bit encryption key from the WordPress auth salt.
 */
function wpiko_chatbot_pro_get_encryption_key() {
    $salt = wp_salt('auth');

    return hash('sha256', $salt, true);
}

/**
 * Encrypt a plain-text value.
 *
 * Returns a prefixed, base64-encoded string: "enc:<iv>:<tag>:<ciphertext>"
 * Returns false if encryption is unavailable so callers can handle the failure.
 */
function wpiko_chatbot_pro_encrypt($plain_text) {
    if ($plain_text === '' || $plain_text === false) {
        return $plain_text;
    }

    if (!function_exists('openssl_encrypt') || !in_array(WPIKO_CHATBOT_ENC_CIPHER, openssl_get_cipher_methods(), true)) {
        return false;
    }

    $key = wpiko_chatbot_pro_get_encryption_key();
    $iv = openssl_random_pseudo_bytes(12);
    $tag = '';

    $ciphertext = openssl_encrypt($plain_text, WPIKO_CHATBOT_ENC_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

    if ($ciphertext === false) {
        return false;
    }

    return WPIKO_CHATBOT_ENC_PREFIX . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
}

/**
 * Decrypt a value previously encrypted with wpiko_chatbot_pro_encrypt().
 *
 * Returns false if the value is not a valid encrypted payload or decryption fails.
 */
function wpiko_chatbot_pro_decrypt($encrypted) {
    if (!is_string($encrypted) || $encrypted === '') {
        return $encrypted;
    }

    if (strpos($encrypted, WPIKO_CHATBOT_ENC_PREFIX) !== 0) {
        return false;
    }

    if (!function_exists('openssl_decrypt') || !in_array(WPIKO_CHATBOT_ENC_CIPHER, openssl_get_cipher_methods(), true)) {
        return false;
    }

    $payload = substr($encrypted, strlen(WPIKO_CHATBOT_ENC_PREFIX));
    $parts = explode(':', $payload, 3);

    if (count($parts) !== 3) {
        return false;
    }

    $iv = base64_decode($parts[0], true);
    $tag = base64_decode($parts[1], true);
    $ciphertext = base64_decode($parts[2], true);

    if ($iv === false || $tag === false || $ciphertext === false) {
        return false;
    }

    $key = wpiko_chatbot_pro_get_encryption_key();

    $plain_text = openssl_decrypt($ciphertext, WPIKO_CHATBOT_ENC_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($plain_text === false) {
        return false;
    }

    return $plain_text;
}
