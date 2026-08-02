<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/reports.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
if (!in_array(current_user()['role'], ['student', 'instructor'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'غير مصرح.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'طلب غير صالح (CSRF)، حدّث الصفحة وحاول مجددًا.']);
    exit;
}

$pdo = get_db();
$user = current_user();

$documentId = (int)($_POST['document_id'] ?? 0);
$sectionId = (int)($_POST['section_id'] ?? 0);
$content = $_POST['content'] ?? '';

// نتأكد إن المستند والقسم يعودان لنفس المستخدم قبل أي تعديل
$stmt = $pdo->prepare('SELECT rd.id FROM report_sections rs
                        JOIN report_documents rd ON rd.id = rs.document_id
                        WHERE rs.id = ? AND rs.document_id = ? AND rd.user_id = ?');
$stmt->execute([$sectionId, $documentId, $user['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'القسم غير موجود.']);
    exit;
}

// تنظيف بسيط للـ HTML الوارد من المحرر: نسمح فقط بالوسوم الأساسية للتنسيق
$allowedTags = '<p><br><b><strong><i><em><ul><ol><li>';
$cleanContent = strip_tags($content, $allowedTags);

$stmt = $pdo->prepare("UPDATE report_sections SET content = ?, status = 'edited' WHERE id = ?");
$stmt->execute([$cleanContent, $sectionId]);

recalculate_report_word_count($pdo, $documentId);
$pdo->prepare('UPDATE report_documents SET updated_at = NOW() WHERE id = ?')->execute([$documentId]);

echo json_encode(['ok' => true, 'word_count' => report_word_count($cleanContent)]);
