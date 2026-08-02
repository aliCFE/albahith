<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT proof_filename FROM payment_requests WHERE id = ?');
$stmt->execute([$id]);
$filename = $stmt->fetchColumn();

if (!$filename) {
    http_response_code(404);
    exit('غير موجود');
}

$path = __DIR__ . '/../uploads/payment_proofs/' . basename($filename);
if (!is_file($path)) {
    http_response_code(404);
    exit('غير موجود');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0, no-cache');
readfile($path);
