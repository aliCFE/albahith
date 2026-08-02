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
$tool = $_POST['tool'] ?? '';
$selectedText = trim($_POST['text'] ?? '');

$tools = [
    'rewrite'      => 'أعد صياغة النص التالي بأسلوب مختلف مع الحفاظ على المعنى تمامًا',
    'academic'     => 'أعد كتابة النص التالي بأسلوب أكاديمي رصين وفصيح',
    'shorten'      => 'اختصر النص التالي مع الحفاظ على الأفكار الأساسية',
    'expand'       => 'وسّع النص التالي بإضافة تفصيل وشرح أكثر مع الحفاظ على نفس الفكرة',
    'simplify'     => 'بسّط النص التالي ليصير أوضح وأسهل بالفهم',
    'proofread'    => 'صحّح الأخطاء اللغوية والإملائية والنحوية بالنص التالي دون تغيير المعنى أو الأسلوب',
];

if (!array_key_exists($tool, $tools)) {
    echo json_encode(['ok' => false, 'error' => 'أداة غير معروفة.']);
    exit;
}
if ($selectedText === '') {
    echo json_encode(['ok' => false, 'error' => 'حدّد نصًا أولًا.']);
    exit;
}

$stmt = $pdo->prepare('SELECT rd.id FROM report_sections rs
                        JOIN report_documents rd ON rd.id = rs.document_id
                        WHERE rs.id = ? AND rs.document_id = ? AND rd.user_id = ?');
$stmt->execute([$sectionId, $documentId, $user['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'القسم غير موجود.']);
    exit;
}

if (usage_remaining($pdo, $user) <= 0) {
    echo json_encode(['ok' => false, 'error' => 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.']);
    exit;
}

$stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmtUser->execute([$user['id']]);
$fullUser = $stmtUser->fetch();
$systemPrompt = build_academic_system_prompt($fullUser);

$prompt = $tools[$tool] . " فقط، ولا تضف أي مقدمة أو تعليق أو شرح خارج النص الناتج:\n\n" . $selectedText;

$result = ai_chat([['role' => 'user', 'content' => $prompt]], $systemPrompt, 1500);
log_ai_request($pdo, $user['id'], $documentId, $sectionId, 'ai_tool_' . $tool, $result);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

record_ai_usage($pdo, $user['id']);

echo json_encode(['ok' => true, 'result' => trim($result['text'])]);
