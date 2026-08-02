document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('chatForm');
    if (!form) return;

    var messagesEl = document.getElementById('chatMessages');
    var input = document.getElementById('chatInput');
    var sendBtn = document.getElementById('chatSend');
    var conversationIdEl = document.getElementById('conversationId');
    var csrfEl = document.getElementById('csrfToken');

    function appendMessage(role, text) {
        var empty = messagesEl.querySelector('.chat-empty');
        if (empty) empty.remove();
        var div = document.createElement('div');
        div.className = 'chat-msg chat-msg-' + role;
        div.textContent = text;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var message = input.value.trim();
        if (!message || sendBtn.disabled) return;

        appendMessage('user', message);
        input.value = '';
        sendBtn.disabled = true;
        sendBtn.textContent = 'جارٍ الإرسال...';

        var body = new URLSearchParams();
        body.set('message', message);
        body.set('conversation_id', conversationIdEl.value || '');
        body.set('csrf_token', csrfEl.value);

        fetch('api/chat-send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    appendMessage('assistant', data.reply);
                    if (data.conversation_id && !conversationIdEl.value) {
                        conversationIdEl.value = data.conversation_id;
                        var url = new URL(window.location.href);
                        url.searchParams.set('id', data.conversation_id);
                        window.history.replaceState({}, '', url);
                    }
                } else {
                    appendMessage('assistant', 'خطأ: ' + data.error);
                }
            })
            .catch(function () {
                appendMessage('assistant', 'تعذر الاتصال بالخادم. تحقق من اتصالك وحاول مرة أخرى.');
            })
            .finally(function () {
                sendBtn.disabled = false;
                sendBtn.textContent = 'إرسال';
            });
    });
});
