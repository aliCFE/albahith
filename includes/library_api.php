<?php
/**
 * تكامل مع واجهات برمجية خارجية مجانية لمكتبة "المكتبات" (كتب إسلامية + القرآن الكريم)
 * كل استجابة تُخزَّن مؤقتًا بملفات JSON محلية لتقليل الطلبات الخارجية وتسريع الصفحات
 */

define('ISLAMHOUSE_API_BASE', 'https://api3.islamhouse.com/v3/paV29H2gm56kvLPy/main');
define('QURANENC_API_BASE', 'https://quranenc.com/api/v1');

function library_cache_path($key) {
    return __DIR__ . '/../cache/library/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.json';
}

function library_cache_get($key, $ttlSeconds) {
    $path = library_cache_path($key);
    if (!is_file($path) || (time() - filemtime($path)) > $ttlSeconds) {
        return null;
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return $data !== null ? $data : null;
}

function library_cache_set($key, $data) {
    $path = library_cache_path($key);
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
}

function library_http_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['User-Agent: BahethPlatform/1.0'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return null;
    }
    return json_decode($response, true);
}

/**
 * قائمة الكتب الإسلامية من IslamHouse (مع صفحات)
 */
function islamhouse_fetch_books($page = 1, $perPage = 25) {
    $page = max(1, (int)$page);
    $cacheKey = 'islamhouse_books_' . $page . '_' . $perPage;
    $cached = library_cache_get($cacheKey, 6 * 3600);
    if ($cached !== null) {
        return $cached;
    }

    $url = ISLAMHOUSE_API_BASE . '/books/ar/ar/' . $page . '/' . $perPage . '/json';
    $data = library_http_get($url);
    if ($data === null || !isset($data['data'])) {
        return ['ok' => false, 'items' => [], 'pagination' => null];
    }

    $result = [
        'ok'         => true,
        'items'      => $data['data'],
        'pagination' => $data['links'] ?? null,
    ];
    library_cache_set($cacheKey, $result);
    return $result;
}

/**
 * قائمة تراجم القرآن المتاحة من QuranEnc للغة محددة (افتراضيًا الإنجليزية، لأن ar لا ترجع نتائج)
 */
function quranenc_fetch_translations($lang = 'en') {
    $cacheKey = 'quranenc_translations_' . $lang;
    $cached = library_cache_get($cacheKey, 24 * 3600);
    if ($cached !== null) {
        return $cached;
    }

    $url = QURANENC_API_BASE . '/translations/list/' . rawurlencode($lang);
    $data = library_http_get($url);
    if ($data === null || !isset($data['translations'])) {
        return ['ok' => false, 'items' => []];
    }

    $result = ['ok' => true, 'items' => $data['translations']];
    library_cache_set($cacheKey, $result);
    return $result;
}

/**
 * آيات سورة كاملة بترجمة محددة
 */
function quranenc_fetch_sura($translationKey, $suraNumber) {
    $translationKey = preg_replace('/[^a-zA-Z0-9_]/', '', $translationKey);
    $suraNumber = max(1, min(114, (int)$suraNumber));
    $cacheKey = 'quranenc_sura_' . $translationKey . '_' . $suraNumber;
    $cached = library_cache_get($cacheKey, 30 * 24 * 3600);
    if ($cached !== null) {
        return $cached;
    }

    $url = QURANENC_API_BASE . '/translation/sura/' . rawurlencode($translationKey) . '/' . $suraNumber;
    $data = library_http_get($url);
    if ($data === null || !isset($data['result'])) {
        return ['ok' => false, 'ayat' => []];
    }

    $result = ['ok' => true, 'ayat' => $data['result']];
    library_cache_set($cacheKey, $result);
    return $result;
}

/**
 * إزالة التشكيل وتوحيد أشكال الألف لتسهيل مطابقة نصوص البحث العربية
 */
function arabic_normalize($text) {
    $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{06D6}-\x{06ED}]/u', '', (string)$text);
    return str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
}

/**
 * فهرس بحث محلي عن الكتب (اسم الملف: islamhouse_index.json) يُبنى تدريجيًا عبر الزيارات الفعلية،
 * لأن واجهة IslamHouse لا تدعم البحث الخادمي — كل استدعاء يجلب صفحتين إضافيتين فقط حتى يكتمل الفهرس
 */
function islamhouse_index_path() {
    return __DIR__ . '/../cache/library/islamhouse_index.json';
}

function islamhouse_load_index() {
    $path = islamhouse_index_path();
    if (!is_file($path)) {
        return ['next_page' => 1, 'total_pages' => null, 'items' => []];
    }
    $data = json_decode(file_get_contents($path), true);
    return $data ?: ['next_page' => 1, 'total_pages' => null, 'items' => []];
}

function islamhouse_warm_index($maxPagesPerRun = 2) {
    $index = islamhouse_load_index();
    if ($index['total_pages'] !== null && $index['next_page'] > $index['total_pages']) {
        return $index;
    }

    $lockFile = __DIR__ . '/../cache/library/islamhouse_index.lock';
    if (is_file($lockFile) && (time() - filemtime($lockFile)) < 5) {
        return $index;
    }
    @file_put_contents($lockFile, '1');

    for ($i = 0; $i < $maxPagesPerRun; $i++) {
        if ($index['total_pages'] !== null && $index['next_page'] > $index['total_pages']) {
            break;
        }
        $url = ISLAMHOUSE_API_BASE . '/books/ar/ar/' . $index['next_page'] . '/50/json';
        $data = library_http_get($url);
        if ($data === null || !isset($data['data'])) {
            break;
        }
        foreach ($data['data'] as $book) {
            $authors = [];
            foreach (($book['prepared_by'] ?? []) as $p) {
                if (!empty($p['title'])) $authors[] = $p['title'];
            }
            $index['items'][] = [
                'id'           => $book['id'] ?? null,
                'title'        => $book['title'] ?? '',
                'title_norm'   => arabic_normalize($book['title'] ?? ''),
                'authors'      => implode('، ', $authors),
                'description'  => $book['description'] ?? '',
                'attachments'  => $book['attachments'] ?? [],
            ];
        }
        $index['total_pages'] = $data['links']['pages_number'] ?? $index['total_pages'];
        $index['next_page']++;
    }

    @file_put_contents(islamhouse_index_path(), json_encode($index, JSON_UNESCAPED_UNICODE));
    @unlink($lockFile);
    return $index;
}

function islamhouse_search_books($query, $limit = 30) {
    $index = islamhouse_warm_index(2);
    $indexedCount = count($index['items']);
    $complete = $index['total_pages'] !== null && $index['next_page'] > $index['total_pages'];
    $query = trim($query);

    if ($query === '') {
        return ['items' => [], 'indexed' => $indexedCount, 'complete' => $complete];
    }

    $needle = arabic_normalize(mb_strtolower($query, 'UTF-8'));
    $matches = [];
    foreach ($index['items'] as $item) {
        $haystack = mb_strtolower($item['title_norm'] . ' ' . $item['authors'], 'UTF-8');
        if (mb_strpos($haystack, $needle, 0, 'UTF-8') !== false) {
            $matches[] = $item;
            if (count($matches) >= $limit) break;
        }
    }
    return ['items' => $matches, 'indexed' => $indexedCount, 'complete' => $complete];
}

/**
 * فهرس بحث محلي عن نص آيات القرآن (يعتمد على كاش quranenc_fetch_sura لكل سورة)
 */
function quran_search_index_path() {
    return __DIR__ . '/../cache/library/quran_search_index.json';
}

function quran_load_search_index() {
    $path = quran_search_index_path();
    if (!is_file($path)) {
        return ['next_sura' => 1, 'items' => []];
    }
    $data = json_decode(file_get_contents($path), true);
    return $data ?: ['next_sura' => 1, 'items' => []];
}

function quran_warm_search_index($maxSurahsPerRun = 6) {
    $index = quran_load_search_index();
    if ($index['next_sura'] > 114) {
        return $index;
    }

    $lockFile = __DIR__ . '/../cache/library/quran_search_index.lock';
    if (is_file($lockFile) && (time() - filemtime($lockFile)) < 5) {
        return $index;
    }
    @file_put_contents($lockFile, '1');

    $namesByNum = [];
    foreach (quran_surah_list() as $s) {
        $namesByNum[$s[0]] = $s[1];
    }

    for ($i = 0; $i < $maxSurahsPerRun && $index['next_sura'] <= 114; $i++) {
        $suraNum = $index['next_sura'];
        $result = quranenc_fetch_sura('english_saheeh', $suraNum);
        if ($result['ok']) {
            foreach ($result['ayat'] as $ayah) {
                $index['items'][] = [
                    'sura'      => $suraNum,
                    'sura_name' => $namesByNum[$suraNum] ?? '',
                    'aya'       => $ayah['aya'],
                    'text'      => $ayah['arabic_text'],
                    'text_norm' => arabic_normalize($ayah['arabic_text'] ?? ''),
                ];
            }
        }
        $index['next_sura']++;
    }

    @file_put_contents(quran_search_index_path(), json_encode($index, JSON_UNESCAPED_UNICODE));
    @unlink($lockFile);
    return $index;
}

function quran_search_ayat($query, $limit = 30) {
    $query = trim($query);

    if (preg_match('/^(\d{1,3})\s*[:\-]\s*(\d{1,3})$/', $query, $m)) {
        return ['items' => [], 'jump' => ['sura' => (int)$m[1], 'aya' => (int)$m[2]]];
    }

    $index = quran_warm_search_index(6);
    $indexedSurahs = min(114, $index['next_sura'] - 1);
    $complete = $index['next_sura'] > 114;

    if ($query === '') {
        return ['items' => [], 'indexed_surahs' => $indexedSurahs, 'complete' => $complete];
    }

    $needle = arabic_normalize($query);
    $matches = [];
    foreach ($index['items'] as $item) {
        if (mb_strpos($item['text_norm'], $needle, 0, 'UTF-8') !== false) {
            $matches[] = $item;
            if (count($matches) >= $limit) break;
        }
    }
    return ['items' => $matches, 'indexed_surahs' => $indexedSurahs, 'complete' => $complete];
}

/**
 * قائمة ثابتة بسور القرآن الكريم الـ114 (بيانات ثابتة لا تحتاج طلب API)
 */
function quran_surah_list() {
    return [
        [1, 'الفاتحة', 'Al-Fatihah', 7, 'meccan'], [2, 'البقرة', 'Al-Baqarah', 286, 'medinan'],
        [3, 'آل عمران', 'Aal-E-Imran', 200, 'medinan'], [4, 'النساء', 'An-Nisa', 176, 'medinan'],
        [5, 'المائدة', "Al-Ma'idah", 120, 'medinan'], [6, 'الأنعام', "Al-An'am", 165, 'meccan'],
        [7, 'الأعراف', "Al-A'raf", 206, 'meccan'], [8, 'الأنفال', 'Al-Anfal', 75, 'medinan'],
        [9, 'التوبة', 'At-Tawbah', 129, 'medinan'], [10, 'يونس', 'Yunus', 109, 'meccan'],
        [11, 'هود', 'Hud', 123, 'meccan'], [12, 'يوسف', 'Yusuf', 111, 'meccan'],
        [13, 'الرعد', "Ar-Ra'd", 43, 'medinan'], [14, 'إبراهيم', 'Ibrahim', 52, 'meccan'],
        [15, 'الحجر', 'Al-Hijr', 99, 'meccan'], [16, 'النحل', 'An-Nahl', 128, 'meccan'],
        [17, 'الإسراء', 'Al-Isra', 111, 'meccan'], [18, 'الكهف', 'Al-Kahf', 110, 'meccan'],
        [19, 'مريم', 'Maryam', 98, 'meccan'], [20, 'طه', 'Ta-Ha', 135, 'meccan'],
        [21, 'الأنبياء', 'Al-Anbiya', 112, 'meccan'], [22, 'الحج', 'Al-Hajj', 78, 'medinan'],
        [23, 'المؤمنون', "Al-Mu'minun", 118, 'meccan'], [24, 'النور', 'An-Nur', 64, 'medinan'],
        [25, 'الفرقان', 'Al-Furqan', 77, 'meccan'], [26, 'الشعراء', "Ash-Shu'ara", 227, 'meccan'],
        [27, 'النمل', 'An-Naml', 93, 'meccan'], [28, 'القصص', 'Al-Qasas', 88, 'meccan'],
        [29, 'العنكبوت', 'Al-Ankabut', 69, 'meccan'], [30, 'الروم', 'Ar-Rum', 60, 'meccan'],
        [31, 'لقمان', 'Luqman', 34, 'meccan'], [32, 'السجدة', 'As-Sajdah', 30, 'meccan'],
        [33, 'الأحزاب', 'Al-Ahzab', 73, 'medinan'], [34, 'سبأ', 'Saba', 54, 'meccan'],
        [35, 'فاطر', 'Fatir', 45, 'meccan'], [36, 'يس', 'Ya-Sin', 83, 'meccan'],
        [37, 'الصافات', 'As-Saffat', 182, 'meccan'], [38, 'ص', 'Sad', 88, 'meccan'],
        [39, 'الزمر', 'Az-Zumar', 75, 'meccan'], [40, 'غافر', 'Ghafir', 85, 'meccan'],
        [41, 'فصلت', 'Fussilat', 54, 'meccan'], [42, 'الشورى', 'Ash-Shuraa', 53, 'meccan'],
        [43, 'الزخرف', 'Az-Zukhruf', 89, 'meccan'], [44, 'الدخان', 'Ad-Dukhan', 59, 'meccan'],
        [45, 'الجاثية', 'Al-Jathiyah', 37, 'meccan'], [46, 'الأحقاف', 'Al-Ahqaf', 35, 'meccan'],
        [47, 'محمد', 'Muhammad', 38, 'medinan'], [48, 'الفتح', 'Al-Fath', 29, 'medinan'],
        [49, 'الحجرات', 'Al-Hujurat', 18, 'medinan'], [50, 'ق', 'Qaf', 45, 'meccan'],
        [51, 'الذاريات', 'Adh-Dhariyat', 60, 'meccan'], [52, 'الطور', 'At-Tur', 49, 'meccan'],
        [53, 'النجم', 'An-Najm', 62, 'meccan'], [54, 'القمر', 'Al-Qamar', 55, 'meccan'],
        [55, 'الرحمن', 'Ar-Rahman', 78, 'medinan'], [56, 'الواقعة', "Al-Waqi'ah", 96, 'meccan'],
        [57, 'الحديد', 'Al-Hadid', 29, 'medinan'], [58, 'المجادلة', 'Al-Mujadilah', 22, 'medinan'],
        [59, 'الحشر', 'Al-Hashr', 24, 'medinan'], [60, 'الممتحنة', 'Al-Mumtahanah', 13, 'medinan'],
        [61, 'الصف', 'As-Saf', 14, 'medinan'], [62, 'الجمعة', "Al-Jumu'ah", 11, 'medinan'],
        [63, 'المنافقون', 'Al-Munafiqun', 11, 'medinan'], [64, 'التغابن', 'At-Taghabun', 18, 'medinan'],
        [65, 'الطلاق', 'At-Talaq', 12, 'medinan'], [66, 'التحريم', 'At-Tahrim', 12, 'medinan'],
        [67, 'الملك', 'Al-Mulk', 30, 'meccan'], [68, 'القلم', 'Al-Qalam', 52, 'meccan'],
        [69, 'الحاقة', 'Al-Haqqah', 52, 'meccan'], [70, 'المعارج', "Al-Ma'arij", 44, 'meccan'],
        [71, 'نوح', 'Nuh', 28, 'meccan'], [72, 'الجن', 'Al-Jinn', 28, 'meccan'],
        [73, 'المزمل', 'Al-Muzzammil', 20, 'meccan'], [74, 'المدثر', 'Al-Muddaththir', 56, 'meccan'],
        [75, 'القيامة', 'Al-Qiyamah', 40, 'meccan'], [76, 'الإنسان', 'Al-Insan', 31, 'medinan'],
        [77, 'المرسلات', 'Al-Mursalat', 50, 'meccan'], [78, 'النبأ', 'An-Naba', 40, 'meccan'],
        [79, 'النازعات', "An-Nazi'at", 46, 'meccan'], [80, 'عبس', 'Abasa', 42, 'meccan'],
        [81, 'التكوير', 'At-Takwir', 29, 'meccan'], [82, 'الانفطار', 'Al-Infitar', 19, 'meccan'],
        [83, 'المطففين', 'Al-Mutaffifin', 36, 'meccan'], [84, 'الانشقاق', 'Al-Inshiqaq', 25, 'meccan'],
        [85, 'البروج', 'Al-Buruj', 22, 'meccan'], [86, 'الطارق', 'At-Tariq', 17, 'meccan'],
        [87, 'الأعلى', "Al-A'la", 19, 'meccan'], [88, 'الغاشية', 'Al-Ghashiyah', 26, 'meccan'],
        [89, 'الفجر', 'Al-Fajr', 30, 'meccan'], [90, 'البلد', 'Al-Balad', 20, 'meccan'],
        [91, 'الشمس', 'Ash-Shams', 15, 'meccan'], [92, 'الليل', 'Al-Layl', 21, 'meccan'],
        [93, 'الضحى', 'Ad-Duhaa', 11, 'meccan'], [94, 'الشرح', 'Ash-Sharh', 8, 'meccan'],
        [95, 'التين', 'At-Tin', 8, 'meccan'], [96, 'العلق', 'Al-Alaq', 19, 'meccan'],
        [97, 'القدر', 'Al-Qadr', 5, 'meccan'], [98, 'البينة', 'Al-Bayyinah', 8, 'medinan'],
        [99, 'الزلزلة', 'Az-Zalzalah', 8, 'medinan'], [100, 'العاديات', 'Al-Adiyat', 11, 'meccan'],
        [101, 'القارعة', "Al-Qari'ah", 11, 'meccan'], [102, 'التكاثر', 'At-Takathur', 8, 'meccan'],
        [103, 'العصر', 'Al-Asr', 3, 'meccan'], [104, 'الهمزة', 'Al-Humazah', 9, 'meccan'],
        [105, 'الفيل', 'Al-Fil', 5, 'meccan'], [106, 'قريش', 'Quraysh', 4, 'meccan'],
        [107, 'الماعون', "Al-Ma'un", 7, 'meccan'], [108, 'الكوثر', 'Al-Kawthar', 3, 'meccan'],
        [109, 'الكافرون', 'Al-Kafirun', 6, 'meccan'], [110, 'النصر', 'An-Nasr', 3, 'medinan'],
        [111, 'المسد', 'Al-Masad', 5, 'meccan'], [112, 'الإخلاص', 'Al-Ikhlas', 4, 'meccan'],
        [113, 'الفلق', 'Al-Falaq', 5, 'meccan'], [114, 'الناس', 'An-Nas', 6, 'meccan'],
    ];
}
