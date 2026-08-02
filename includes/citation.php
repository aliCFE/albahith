<?php
/**
 * تنسيق الاستشهادات المرجعية بأنماط أكاديمية شائعة، من بيانات حقيقية محفوظة
 * (وليس نصًا مولّدًا بالذكاء الاصطناعي) لتفادي أي معلومات غير دقيقة
 */

/**
 * يبحث عن مصادر علمية حقيقية عبر CrossRef API (مجاني، بدون مفتاح API)
 * يرجع مصفوفة من ['title','authors','pub_year','container_title','doi','url']
 */
function crossref_search($query, $limit = 8) {
    $url = 'https://api.crossref.org/works?query=' . urlencode($query) . '&rows=' . (int)$limit;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['User-Agent: BahethPlatform/1.0 (mailto:support@baheth.local)'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return ['ok' => false, 'error' => 'تعذر الوصول لخدمة البحث عن المصادر العلمية (CrossRef) حاليًا.'];
    }

    $data = json_decode($response, true);
    $items = $data['message']['items'] ?? [];
    $results = [];

    foreach ($items as $item) {
        $title = $item['title'][0] ?? null;
        if (!$title) continue;

        $authors = [];
        foreach (($item['author'] ?? []) as $a) {
            $name = trim(($a['given'] ?? '') . ' ' . ($a['family'] ?? ''));
            if ($name !== '') $authors[] = $name;
        }

        $year = $item['published-print']['date-parts'][0][0]
            ?? $item['published-online']['date-parts'][0][0]
            ?? $item['issued']['date-parts'][0][0]
            ?? null;

        $results[] = [
            'title'           => $title,
            'authors'         => implode('، ', $authors),
            'pub_year'        => $year ? (string)$year : '',
            'container_title' => $item['container-title'][0] ?? '',
            'doi'             => $item['DOI'] ?? '',
            'url'             => $item['URL'] ?? ($item['DOI'] ? 'https://doi.org/' . $item['DOI'] : ''),
            'source_type'     => ($item['type'] ?? '') === 'book' ? 'book' : 'journal',
        ];
    }

    return ['ok' => true, 'results' => $results];
}

function citation_authors_apa($authorsString) {
    if ($authorsString === '' || $authorsString === null) return 'مؤلف غير معروف';
    return $authorsString;
}

/**
 * تنسيق APA مبسّط: المؤلفون (السنة). العنوان. المجلة/الناشر. الرابط
 */
function format_citation_apa(array $source) {
    $parts = [];
    $parts[] = citation_authors_apa($source['authors']);
    $parts[] = '(' . ($source['pub_year'] ?: 'د.ت') . ').';
    $parts[] = rtrim($source['title'], '.') . '.';
    if (!empty($source['container_title'])) {
        $parts[] = $source['container_title'] . '.';
    }
    if (!empty($source['doi'])) {
        $parts[] = 'https://doi.org/' . $source['doi'];
    } elseif (!empty($source['url'])) {
        $parts[] = $source['url'];
    }
    return implode(' ', $parts);
}

/**
 * تنسيق MLA مبسّط: المؤلفون. "العنوان." المجلة، السنة، الرابط.
 */
function format_citation_mla(array $source) {
    $parts = [];
    $parts[] = citation_authors_apa($source['authors']) . '.';
    $parts[] = '"' . rtrim($source['title'], '.') . '."';
    if (!empty($source['container_title'])) {
        $parts[] = $source['container_title'] . ',';
    }
    if (!empty($source['pub_year'])) {
        $parts[] = $source['pub_year'] . ',';
    }
    if (!empty($source['doi'])) {
        $parts[] = 'doi:' . $source['doi'] . '.';
    } elseif (!empty($source['url'])) {
        $parts[] = $source['url'] . '.';
    }
    return implode(' ', $parts);
}

/**
 * تنسيق Chicago مبسّط: المؤلفون. "العنوان." المجلة (السنة). الرابط.
 */
function format_citation_chicago(array $source) {
    $parts = [];
    $parts[] = citation_authors_apa($source['authors']) . '.';
    $parts[] = '"' . rtrim($source['title'], '.') . '."';
    if (!empty($source['container_title'])) {
        $parts[] = $source['container_title'];
    }
    if (!empty($source['pub_year'])) {
        $parts[] = '(' . $source['pub_year'] . ').';
    }
    if (!empty($source['doi'])) {
        $parts[] = 'https://doi.org/' . $source['doi'] . '.';
    } elseif (!empty($source['url'])) {
        $parts[] = $source['url'] . '.';
    }
    return implode(' ', $parts);
}

function format_citation($source, $style = 'apa') {
    switch ($style) {
        case 'mla':
            return format_citation_mla($source);
        case 'chicago':
            return format_citation_chicago($source);
        default:
            return format_citation_apa($source);
    }
}

function source_type_label($type) {
    $labels = [
        'journal'    => 'مقال علمي',
        'book'       => 'كتاب',
        'conference' => 'ورقة مؤتمر',
        'website'    => 'موقع إلكتروني',
        'other'      => 'أخرى',
    ];
    return $labels[$type] ?? $type;
}
