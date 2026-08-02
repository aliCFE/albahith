<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['university_name'] ?? '');
        $limit = (int)($_POST['monthly_limit'] ?? 0);

        if ($name === '') {
            $errors[] = 'اسم الجامعة مطلوب.';
        }
        if ($limit < 0) {
            $errors[] = 'الحد الشهري لا يمكن أن يكون رقمًا سالبًا.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO university_plans (university_name, monthly_limit) VALUES (?, ?)
                                    ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit)');
            $stmt->execute([$name, $limit]);
            flash_set('تم حفظ الجامعة.');
            redirect('admin/universities.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM university_plans WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('تم حذف الجامعة.');
        redirect('admin/universities.php');
    }
}

$plans = $pdo->query('SELECT * FROM university_plans ORDER BY university_name ASC')->fetchAll();

$page_title = 'الجامعات';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>إضافة / تعديل جامعة</h2>
    <p class="hint">أي جامعة تضيفها هنا تظهر تلقائيًا بقائمة الجامعات عند تسجيل الطالب/التدريسي أو تعديل بياناته. حقل "الحد الشهري" اختياري — اتركه 0 لو تريد إضافتها فقط لقائمة الاختيار بدون رفع حد استخدام خاص لطلابها.</p>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>اسم الجامعة
            <input type="text" name="university_name" placeholder="مثال: جامعة بغداد" required>
        </label>
        <label>حد شهري خاص لطلاب وتدريسيي هذه الجامعة (اختياري)
            <input type="number" name="monthly_limit" min="0" value="0" required>
        </label>
        <button type="submit" class="btn">حفظ</button>
    </form>
</div>

<div class="panel">
    <h2>الجامعات المضافة (<?= count($plans) ?>)</h2>
    <?php if (empty($plans)): ?>
        <p class="hint">لا توجد أي جامعة مضافة بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>الجامعة</th><th>الحد الشهري الخاص</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php foreach ($plans as $p): ?>
                    <tr>
                        <td><?= h($p['university_name']) ?></td>
                        <td><?= (int)$p['monthly_limit'] > 0 ? (int)$p['monthly_limit'] : '—' ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('حذف هذه الجامعة؟ راح تختفي من قائمة التسجيل أيضًا.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger-outline">✕ حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
