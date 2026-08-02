<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT stored_filename FROM published_papers WHERE id = ?');
$stmt->execute([$id]);
$filename = $stmt->fetchColumn();

if (!$filename) {
    http_response_code(404);
    exit('غير موجود');
}

$path = __DIR__ . '/../uploads/published_papers/' . basename($filename);
if (!is_file($path)) {
    http_response_code(404);
    exit('غير موجود');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="paper.pdf"');
header('Cache-Control: private, max-age=0, no-cache');
readfile($path);
