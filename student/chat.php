<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$stmt = $pdo->prepare('SELECT * FROM conversations WHERE user_id = ? ORDER BY updated_at DESC');
$stmt->execute([$user['id']]);
$conversations = $stmt->fetchAll();

$activeId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$messages = [];

if ($activeId) {
    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$activeId, $user['id']]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare('SELECT * FROM messages WHERE conversation_id = ? ORDER BY id ASC');
        $stmt->execute([$activeId]);
        $messages = $stmt->fetchAll();
    } else {
        $activeId = null;
    }
}

$remaining = usage_remaining($pdo, $user);

$page_title = 'المساعد الأكاديمي';
include __DIR__ . '/../includes/header.php';
?>
<div class="chat-layout">
    <aside class="chat-sidebar">
        <a href="<?= BASE_URL ?>student/chat.php" class="btn btn-block">+ محادثة جديدة</a>
        <ul class="conversation-list">
            <?php foreach ($conversations as $c): ?>
                <li>
                    <a href="?id=<?= (int)$c['id'] ?>" class="<?= $activeId === (int)$c['id'] ? 'active' : '' ?>">
                        <?= h($c['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($conversations)): ?>
                <li class="empty">لا توجد محادثات بعد.</li>
            <?php endif; ?>
        </ul>
    </aside>
    <section class="chat-main">
        <div class="chat-messages" id="chatMessages">
            <?php if (empty($messages)): ?>
                <div class="chat-empty">اسأل مساعدك الأكاديمي عن أي موضوع بتخصصك — صياغة أفكار، شرح مفاهيم، مراجعة نص أكاديمي، وأكثر.</div>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <div class="chat-msg chat-msg-<?= h($m['role']) ?>"><?= nl2br(h($m['content'])) ?></div>
            <?php endforeach; ?>
        </div>
        <?php if ($remaining <= 0): ?>
            <div class="alert alert-error">وصلت للحد الشهري من طلبات الذكاء الاصطناعي.</div>
        <?php else: ?>
        <form id="chatForm" class="chat-form">
            <input type="hidden" id="conversationId" value="<?= $activeId ? (int)$activeId : '' ?>">
            <input type="hidden" id="csrfToken" value="<?= h(csrf_token()) ?>">
            <textarea id="chatInput" placeholder="اكتب سؤالك الأكاديمي هنا..." required></textarea>
            <button type="submit" class="btn" id="chatSend">إرسال</button>
        </form>
        <?php endif; ?>
    </section>
</div>
<script src="<?= asset_url('assets/js/chat.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
