<?php
/**
 * تكامل مع Pexels API (مجاني) لجلب صور فوتوغرافية حقيقية مرتبطة بمحتوى شرائح العروض التقديمية.
 * يتطلب مفتاح PEXELS_API_KEY بملف config.php — إذا لم يكن معرّفًا، تُبنى العروض بدون صور (تدهور آمن).
 */

function pexels_is_configured() {
    return defined('PEXELS_API_KEY') && PEXELS_API_KEY !== '';
}

function pexels_cache_path($key) {
    return __DIR__ . '/../cache/pexels/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.url';
}

/**
 * حاجز أمان صارم: يرفض أي نص (عبارة بحث أو وصف صورة alt) يشير لأشخاص أو بشر بأي صيغة،
 * لضمان عدم ظهور أي صورة فيها إنسان (رجل أو امرأة) إطلاقًا ضمن الصور المضمّنة بالعروض التقديمية
 */
function pexels_text_mentions_people($text) {
    $blocked = [
        // أشخاص بصيغة عامة
        'person', 'people', 'man', 'men', 'woman', 'women', 'girl', 'girls', 'boy', 'boys',
        'human', 'humans', 'face', 'faces', 'portrait', 'model', 'models', 'lady', 'ladies',
        'guy', 'guys', 'couple', 'family', 'crowd', 'audience', 'individual', 'individuals',
        'figure', 'figures', 'character', 'characters', 'female', 'females', 'male', 'males',
        'gentleman', 'gentlemen', 'adult', 'adults', 'teen', 'teens', 'teenager', 'teenagers',
        'elderly', 'senior', 'seniors', 'baby', 'babies', 'infant', 'infants', 'youth', 'someone',
        'anonymous', 'silhouette', 'silhouettes', 'crop of', 'cropped',
        // تعليم
        'student', 'students', 'teacher', 'teachers', 'professor', 'graduate', 'graduation',
        'kid', 'kids', 'child', 'children', 'classmate', 'classmates', 'lecturer', 'instructor',
        // عمل وأعمال
        'worker', 'workers', 'employee', 'employees', 'staff', 'colleague', 'colleagues',
        'businessman', 'businesswoman', 'businessperson', 'businesspeople', 'professional', 'professionals',
        'team', 'teams', 'teammate', 'coworker', 'coworkers', 'manager', 'ceo', 'executive',
        'entrepreneur', 'freelancer', 'candidate', 'interviewer', 'interviewee', 'speaker', 'presenter',
        // مناسبات وأنشطة تتضمن أشخاصًا عادة
        'meeting', 'interview', 'presentation', 'presenting', 'conference', 'seminar', 'workshop',
        'discussion', 'collaborating', 'collaboration', 'brainstorming', 'handshake', 'networking',
        'training session', 'consultation', 'consulting',
        // أجزاء جسم
        'hand', 'hands', 'finger', 'fingers', 'arm', 'arms', 'smile', 'smiling', 'wearing',
    ];
    $normalized = ' ' . strtolower($text) . ' ';
    foreach ($blocked as $word) {
        if (strpos($normalized, ' ' . $word . ' ') !== false
            || strpos($normalized, ' ' . $word) === 0
            || substr($normalized, -strlen($word) - 1) === ' ' . $word) {
            return true;
        }
    }
    return false;
}

/**
 * يبحث عن صورة مناسبة (اتجاه أفقي) لعبارة بحث إنجليزية، ويرجع رابط تحميل مباشر أو null.
 * حماية مزدوجة: (1) رفض عبارة البحث نفسها لو فيها إشارة لأشخاص، (2) فحص عدة نتائج فعلية
 * والتحقق من الوصف الحقيقي (alt) لكل صورة قبل قبولها — لأن عبارة بحث "آمنة الشكل" قد ترجع
 * صورًا تحتوي أشخاصًا فعليًا (مثل "team meeting" أو حتى "office" أحيانًا)
 */
function pexels_search_photo_url($query) {
    if (!pexels_is_configured() || trim($query) === '') {
        return null;
    }
    if (pexels_text_mentions_people($query)) {
        return null;
    }

    $cacheKey = 'pexels_' . md5(strtolower(trim($query)));
    $cachePath = pexels_cache_path($cacheKey);
    if (is_file($cachePath) && (time() - filemtime($cachePath)) < 30 * 24 * 3600) {
        $cached = trim(file_get_contents($cachePath));
        return $cached !== '' ? $cached : null;
    }

    $url = 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=8&orientation=landscape';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . PEXELS_API_KEY],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    $photos = $data['photos'] ?? [];

    $photoUrl = null;
    foreach ($photos as $photo) {
        $alt = $photo['alt'] ?? '';
        // رفض أي صورة وصفها الحقيقي (من Pexels نفسها) يشير لأشخاص، بصرف النظر عن عبارة البحث
        if ($alt !== '' && pexels_text_mentions_people($alt)) {
            continue;
        }
        $photoUrl = $photo['src']['large'] ?? null;
        if ($photoUrl) break;
    }

    @file_put_contents($cachePath, (string)$photoUrl);
    return $photoUrl;
}

/**
 * يحمّل صورة من رابط مباشر ويرجع محتواها الثنائي (binary) أو null عند الفشل
 */
function pexels_download_image($imageUrl) {
    if (!$imageUrl) {
        return null;
    }
    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['User-Agent: BahethPlatform/1.0'],
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data === false || $httpCode !== 200) {
        return null;
    }
    return $data;
}
