<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/library_api.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
$result = islamhouse_search_books($query, 30);

$items = array_map(function ($item) {
    return [
        'title'       => $item['title'],
        'authors'     => $item['authors'],
        'description' => mb_substr($item['description'] ?? '', 0, 160, 'UTF-8'),
        'attachments' => array_map(function ($att) {
            return [
                'url'   => $att['url'] ?? '',
                'type'  => $att['extension_type'] ?? 'PDF',
                'size'  => $att['size'] ?? '',
            ];
        }, $item['attachments'] ?? []),
    ];
}, $result['items']);

echo json_encode([
    'ok'       => true,
    'items'    => $items,
    'indexed'  => $result['indexed'],
    'complete' => $result['complete'],
], JSON_UNESCAPED_UNICODE);
