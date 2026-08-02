<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$document = $stmt->fetch();

if (!$document) {
    flash_set('المستند غير موجود.', 'error');
    redirect('student/documents.php');
}

$messages = [];
if ($document['status'] === 'done') {
    $stmt = $pdo->prepare('SELECT * FROM document_messages WHERE document_id = ? ORDER BY id ASC');
    $stmt->execute([$id]);
    $messages = $stmt->fetchAll();
}

$remaining = usage_remaining($pdo, $user);

$page_title = $document['original_filename'];
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <p><a href="<?= BASE_URL ?>student/documents.php">→ العودة لقائمة المستندات</a></p>
    <?php if ($document['status'] === 'done'): ?>
        <div class="ai-output"><?= nl2br(h($document['ai_summary'])) ?></div>
    <?php else: ?>
        <div class="alert alert-error">تعذر تحليل هذا المستند: <?= h($document['error_message']) ?></div>
    <?php endif; ?>
</div>

<?php if ($document['status'] === 'done'): ?>
<div class="panel">
    <h2>أسئلة متابعة حول هذا المستند</h2>
    <div class="chat-main chat-main-embedded">
        <div class="chat-messages" id="docChatMessages">
            <?php if (empty($messages)): ?>
                <div class="chat-empty">اسأل أي سؤال تفصيلي عن محتوى هذا المستند تحديدًا.</div>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <div class="chat-msg chat-msg-<?= h($m['role']) ?>"><?= nl2br(h($m['content'])) ?></div>
            <?php endforeach; ?>
        </div>
        <?php if ($remaining <= 0): ?>
            <div class="alert alert-error">وصلت للحد الشهري من طلبات الذكاء الاصطناعي.</div>
        <?php else: ?>
        <form id="docChatForm" class="chat-form">
            <input type="hidden" id="docId" value="<?= (int)$document['id'] ?>">
            <input type="hidden" id="docCsrfToken" value="<?= h(csrf_token()) ?>">
            <textarea id="docChatInput" placeholder="اكتب سؤالك عن المستند..." required></textarea>
            <button type="submit" class="btn" id="docChatSend">إرسال</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script src="<?= asset_url('assets/js/doc-chat.js') ?>"></script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
