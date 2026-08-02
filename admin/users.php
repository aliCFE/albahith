<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId === $admin['id']) {
        flash_set('لا يمكنك تعديل حالة حسابك الخاص من هنا.', 'error');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('student','instructor')");
        $stmt->execute([$userId]);
        $target = $stmt->fetch();

        if (!$target) {
            flash_set('المستخدم غير موجود.', 'error');
        } elseif ($action === 'toggle_active') {
            $newStatus = $target['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
            $stmt->execute([$newStatus, $userId]);
            flash_set($newStatus ? 'تم تفعيل الحساب.' : 'تم إيقاف الحساب.');
        } elseif ($action === 'reset_usage') {
            $stmt = $pdo->prepare('UPDATE users SET ai_requests_used = 0, usage_reset_at = ? WHERE id = ?');
            $stmt->execute([date('Y-m-d'), $userId]);
            flash_set('تم تصفير عداد الاستخدام.');
        } elseif ($action === 'toggle_plan') {
            $newPlan = $target['plan'] === 'pro' ? 'free' : 'pro';
            $stmt = $pdo->prepare('UPDATE users SET plan = ? WHERE id = ?');
            $stmt->execute([$newPlan, $userId]);
            flash_set($newPlan === 'pro' ? 'تم ترقية الحساب للخطة المدفوعة.' : 'تم إرجاع الحساب للخطة المجانية.');
        }
    }
    redirect('admin/users.php');
}

$users = $pdo->query("SELECT * FROM users WHERE role IN ('student','instructor') ORDER BY created_at DESC")->fetchAll();

$page_title = 'إدارة المستخدمين';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>الاسم</th><th>البريد</th><th>النوع</th><th>الجامعة</th><th>الخطة</th><th>الاستخدام</th><th>الحالة</th><th>إجراءات</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $s): ?>
                <tr>
                    <td><?= h($s['name']) ?></td>
                    <td><?= h($s['email']) ?></td>
                    <td><?= h(role_label($s['role'])) ?></td>
                    <td><?= h($s['university'] ?? '-') ?></td>
                    <td>
                        <?php if ($s['plan'] === 'pro'): ?>
                            <span class="badge badge-success">مدفوعة</span>
                        <?php else: ?>
                            <span class="badge">مجانية</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$s['ai_requests_used'] ?></td>
                    <td>
                        <?php if ($s['is_active']): ?>
                            <span class="badge badge-success">نشط</span>
                        <?php else: ?>
                            <span class="badge badge-error">موقوف</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-menu">
                            <button type="button" class="row-menu-toggle" aria-label="إجراءات">⋮</button>
                            <div class="row-menu-dropdown">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <button type="submit"><?= $s['is_active'] ? '⏸ إيقاف الحساب' : '▶ تفعيل الحساب' ?></button>
                                </form>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="action" value="toggle_plan">
                                    <button type="submit"><?= $s['plan'] === 'pro' ? '↩ إرجاع لخطة مجانية' : '⭐ ترقية لخطة مدفوعة' ?></button>
                                </form>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="action" value="reset_usage">
                                    <button type="submit">↺ تصفير الاستخدام</button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="8">لا يوجد مستخدمون مسجلون بعد.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
