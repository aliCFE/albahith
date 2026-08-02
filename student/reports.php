<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$stmt = $pdo->prepare('SELECT * FROM report_documents WHERE user_id = ? ORDER BY updated_at DESC');
$stmt->execute([$user['id']]);
$documents = $stmt->fetchAll();

$statusLabels = [
    'draft'         => 'مسودة',
    'outline_ready' => 'المخطط جاهز',
    'writing'       => 'قيد الكتابة',
    'completed'     => 'مكتمل',
];

$page_title = 'التقارير والأوراق البحثية';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <p><a href="<?= BASE_URL ?>student/reports-new.php" class="btn">+ إنشاء مستند جديد</a></p>
    <h2>مستنداتي (<?= count($documents) ?>)</h2>
    <?php if (empty($documents)): ?>
        <p class="hint">لا توجد مستندات بعد. ابدأ بإنشاء تقرير أو ورقة بحثية جديدة.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>العنوان</th><th>الحالة</th><th>عدد الكلمات</th><th>آخر تعديل</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?= h($doc['title']) ?></td>
                        <td><span class="badge <?= $doc['status'] === 'completed' ? 'badge-success' : 'badge-info' ?>"><?= h($statusLabels[$doc['status']] ?? $doc['status']) ?></span></td>
                        <td><?= (int)$doc['total_words'] ?></td>
                        <td><?= h($doc['updated_at']) ?></td>
                        <td>
                            <?php if ($doc['status'] === 'draft'): ?>
                                <a href="<?= BASE_URL ?>student/reports-outline.php?id=<?= (int)$doc['id'] ?>">إعداد المخطط</a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>student/reports-editor.php?id=<?= (int)$doc['id'] ?>">فتح المحرر</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
