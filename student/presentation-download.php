<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM presentations WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$presentation = $stmt->fetch();

if (!$presentation || empty($presentation['stored_filename'])) {
    http_response_code(404);
    exit('غير موجود');
}

$path = __DIR__ . '/../uploads/presentations/' . basename($presentation['stored_filename']);
if (!is_file($path)) {
    http_response_code(404);
    exit('الملف غير موجود على الخادم');
}

$downloadName = preg_replace('/[^\p{L}\p{N}_\- ]/u', '', $presentation['topic']);
if ($downloadName === '') $downloadName = 'presentation';

header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
header('Content-Disposition: attachment; filename="' . $downloadName . '.pptx"');
header('Content-Length: ' . filesize($path));
readfile($path);
