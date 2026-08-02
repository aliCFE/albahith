<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();
$remaining = usage_remaining($pdo, $user);
$limit = current_plan_limit($pdo, $user['id']);

$stmt = $pdo->prepare('SELECT plan FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$plan = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM conversations WHERE user_id = ?');
$stmt->execute([$user['id']]);
$conversationsCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE user_id = ?');
$stmt->execute([$user['id']]);
$documentsCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM research_plans WHERE user_id = ?');
$stmt->execute([$user['id']]);
$plansCount = (int)$stmt->fetchColumn();

$page_title = 'مرحبًا، ' . $user['name'];
include __DIR__ . '/../includes/header.php';
?>
<div class="usage-banner">
    خطتك الحالية: <strong><?= h(plan_label($plan)) ?></strong> — استخدامك هذا الشهر: <strong><?= $limit - $remaining ?></strong> من أصل <strong><?= $limit ?></strong> طلب ذكاء اصطناعي.
    <?php if ($remaining === 0): ?>
        <span class="usage-warning">وصلت للحد الشهري — <a href="<?= BASE_URL ?>student/subscription.php">ترقية الخطة</a>.</span>
    <?php endif; ?>
</div>

<div class="dashboard-grid">
    <a href="<?= BASE_URL ?>student/chat.php" class="dash-card">
        <span class="feature-icon">💬</span>
        <h3>المساعد الأكاديمي</h3>
        <p><?= $conversationsCount ?> محادثة محفوظة</p>
    </a>
    <a href="<?= BASE_URL ?>student/documents.php" class="dash-card">
        <span class="feature-icon">📄</span>
        <h3>تحليل المستندات</h3>
        <p><?= $documentsCount ?> مستند مرفوع</p>
    </a>
    <a href="<?= BASE_URL ?>student/research-plan.php" class="dash-card">
        <span class="feature-icon">🧭</span>
        <h3>خطط البحث</h3>
        <p><?= $plansCount ?> خطة منشأة</p>
    </a>
    <a href="<?= BASE_URL ?>student/subscription.php" class="dash-card">
        <span class="feature-icon">💳</span>
        <h3>الاشتراك</h3>
        <p>خطتك: <?= h(plan_label($plan)) ?></p>
    </a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
