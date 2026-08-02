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

        if ($name === '') {
            $errors[] = 'اسم الجامعة مطلوب.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO university_plans (university_name, monthly_limit) VALUES (?, 0)
                                    ON DUPLICATE KEY UPDATE university_name = VALUES(university_name)');
            $stmt->execute([$name]);
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

$universities = $pdo->query('SELECT * FROM university_plans ORDER BY university_name ASC')->fetchAll();

$page_title = 'الجامعات';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>إضافة جامعة</h2>
    <p class="hint">أي جامعة تضيفها هنا تظهر تلقائيًا بقائمة الاختيار عند تسجيل الطالب/التدريسي أو تعديل بياناته.</p>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>اسم الجامعة
            <input type="text" name="university_name" placeholder="مثال: جامعة بغداد" required>
        </label>
        <button type="submit" class="btn">حفظ</button>
    </form>
</div>

<div class="panel">
    <h2>الجامعات المضافة (<?= count($universities) ?>)</h2>
    <?php if (empty($universities)): ?>
        <p class="hint">لا توجد أي جامعة مضافة بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>الجامعة</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php foreach ($universities as $u): ?>
                    <tr>
                        <td><?= h($u['university_name']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('حذف هذه الجامعة؟ راح تختفي من قائمة التسجيل أيضًا.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
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
