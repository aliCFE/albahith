<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ai.php';
require_once __DIR__ . '/../../includes/ai_cost.php';
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

$stmt = $pdo->prepare('SELECT * FROM report_documents WHERE id = ? AND user_id = ?');
$stmt->execute([$documentId, $user['id']]);
$doc = $stmt->fetch();
if (!$doc) {
    echo json_encode(['ok' => false, 'error' => 'المستند غير موجود.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM report_sections WHERE id = ? AND document_id = ?');
$stmt->execute([$sectionId, $documentId]);
$section = $stmt->fetch();
if (!$section) {
    echo json_encode(['ok' => false, 'error' => 'القسم غير موجود.']);
    exit;
}

if (usage_remaining($pdo, $user) <= 0) {
    echo json_encode(['ok' => false, 'error' => 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.']);
    exit;
}

$pdo->prepare("UPDATE report_sections SET status = 'generating' WHERE id = ?")->execute([$sectionId]);

$stmt = $pdo->prepare("SELECT section_title FROM report_sections WHERE document_id = ? AND id != ? AND status != 'disabled'");
$stmt->execute([$documentId, $sectionId]);
$otherTitles = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare('SELECT extracted_text FROM report_files WHERE document_id = ? AND processing_status = ?');
$stmt->execute([$documentId, 'done']);
$sourceTexts = $stmt->fetchAll(PDO::FETCH_COLUMN);
$sourceText = implode("\n\n---\n\n", array_filter($sourceTexts));

$stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmtUser->execute([$user['id']]);
$fullUser = $stmtUser->fetch();
$systemPrompt = build_academic_system_prompt($fullUser);
$prompt = report_section_prompt($doc, $section, $otherTitles, $sourceText);

$maxTokens = min(4000, max(800, (int)($section['target_word_count'] ?? 300) * 3));
$result = ai_chat([['role' => 'user', 'content' => $prompt]], $systemPrompt, $maxTokens);
log_ai_request($pdo, $user['id'], $documentId, $sectionId, 'generate_section', $result);

if (!$result['ok']) {
    $pdo->prepare("UPDATE report_sections SET status = 'failed' WHERE id = ?")->execute([$sectionId]);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$html = report_text_to_html($result['text']);
$wordCount = report_word_count($html);

$stmt = $pdo->prepare("UPDATE report_sections SET content = ?, status = 'generated' WHERE id = ?");
$stmt->execute([$html, $sectionId]);

recalculate_report_word_count($pdo, $documentId);

record_ai_usage($pdo, $user['id']);

echo json_encode(['ok' => true, 'content' => $html, 'word_count' => $wordCount]);
