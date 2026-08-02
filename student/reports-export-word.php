<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/docx-builder.php';
require_once __DIR__ . '/../includes/citation.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM report_documents WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$doc = $stmt->fetch();

if (!$doc) {
    flash_set('المستند غير موجود.', 'error');
    redirect('student/reports.php');
}

$stmt = $pdo->prepare("SELECT * FROM report_sections WHERE document_id = ? AND status != 'disabled' ORDER BY sort_order ASC");
$stmt->execute([$id]);
$sections = $stmt->fetchAll();

if (empty($sections)) {
    flash_set('لا توجد أقسام مكتملة بعد لتصديرها.', 'error');
    redirect('student/reports-editor.php?id=' . $id);
}

$stmt = $pdo->prepare('SELECT * FROM report_references WHERE document_id = ? ORDER BY created_at ASC');
$stmt->execute([$id]);
$references = $stmt->fetchAll();

$tmpPath = tempnam(sys_get_temp_dir(), 'baheth_docx_');
if ($tmpPath === false || !build_docx_file($doc, $sections, $references, $tmpPath)) {
    if ($tmpPath !== false && is_file($tmpPath)) {
        unlink($tmpPath);
    }
    flash_set('تعذر توليد ملف Word. حاول مرة أخرى.', 'error');
    redirect('student/reports-editor.php?id=' . $id);
}

$downloadName = preg_replace('/[^\p{L}\p{N}_\- ]+/u', '', $doc['title']);
$downloadName = trim($downloadName) !== '' ? trim($downloadName) : 'تقرير';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="report.docx"; filename*=UTF-8\'\'' . rawurlencode($downloadName) . '.docx');
header('Content-Length: ' . filesize($tmpPath));
header('Cache-Control: no-cache, must-revalidate');

readfile($tmpPath);
unlink($tmpPath);
exit;
