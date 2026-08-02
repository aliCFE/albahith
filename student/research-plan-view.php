<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM research_plans WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$plan = $stmt->fetch();

if (!$plan) {
    flash_set('خطة البحث غير موجودة.', 'error');
    redirect('student/research-plan.php');
}

$page_title = $plan['topic'];
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <p><a href="<?= BASE_URL ?>student/research-plan.php">→ العودة لخطط البحث</a></p>
    <p class="hint">التخصص: <?= h($plan['academic_field']) ?> — المرحلة: <?= h(degree_level_label($plan['degree_level'])) ?></p>
    <div class="ai-output"><?= nl2br(h($plan['generated_content'])) ?></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
