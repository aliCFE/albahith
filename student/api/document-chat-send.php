<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ai.php';
require_once __DIR__ . '/../../includes/docx.php';

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
$message = trim($_POST['message'] ?? '');

if ($message === '') {
    echo json_encode(['ok' => false, 'error' => 'الرجاء كتابة سؤال.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND user_id = ? AND status = 'done'");
$stmt->execute([$documentId, $user['id']]);
$document = $stmt->fetch();

if (!$document) {
    echo json_encode(['ok' => false, 'error' => 'المستند غير موجود.']);
    exit;
}

if (usage_remaining($pdo, $user) <= 0) {
    echo json_encode(['ok' => false, 'error' => 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO document_messages (document_id, role, content) VALUES (?, 'user', ?)");
$stmt->execute([$documentId, $message]);

$stmt = $pdo->prepare('SELECT role, content FROM document_messages WHERE document_id = ? ORDER BY id ASC LIMIT 20');
$stmt->execute([$documentId]);
$history = $stmt->fetchAll();

$historyText = '';
foreach ($history as $h) {
    $historyText .= ($h['role'] === 'user' ? 'سؤال سابق: ' : 'إجابة سابقة: ') . $h['content'] . "\n\n";
}

$stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmtUser->execute([$user['id']]);
$fullUser = $stmtUser->fetch();
$systemPrompt = build_academic_system_prompt($fullUser);

$instruction = "هذا سؤال متابعة حول المستند المرفق. ملخص سابق للمستند: " . $document['ai_summary'] . "\n\n" .
    ($historyText !== '' ? "سياق أسئلة سابقة حول نفس المستند:\n" . $historyText . "\n" : '') .
    "أجب على السؤال الجديد التالي بالاعتماد على محتوى المستند: " . $message;

$documentPath = __DIR__ . '/../../uploads/documents/' . $document['stored_filename'];

if ($document['file_type'] === 'pdf') {
    if (!is_file($documentPath)) {
        echo json_encode(['ok' => false, 'error' => 'ملف المستند الأصلي لم يعد موجودًا على الخادم.']);
        exit;
    }
    $base64 = base64_encode(file_get_contents($documentPath));
    $result = ai_analyze_pdf($base64, $instruction, $systemPrompt);
} else {
    $text = extract_text_from_docx($documentPath);
    $result = ai_analyze_text($text ?? '', $instruction, $systemPrompt);
}

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO document_messages (document_id, role, content) VALUES (?, 'assistant', ?)");
$stmt->execute([$documentId, $result['text']]);

record_ai_usage($pdo, $user['id']);

echo json_encode(['ok' => true, 'reply' => $result['text']]);
