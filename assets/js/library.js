(function () {
    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, delay);
        };
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    /* ===== بحث موحّد عن السور والآيات (خانة واحدة) ===== */
    var quranSearch = document.getElementById('quranSearchInput');
    if (quranSearch) {
        var surahDataEl = document.getElementById('surahData');
        var surahs = surahDataEl ? JSON.parse(surahDataEl.textContent) : [];
        var gridWrap = document.getElementById('surahGridWrap');
        var resultsWrap = document.getElementById('quranSearchResults');
        var statusEl = document.getElementById('quranSearchStatus');
        var baseUrl = (document.querySelector('link[rel=icon]') || {}).href || '';

        function surahUrl(num) {
            return 'library-quran-view.php?sura=' + num;
        }

        function renderSurahMatches(matches) {
            if (!matches.length) return '';
            var html = '<div class="panel"><h3>السور المطابقة</h3><div class="surah-grid">';
            matches.forEach(function (s) {
                html += '<a href="' + surahUrl(s.num) + '" class="surah-card">' +
                    '<span class="surah-num">' + s.num + '</span>' +
                    '<span class="surah-info">' +
                    '<span class="surah-name-ar">' + escapeHtml(s.name_ar) + '</span>' +
                    '<span class="surah-name-en">' + escapeHtml(s.name_en) + '</span>' +
                    '<span class="surah-meta"><span class="surah-pill">' + s.ayah_count + ' آية</span>' +
                    '<span class="surah-pill place">' + (s.revelation === 'meccan' ? '🕋 مكية' : '🕌 مدنية') + '</span></span>' +
                    '</span></a>';
            });
            html += '</div></div>';
            return html;
        }

        function renderAyahMatches(data) {
            if (!data.items || !data.items.length) {
                if (data.indexed_surahs !== undefined && !data.complete) {
                    return '<div class="panel"><p class="hint">ما لكينا آيات مطابقة (الفهرسة جارية: ' + data.indexed_surahs + ' من 114 سورة حتى الآن).</p></div>';
                }
                return '';
            }
            var html = '<div class="panel"><h3>الآيات المطابقة</h3><div class="source-list">';
            data.items.forEach(function (item) {
                html += '<div class="source-card"><p class="quran-ayah" style="font-size:1.15rem">' + item.text +
                    '<span class="quran-ayah-num">' + item.aya + '</span></p>' +
                    '<a href="' + surahUrl(item.sura) + '#ayah-' + item.aya + '">سورة ' + escapeHtml(item.sura_name) + ' — آية ' + item.aya + '</a></div>';
            });
            html += '</div>';
            if (!data.complete) {
                html += '<p class="hint">الفهرسة جارية (' + data.indexed_surahs + ' من 114 سورة) — قد تظهر نتائج أكثر لاحقًا.</p>';
            }
            html += '</div>';
            return html;
        }

        var ayahResultsHtml = '';

        function repaint() {
            resultsWrap.innerHTML = renderSurahMatchesCache + ayahResultsHtml;
        }

        var renderSurahMatchesCache = '';

        var runAyahSearch = debounce(function (q) {
            if (statusEl) statusEl.textContent = 'جارٍ البحث عن آيات مطابقة...';
            fetch('library-quran-search.php?q=' + encodeURIComponent(q))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.ok) return;
                    if (data.jump) {
                        window.location.href = surahUrl(data.jump.sura) + '#ayah-' + data.jump.aya;
                        return;
                    }
                    ayahResultsHtml = renderAyahMatches(data);
                    repaint();
                    if (statusEl) statusEl.textContent = '';
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = 'تعذر البحث حاليًا.';
                });
        }, 350);

        quranSearch.addEventListener('input', function () {
            var q = quranSearch.value.trim();
            if (q === '') {
                gridWrap.style.display = '';
                resultsWrap.innerHTML = '';
                if (statusEl) statusEl.textContent = '';
                return;
            }
            gridWrap.style.display = 'none';

            var qLower = q.toLowerCase();
            var surahMatches = surahs.filter(function (s) {
                return (s.name_ar + ' ' + s.name_en + ' ' + s.num).toLowerCase().indexOf(qLower) !== -1;
            });
            renderSurahMatchesCache = renderSurahMatches(surahMatches);
            ayahResultsHtml = '';
            repaint();

            runAyahSearch(q);
        });
    }

    /* ===== بحث فوري عن الكتب (AJAX) ===== */
    var bookSearch = document.getElementById('bookSearchInput');
    if (bookSearch) {
        var bookGrid = document.getElementById('booksGrid');
        var bookPagination = document.getElementById('booksPagination');
        var bookStatus = document.getElementById('bookSearchStatus');
        var defaultGridHtml = bookGrid.innerHTML;
        var defaultPaginationHtml = bookPagination ? bookPagination.innerHTML : '';

        function renderBooks(items) {
            if (!items.length) {
                bookGrid.innerHTML = '<p class="hint">ما لكينا نتائج مطابقة.</p>';
                return;
            }
            var html = '<div class="books-grid">';
            items.forEach(function (book) {
                html += '<div class="book-card"><div class="book-card-title"><span class="icon">📗</span><h3>' + escapeHtml(book.title) + '</h3></div>';
                if (book.authors) html += '<div class="book-card-author">' + escapeHtml(book.authors) + '</div>';
                if (book.description) html += '<p class="book-card-desc">' + escapeHtml(book.description) + '</p>';
                if (book.attachments && book.attachments.length) {
                    html += '<div class="book-card-downloads">';
                    book.attachments.forEach(function (att) {
                        if (!att.url) return;
                        html += '<a href="' + escapeHtml(att.url) + '" class="btn btn-outline btn-xs" target="_blank" rel="noopener">⬇ ' + escapeHtml(att.type || 'PDF') + (att.size ? ' (' + escapeHtml(att.size) + ')' : '') + '</a>';
                    });
                    html += '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
            bookGrid.innerHTML = html;
        }

        var runBookSearch = debounce(function (q) {
            if (q === '') {
                bookGrid.innerHTML = defaultGridHtml;
                if (bookPagination) bookPagination.innerHTML = defaultPaginationHtml;
                if (bookStatus) bookStatus.textContent = '';
                return;
            }
            if (bookStatus) bookStatus.textContent = 'جارٍ البحث...';
            if (bookPagination) bookPagination.innerHTML = '';
            fetch('library-books-search.php?q=' + encodeURIComponent(q))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.ok) return;
                    renderBooks(data.items);
                    if (bookStatus) {
                        bookStatus.textContent = data.complete
                            ? ('تم فهرسة المكتبة كاملة (' + data.indexed + ' كتاب) — ' + data.items.length + ' نتيجة')
                            : ('جارٍ فهرسة المكتبة تدريجيًا (' + data.indexed + ' كتاب مفهرس حتى الآن) — ' + data.items.length + ' نتيجة، جرّب لاحقًا لتغطية أوسع');
                    }
                })
                .catch(function () {
                    if (bookStatus) bookStatus.textContent = 'تعذر البحث حاليًا.';
                });
        }, 350);

        bookSearch.addEventListener('input', function () {
            runBookSearch(bookSearch.value.trim());
        });
    }

    /* ===== الانتقال لآية محددة عبر الرابط (#ayah-N) وتمييزها ===== */
    function jumpToAyahFromHash() {
        var hash = window.location.hash;
        if (!hash || hash.indexOf('#ayah-') !== 0) return;
        var target = document.getElementById(hash.slice(1));
        if (!target) return;
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('quran-ayah-highlight');
        setTimeout(function () { target.classList.remove('quran-ayah-highlight'); }, 3000);
    }
    if (document.readyState === 'complete') {
        setTimeout(jumpToAyahFromHash, 150);
    } else {
        window.addEventListener('load', function () { setTimeout(jumpToAyahFromHash, 150); });
    }
    window.addEventListener('hashchange', jumpToAyahFromHash);
})();
