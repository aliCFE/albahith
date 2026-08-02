<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ai.php';

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
    echo json_encode(['ok' => false, 'error' => 'طلب غير صالح (CSRF)، الرجاء تحديث الصفحة والمحاولة مجددًا.']);
    exit;
}

$pdo = get_db();
$user = current_user();

$message = trim($_POST['message'] ?? '');
$conversationId = isset($_POST['conversation_id']) && $_POST['conversation_id'] !== '' ? (int)$_POST['conversation_id'] : null;

if ($message === '') {
    echo json_encode(['ok' => false, 'error' => 'الرجاء كتابة سؤال.']);
    exit;
}

if (usage_remaining($pdo, $user) <= 0) {
    echo json_encode(['ok' => false, 'error' => 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.']);
    exit;
}

if ($conversationId) {
    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $user['id']]);
    if (!$stmt->fetch()) {
        $conversationId = null;
    }
}

if (!$conversationId) {
    $title = mb_substr($message, 0, 60);
    $stmt = $pdo->prepare('INSERT INTO conversations (user_id, title) VALUES (?, ?)');
    $stmt->execute([$user['id'], $title]);
    $conversationId = (int)$pdo->lastInsertId();
}

$stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'user', ?)");
$stmt->execute([$conversationId, $message]);

$stmt = $pdo->prepare('SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 20');
$stmt->execute([$conversationId]);
$history = array_reverse($stmt->fetchAll());
$apiMessages = array_map(function ($m) {
    return ['role' => $m['role'], 'content' => $m['content']];
}, $history);

$stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmtUser->execute([$user['id']]);
$fullUser = $stmtUser->fetch();

$systemPrompt = build_academic_system_prompt($fullUser);
$result = ai_chat($apiMessages, $systemPrompt);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error'], 'conversation_id' => $conversationId]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'assistant', ?)");
$stmt->execute([$conversationId, $result['text']]);

$stmt = $pdo->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = ?');
$stmt->execute([$conversationId]);

record_ai_usage($pdo, $user['id']);

echo json_encode([
    'ok' => true,
    'conversation_id' => $conversationId,
    'reply' => $result['text'],
]);
