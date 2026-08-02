<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$types = $pdo->query('SELECT * FROM report_types WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();

$page_title = 'اختر نوع المستند';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <p><a href="<?= BASE_URL ?>student/reports.php">→ العودة لمستنداتي</a></p>
    <h2>شنو تحب تسوي؟</h2>
    <p class="hint">اختر نوع المستند اللي تريد تنشئه، وراح نوريك بعدها حقول تفصيلية حسب النوع.</p>
    <div class="type-cards">
        <?php foreach ($types as $t): ?>
            <a class="type-card" href="<?= BASE_URL ?>student/reports-new-details.php?type=<?= h($t['type_key']) ?>">
                <h3><?= h($t['label_ar']) ?></h3>
                <?php if (!empty($t['description'])): ?>
                    <p><?= h($t['description']) ?></p>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (empty($types)): ?>
            <p class="hint">لا توجد أنواع مستندات مفعّلة حاليًا. تواصل مع إدارة المنصة.</p>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
