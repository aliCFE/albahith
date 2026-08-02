<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['admin_note'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM payment_requests WHERE id = ?');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        flash_set('الطلب غير موجود.', 'error');
    } elseif ($action === 'approve') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE payment_requests SET status = 'approved', admin_note = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$note !== '' ? $note : null, $requestId]);
        $stmt = $pdo->prepare("UPDATE users SET plan = 'pro' WHERE id = ?");
        $stmt->execute([$request['user_id']]);
        $pdo->commit();
        flash_set('تمت الموافقة على الطلب وترقية حساب المستخدم.');
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE payment_requests SET status = 'rejected', admin_note = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$note !== '' ? $note : null, $requestId]);
        flash_set('تم رفض الطلب.');
    }
    redirect('admin/payments.php');
}

$requests = $pdo->query(
    "SELECT pr.*, u.name AS user_name, u.email AS user_email
     FROM payment_requests pr JOIN users u ON u.id = pr.user_id
     ORDER BY (pr.status = 'pending') DESC, pr.created_at DESC"
)->fetchAll();

$page_title = 'طلبات الدفع';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <?php if (empty($requests)): ?>
        <p class="hint">لا يوجد طلبات دفع بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>المستخدم</th><th>الصورة</th><th>التاريخ</th><th>الحالة</th><th>ملاحظة</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= h($r['user_name']) ?><br><span class="hint"><?= h($r['user_email']) ?></span></td>
                        <td><a href="<?= BASE_URL ?>admin/payment-proof-image.php?id=<?= (int)$r['id'] ?>" target="_blank">عرض الصورة</a></td>
                        <td><?= h($r['created_at']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'approved'): ?>
                                <span class="badge badge-success">تمت الموافقة</span>
                            <?php elseif ($r['status'] === 'rejected'): ?>
                                <span class="badge badge-error">مرفوض</span>
                            <?php else: ?>
                                <span class="badge badge-info">قيد المراجعة</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($r['admin_note'] ?? '-') ?></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <div class="row-actions">
                                    <form method="post" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-xs">✓ موافقة</button>
                                    </form>
                                    <form method="post" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
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
