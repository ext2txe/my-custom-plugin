<?php
/**
 * Plugin Name: My Custom Plugin - Simple Chat
 * Description: Adds a Simple Chat page and shortcode that sends user messages to a mock LLM response endpoint.
 * Version: 0.1.0
 * Author: Local Dev
 */

if (!defined('ABSPATH')) {
    exit;
}

const MCP_SIMPLE_CHAT_PAGE_TITLE = 'Simple Chat';
const MCP_SIMPLE_CHAT_PAGE_SLUG = 'simple-chat';
const MCP_SIMPLE_CHAT_SHORTCODE = 'my_custom_simple_chat';

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
        '0.1.0',
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

    $timestamp = wp_date('Y-m-d H:i:s');
    $responseText = sprintf('[%s] %s', $timestamp, $message);

    wp_send_json_success([
        'response' => $responseText,
    ]);
}
add_action('wp_ajax_mcp_simple_chat_message', 'mcp_handle_simple_chat_message');
add_action('wp_ajax_nopriv_mcp_simple_chat_message', 'mcp_handle_simple_chat_message');
