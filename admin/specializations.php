<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['specialization_name'] ?? '');

        if ($name === '') {
            $errors[] = 'اسم التخصص مطلوب.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO specializations (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
            $stmt->execute([$name]);
            flash_set('تم حفظ التخصص.');
            redirect('admin/specializations.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM specializations WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('تم حذف التخصص.');
        redirect('admin/specializations.php');
    }
}

$specializations = $pdo->query('SELECT * FROM specializations ORDER BY name ASC')->fetchAll();

$page_title = 'التخصصات';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>إضافة تخصص</h2>
    <p class="hint">أي تخصص تضيفه هنا يظهر تلقائيًا بقائمة الاختيار عند تسجيل الطالب/التدريسي أو تعديل بياناته.</p>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>اسم التخصص
            <input type="text" name="specialization_name" placeholder="مثال: هندسة حاسوب" required>
        </label>
        <button type="submit" class="btn">حفظ</button>
    </form>
</div>

<div class="panel">
    <h2>التخصصات المضافة (<?= count($specializations) ?>)</h2>
    <?php if (empty($specializations)): ?>
        <p class="hint">لا يوجد أي تخصص مضاف بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>التخصص</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php foreach ($specializations as $s): ?>
                    <tr>
                        <td><?= h($s['name']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('حذف هذا التخصص؟ راح يختفي من قائمة التسجيل أيضًا.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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
