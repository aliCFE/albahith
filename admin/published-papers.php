<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $paperId = (int)($_POST['paper_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['admin_note'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM published_papers WHERE id = ?');
    $stmt->execute([$paperId]);
    $paper = $stmt->fetch();

    if (!$paper) {
        flash_set('البحث غير موجود.', 'error');
    } elseif ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE published_papers SET status = 'approved', admin_note = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$note !== '' ? $note : null, $paperId]);
        flash_set('تمت الموافقة على البحث ونشره بمكتبة الأبحاث.');
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE published_papers SET status = 'rejected', admin_note = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$note !== '' ? $note : null, $paperId]);
        flash_set('تم رفض البحث.');
    }
    redirect('admin/published-papers.php');
}

$papers = $pdo->query(
    "SELECT pp.*, u.name AS user_name, u.email AS user_email
     FROM published_papers pp JOIN users u ON u.id = pp.user_id
     ORDER BY (pp.status = 'pending') DESC, pp.created_at DESC"
)->fetchAll();

$page_title = 'مراجعة الأبحاث المنشورة';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <?php if (empty($papers)): ?>
        <p class="hint">لا توجد أبحاث مرسلة بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>العنوان</th><th>الباحث</th><th>الملف</th><th>التاريخ</th><th>الحالة</th><th>ملاحظة</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php foreach ($papers as $p): ?>
                    <tr>
                        <td><?= h($p['title']) ?><?php if (!empty($p['description'])): ?><br><span class="hint"><?= h($p['description']) ?></span><?php endif; ?></td>
                        <td><?= h($p['user_name']) ?><br><span class="hint"><?= h($p['user_email']) ?></span></td>
                        <td><a href="<?= BASE_URL ?>admin/published-paper-file.php?id=<?= (int)$p['id'] ?>" target="_blank">عرض PDF</a></td>
                        <td><?= h($p['created_at']) ?></td>
                        <td>
                            <?php if ($p['status'] === 'approved'): ?>
                                <span class="badge badge-success">منشور</span>
                            <?php elseif ($p['status'] === 'rejected'): ?>
                                <span class="badge badge-error">مرفوض</span>
                            <?php else: ?>
                                <span class="badge badge-info">قيد المراجعة</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($p['admin_note'] ?? '-') ?></td>
                        <td>
                            <?php if ($p['status'] === 'pending'): ?>
                                <div class="row-actions">
                                    <form method="post" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="paper_id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-xs">✓ موافقة ونشر</button>
                                    </form>
                                    <form method="post" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="paper_id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-xs btn-danger-outline">✕ رفض</button>
                                    </form>
                                </div>
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
