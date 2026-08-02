(function () {
    var documentId = document.getElementById('documentId');
    var sectionIdInput = document.getElementById('activeSectionId');
    var csrfInput = document.getElementById('reportCsrfToken');
    if (!documentId || !sectionIdInput || !csrfInput) return;

    function csrf() { return csrfInput.value; }

    /* ===== شريط أدوات التنسيق ===== */
    var editor = document.getElementById('sectionEditor');
    if (editor) {
        document.querySelectorAll('.editor-toolbar [data-cmd]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                editor.focus();
                document.execCommand(btn.getAttribute('data-cmd'), false, null);
            });
        });

        /* ===== الحفظ التلقائي ===== */
        var saveTimer = null;
        var statusEl = document.getElementById('autosaveStatus');
        var wordCountEl = document.getElementById('liveWordCount');

        function countWords(text) {
            text = text.trim();
            if (!text) return 0;
            return text.split(/\s+/).length;
        }

        function saveNow() {
            if (statusEl) statusEl.textContent = 'جارٍ الحفظ...';
            var body = new URLSearchParams();
            body.set('csrf_token', csrf());
            body.set('document_id', documentId.value);
            body.set('section_id', sectionIdInput.value);
            body.set('content', editor.innerHTML);

            fetch('api/reports-save-section.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (statusEl) statusEl.textContent = data.ok ? 'تم الحفظ ✓' : 'تعذر الحفظ';
                    if (data.ok && wordCountEl) wordCountEl.textContent = data.word_count;
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = 'تعذر الاتصال بالخادم';
                });
        }

        editor.addEventListener('input', function () {
            if (wordCountEl) wordCountEl.textContent = countWords(editor.innerText);
            if (statusEl) statusEl.textContent = 'تعديلات غير محفوظة...';
            clearTimeout(saveTimer);
            saveTimer = setTimeout(saveNow, 1500);
        });
        editor.addEventListener('blur', function () {
            clearTimeout(saveTimer);
            saveNow();
        });

        /* ===== أدوات الذكاء الاصطناعي على النص المحدد ===== */
        var aiSelect = document.getElementById('aiToolSelect');
        if (aiSelect) {
            aiSelect.addEventListener('change', function () {
                var tool = aiSelect.value;
                if (!tool) return;

                var selection = window.getSelection();
                if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
                    alert('حدّد النص اللي تريد تطبّق عليه الأداة أولًا.');
                    aiSelect.value = '';
                    return;
                }
                var range = selection.getRangeAt(0);
                if (!editor.contains(range.commonAncestorContainer)) {
                    alert('حدّد نصًا داخل المحرر أولًا.');
                    aiSelect.value = '';
                    return;
                }
                var selectedText = selection.toString().trim();
                if (!selectedText) {
                    aiSelect.value = '';
                    return;
                }

                aiSelect.disabled = true;
                if (statusEl) statusEl.textContent = 'جارٍ تطبيق الأداة...';

                var body = new URLSearchParams();
                body.set('csrf_token', csrf());
                body.set('document_id', documentId.value);
                body.set('section_id', sectionIdInput.value);
                body.set('tool', tool);
                body.set('text', selectedText);

                fetch('api/reports-ai-tool.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            range.deleteContents();
                            range.insertNode(document.createTextNode(data.result));
                            selection.removeAllRanges();
                            if (statusEl) statusEl.textContent = 'تم التطبيق، جارٍ الحفظ...';
                            clearTimeout(saveTimer);
                            saveTimer = setTimeout(saveNow, 400);
                        } else {
                            alert('خطأ: ' + data.error);
                            if (statusEl) statusEl.textContent = '';
                        }
                    })
                    .catch(function () {
                        alert('تعذر الاتصال بالخادم.');
                    })
                    .finally(function () {
                        aiSelect.disabled = false;
                        aiSelect.value = '';
                    });
            });
        }
    }

    /* ===== توليد الأقسام ===== */
    function generateSection(sectionId, onDone) {
        var body = new URLSearchParams();
        body.set('csrf_token', csrf());
        body.set('document_id', documentId.value);
        body.set('section_id', sectionId);

        fetch('api/reports-generate-section.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) { onDone(data); })
            .catch(function () { onDone({ ok: false, error: 'تعذر الاتصال بالخادم' }); });
    }

    function runGenerationQueue(ids, progressEl, onAllDone) {
        var i = 0;
        function next() {
            if (i >= ids.length) { onAllDone(); return; }
            var id = ids[i];
            var line = document.createElement('div');
            line.textContent = 'جارٍ توليد القسم ' + (i + 1) + ' من ' + ids.length + '...';
            if (progressEl) progressEl.appendChild(line);

            generateSection(id, function (data) {
                line.textContent = data.ok
                    ? '✓ تم توليد القسم ' + (i + 1) + ' من ' + ids.length
                    : '✕ فشل القسم ' + (i + 1) + ': ' + (data.error || '');
                i++;
                next();
            });
        }
        next();
    }

    var generateAllBtn = document.getElementById('generateAllBtn');
    if (generateAllBtn) {
        generateAllBtn.addEventListener('click', function () {
            var idsInput = document.getElementById('pendingSectionIds');
            var ids = (idsInput.value || '').split(',').filter(Boolean);
            if (!ids.length) return;
            generateAllBtn.disabled = true;
            var progressEl = document.getElementById('generateProgress');
            runGenerationQueue(ids, progressEl, function () {
                window.location.reload();
            });
        });
    }

    var generateSingleBtn = document.getElementById('generateSingleBtn');
    if (generateSingleBtn) {
        generateSingleBtn.addEventListener('click', function () {
            generateSingleBtn.disabled = true;
            generateSingleBtn.textContent = 'جارٍ التوليد...';
            generateSection(generateSingleBtn.getAttribute('data-section-id'), function (data) {
                if (data.ok) {
                    window.location.reload();
                } else {
                    alert('تعذر التوليد: ' + data.error);
                    generateSingleBtn.disabled = false;
                    generateSingleBtn.textContent = 'توليد هذا القسم';
                }
            });
        });
    }
})();
