$(function () {
    const $widget = $('#ai-chat-widget');

    if (!$widget.length) {
        return;
    }

    const $panel = $('#ai-chat-panel');
    const $messagesContainer = $('#ai-chat-messages');
    const $input = $('#ai-chat-input');
    const $clearButton = $('#ai-chat-clear');
    const $sendButton = $('#ai-chat-send');
    const $stopButton = $('#ai-chat-stop');

    let activeAbortController = null;
    const csrfToken = $widget.data('csrfToken') || '';

    const readCookieValue = (cookieName) => {
        const parts = document.cookie.split(';').map((item) => item.trim());
        const match = parts.find((item) => item.startsWith(`${cookieName}=`));

        if (!match) {
            return null;
        }

        return decodeURIComponent(match.substring(cookieName.length + 1));
    };

    const getXsrfTokenFromCookie = () => readCookieValue('XSRF-TOKEN');

    const setStreamingState = (isStreaming) => {
        $panel.toggleClass('ai-chat-panel-streaming', isStreaming);
        $sendButton.prop('disabled', isStreaming);
        $stopButton.prop('disabled', !isStreaming);
        $input.prop('disabled', isStreaming);
    };

    const appendMessage = (role, content, status = null) => {
        const $row = $('<div>').addClass(`ai-chat-message ${role}`);
        if (status) {
            $row.attr('data-status', status);
        }

        const $body = $('<div>').addClass('ai-chat-message-body').text(content || '');
        $row.append($body);

        $messagesContainer.append($row);
        $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);

        return $row;
    };

    const parseSseAndStreamIntoMessage = async (response, $assistantRow) => {
        const reader = response.body.getReader();
        const decoder = new TextDecoder('utf-8');
        const $bodyNode = $assistantRow.find('.ai-chat-message-body');
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });

            while (buffer.includes('\n\n')) {
                const idx = buffer.indexOf('\n\n');
                const rawEvent = buffer.slice(0, idx);
                buffer = buffer.slice(idx + 2);

                const lines = rawEvent.split('\n');
                let eventName = 'message';
                let dataLine = '{}';

                lines.forEach((line) => {
                    if (line.startsWith('event: ')) {
                        eventName = line.slice(7).trim();
                    }
                    if (line.startsWith('data: ')) {
                        dataLine = line.slice(6);
                    }
                });

                let eventData = {};
                try {
                    eventData = JSON.parse(dataLine);
                } catch (e) {
                    eventData = {};
                }

                if (eventName === 'delta' && eventData.content) {
                    $bodyNode.text($bodyNode.text() + eventData.content);
                    $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
                }

                if (eventName === 'error') {
                    const existing = $bodyNode.text() || '';
                    $bodyNode.text(`${existing}\n\n[${translateString('Error')}] ${eventData.message || translateString('Streaming failed')}`.trim());
                }
            }
        }
    };

    const sendMessage = async () => {
        const prompt = $input.val().trim();
        if (!prompt) {
            return;
        }

        //$messagesContainer.empty();

        appendMessage('user', prompt, 'completed');
        $input.val('');

        const $assistantRow = appendMessage('assistant', '', 'streaming');
        setStreamingState(true);

        const abortController = new AbortController();
        activeAbortController = abortController;

        try {
            const response = await fetch('/api/v1/ai/chat/stream', {
                method: 'POST',
                headers: {
                    'Accept': 'text/event-stream',
                    'Content-Type': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    ...(getXsrfTokenFromCookie() ? { 'X-XSRF-TOKEN': getXsrfTokenFromCookie() } : {}),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message: prompt }),
                signal: abortController.signal
            });

            if (!response.ok || !response.body) {
                const $assistantBody = $assistantRow.find('.ai-chat-message-body');
                $assistantBody.text(`${$assistantBody.text()}\n\n[${translateString('Error')}] ${translateString('Unable to get response')}`);
                $assistantRow.attr('data-status', 'error');
                return;
            }

            await parseSseAndStreamIntoMessage(response, $assistantRow);
        } catch (error) {
            const $assistantBody = $assistantRow.find('.ai-chat-message-body');

            if (abortController.signal.aborted) {
                $assistantBody.text(`${$assistantBody.text()}\n\n[${translateString('Stopped')}]`);
                $assistantRow.attr('data-status', 'aborted');
            } else {
                $assistantBody.text(`${$assistantBody.text()}\n\n[${translateString('Error')}] ${translateString('Unable to get response')}`);
                $assistantRow.attr('data-status', 'error');
            }
        } finally {
            activeAbortController = null;
            setStreamingState(false);
        }
    };

    $('.ai-chat-toggle').on('click', () => {
        const isHidden = $panel.prop('hidden');
        $('.ai-chat-toggle').attr('aria-expanded', isHidden.toString());
        $panel.prop('hidden', !isHidden);
    });

    $sendButton.on('click', sendMessage);

    $input.on('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    $stopButton.on('click', () => {
        if (activeAbortController) {
            activeAbortController.abort();
        }
    });

    $clearButton.on('click', () => {
        $messagesContainer.empty();
    });
});
