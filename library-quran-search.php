<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/library_api.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
$result = quran_search_ayat($query, 30);

if (isset($result['jump'])) {
    echo json_encode(['ok' => true, 'jump' => $result['jump']], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok'             => true,
    'items'          => $result['items'],
    'indexed_surahs' => $result['indexed_surahs'],
    'complete'       => $result['complete'],
], JSON_UNESCAPED_UNICODE);
