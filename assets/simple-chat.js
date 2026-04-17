(function () {
    function getElement(id) {
        return document.getElementById(id);
    }

    function initializeSimpleChat() {
        var input = getElement('mcp-user-message');
        var response = getElement('mcp-llm-response');
        var sendButton = getElement('mcp-send-message');
        var clearButton = getElement('mcp-clear-chat');

        if (!input || !response || !sendButton || !clearButton || !window.mcpSimpleChatConfig) {
            return;
        }

        sendButton.addEventListener('click', function () {
            var message = input.value.trim();

            if (!message) {
                response.value = 'Please enter a message.';
                return;
            }

            response.value = 'Sending...';

            var params = new URLSearchParams();
            params.append('action', 'mcp_simple_chat_message');
            params.append('nonce', window.mcpSimpleChatConfig.nonce);
            params.append('message', message);

            fetch(window.mcpSimpleChatConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: params.toString(),
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    if (data && data.success && data.data && data.data.response) {
                        response.value = data.data.response;
                        return;
                    }

                    if (data && data.data && data.data.message) {
                        response.value = data.data.message;
                        return;
                    }

                    response.value = 'Request failed.';
                })
                .catch(function () {
                    response.value = 'Request failed.';
                });
        });

        clearButton.addEventListener('click', function () {
            input.value = '';
            response.value = '';
            input.focus();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSimpleChat);
    } else {
        initializeSimpleChat();
    }
})();
