<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT stored_filename, title FROM published_papers WHERE id = ? AND status = 'approved'");
$stmt->execute([$id]);
$paper = $stmt->fetch();

if (!$paper) {
    http_response_code(404);
    exit('غير موجود');
}

$path = __DIR__ . '/uploads/published_papers/' . basename($paper['stored_filename']);
if (!is_file($path)) {
    http_response_code(404);
    exit('غير موجود');
}

$downloadName = preg_replace('/[^\p{L}\p{N}_\- ]+/u', '', $paper['title']);
$downloadName = trim($downloadName) !== '' ? trim($downloadName) : 'بحث';

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="paper.pdf"; filename*=UTF-8\'\'' . rawurlencode($downloadName) . '.pdf');
header('Cache-Control: public, max-age=3600');
readfile($path);
