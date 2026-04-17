<?php
/**
 * Plugin Name: My Custom Plugin - Simple Chat
 * Description: Adds a Simple Chat page and shortcode that sends user messages to OpenAI.
 * Version: 0.1.2
 * Author: Local Dev
 */

if (!defined('ABSPATH')) {
    exit;
}

const MCP_SIMPLE_CHAT_PAGE_TITLE = 'Simple Chat';
const MCP_SIMPLE_CHAT_PAGE_SLUG = 'simple-chat';
const MCP_SIMPLE_CHAT_SHORTCODE = 'my_custom_simple_chat';
const MCP_SIMPLE_CHAT_LEGACY_SHORTCODE = 'my_cusstom_simple_chat';
const MCP_SIMPLE_CHAT_VERSION = '0.1.2';

/**
 * Creates the Simple Chat page on activation.
 *
 * @return void
 */
function mcp_activate_plugin_create_simple_chat_page(): void
{
    $existingPage = get_page_by_path(MCP_SIMPLE_CHAT_PAGE_SLUG);

    if ($existingPage instanceof WP_Post) {
        return;
    }

    wp_insert_post([
        'post_title' => MCP_SIMPLE_CHAT_PAGE_TITLE,
        'post_name' => MCP_SIMPLE_CHAT_PAGE_SLUG,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '[' . MCP_SIMPLE_CHAT_SHORTCODE . ']',
    ]);
}
register_activation_hook(__FILE__, 'mcp_activate_plugin_create_simple_chat_page');

/**
 * Registers frontend assets.
 *
 * @return void
 */
function mcp_register_simple_chat_assets(): void
{
    wp_register_script(
        'mcp-simple-chat',
        plugin_dir_url(__FILE__) . 'assets/simple-chat.js',
        [],
        MCP_SIMPLE_CHAT_VERSION,
        true
    );

    wp_localize_script('mcp-simple-chat', 'mcpSimpleChatConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mcp_simple_chat_nonce'),
    ]);
}
add_action('wp_enqueue_scripts', 'mcp_register_simple_chat_assets');

/**
 * Renders the simple chat user interface.
 *
 * @return string
 */
function mcp_render_simple_chat_shortcode(): string
{
    wp_enqueue_script('mcp-simple-chat');

    ob_start();
    ?>
    <div id="mcp-simple-chat" style="max-width: 700px; display: grid; gap: 12px;">
        <label for="mcp-user-message"><strong>Your message</strong></label>
        <textarea id="mcp-user-message" rows="5" placeholder="Type your message here..." style="width: 100%;"></textarea>

        <div style="display: flex; gap: 10px;">
            <button id="mcp-send-message" type="button">Send</button>
            <button id="mcp-clear-chat" type="button">Clear</button>
        </div>

        <label for="mcp-llm-response"><strong>LLM response</strong></label>
        <textarea id="mcp-llm-response" rows="6" readonly style="width: 100%;"></textarea>
    </div>
    <?php

    return (string) ob_get_clean();
}
add_shortcode(MCP_SIMPLE_CHAT_SHORTCODE, 'mcp_render_simple_chat_shortcode');
add_shortcode(MCP_SIMPLE_CHAT_LEGACY_SHORTCODE, 'mcp_render_simple_chat_shortcode');

/**
 * Gets the OpenAI API key from a WordPress constant or environment variable.
 *
 * @return string
 */
function mcp_get_openai_api_key(): string
{
    if (defined('OPENAI_API_KEY') && is_string(OPENAI_API_KEY)) {
        return trim(OPENAI_API_KEY);
    }

    $apiKey = getenv('OPENAI_API_KEY');

    return is_string($apiKey) ? trim($apiKey) : '';
}

/**
 * Gets the OpenAI model used for simple chat responses.
 *
 * @return string
 */
function mcp_get_openai_model(): string
{
    /**
     * Filters the OpenAI model used by the simple chat shortcode.
     *
     * @param string $model OpenAI model name.
     */
    $model = apply_filters('mcp_simple_chat_openai_model', 'gpt-4o-mini');

    return is_string($model) && trim($model) !== '' ? trim($model) : 'gpt-4o-mini';
}

/**
 * Extracts plain response text from the OpenAI Responses API payload.
 *
 * @param array<string, mixed> $body Decoded OpenAI response.
 * @return string
 */
function mcp_extract_openai_response_text(array $body): string
{
    if (isset($body['output_text']) && is_string($body['output_text'])) {
        return trim($body['output_text']);
    }

    if (!isset($body['output']) || !is_array($body['output'])) {
        return '';
    }

    $textParts = [];

    foreach ($body['output'] as $outputItem) {
        if (!is_array($outputItem) || !isset($outputItem['content']) || !is_array($outputItem['content'])) {
            continue;
        }

        foreach ($outputItem['content'] as $contentItem) {
            if (!is_array($contentItem) || !isset($contentItem['text']) || !is_string($contentItem['text'])) {
                continue;
            }

            $textParts[] = $contentItem['text'];
        }
    }

    return trim(implode("\n", $textParts));
}

/**
 * Sends a message to OpenAI and returns the response text.
 *
 * @param string $message User message.
 * @return string|WP_Error
 */
function mcp_send_message_to_openai(string $message)
{
    $apiKey = mcp_get_openai_api_key();

    if ($apiKey === '') {
        return new WP_Error(
            'mcp_openai_api_key_missing',
            'OpenAI API key is missing. Define OPENAI_API_KEY in wp-config.php or set the OPENAI_API_KEY environment variable.'
        );
    }

    $response = wp_remote_post('https://api.openai.com/v1/responses', [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'model' => mcp_get_openai_model(),
            'input' => $message,
        ]),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $statusCode = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($body)) {
        return new WP_Error('mcp_openai_invalid_response', 'OpenAI returned an invalid response.');
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $errorMessage = 'OpenAI request failed.';

        if (isset($body['error']) && is_array($body['error']) && isset($body['error']['message']) && is_string($body['error']['message'])) {
            $errorMessage = $body['error']['message'];
        }

        return new WP_Error('mcp_openai_request_failed', $errorMessage);
    }

    $responseText = mcp_extract_openai_response_text($body);

    if ($responseText === '') {
        return new WP_Error('mcp_openai_empty_response', 'OpenAI returned an empty response.');
    }

    return $responseText;
}

/**
 * Handles chat message requests.
 *
 * @return void
 */
function mcp_handle_simple_chat_message(): void
{
    check_ajax_referer('mcp_simple_chat_nonce', 'nonce');

    $rawMessage = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';
    $message = sanitize_textarea_field((string) $rawMessage);

    if ($message === '') {
        wp_send_json_error([
            'message' => 'Please enter a message.',
        ], 400);
    }

    $responseText = mcp_send_message_to_openai($message);

    if (is_wp_error($responseText)) {
        wp_send_json_error([
            'message' => $responseText->get_error_message(),
        ], 500);
    }

    wp_send_json_success([
        'response' => $responseText,
    ]);
}
add_action('wp_ajax_mcp_simple_chat_message', 'mcp_handle_simple_chat_message');
add_action('wp_ajax_nopriv_mcp_simple_chat_message', 'mcp_handle_simple_chat_message');
