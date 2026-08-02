document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('docChatForm');
    if (!form) return;

    var messagesEl = document.getElementById('docChatMessages');
    var input = document.getElementById('docChatInput');
    var sendBtn = document.getElementById('docChatSend');
    var docIdEl = document.getElementById('docId');
    var csrfEl = document.getElementById('docCsrfToken');

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
        body.set('document_id', docIdEl.value);
        body.set('csrf_token', csrfEl.value);

        fetch('api/document-chat-send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    appendMessage('assistant', data.reply);
                } else {
                    appendMessage('assistant', 'خطأ: ' + data.error);
                }
            })
            .catch(function () {
                appendMessage('assistant', 'تعذر الاتصال بالخادم. حاول مرة أخرى.');
            })
            .finally(function () {
                sendBtn.disabled = false;
                sendBtn.textContent = 'إرسال';
            });
    });
});
