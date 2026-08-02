<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM presentations WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$presentation = $stmt->fetch();

if (!$presentation) {
    flash_set('العرض التقديمي غير موجود.', 'error');
    redirect('student/presentations.php');
}

$slides = $presentation['outline_json'] ? (json_decode($presentation['outline_json'], true) ?: []) : [];

$page_title = $presentation['topic'];
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <p><a href="<?= BASE_URL ?>student/presentations.php">→ العودة للعروض التقديمية</a></p>
    <?php if ($presentation['status'] !== 'done'): ?>
        <div class="alert alert-error">تعذر توليد هذا العرض التقديمي: <?= h($presentation['error_message']) ?></div>
    <?php else: ?>
    <p><a href="<?= BASE_URL ?>student/presentation-download.php?id=<?= (int)$presentation['id'] ?>" class="btn">⬇ تنزيل العرض (.pptx)</a></p>

    <?php foreach ($slides as $i => $slide): ?>
        <div class="source-card">
            <span class="badge badge-info">شريحة <?= (int)$i + 1 ?></span>
            <h3><?= h($slide['title'] ?? '') ?></h3>
            <?php if (!empty($slide['bullets'])): ?>
                <ul>
                    <?php foreach ($slide['bullets'] as $bullet): ?>
                        <li><?= h($bullet) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
