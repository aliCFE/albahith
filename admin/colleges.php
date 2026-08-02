<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['college_name'] ?? '');

        if ($name === '') {
            $errors[] = 'اسم الكلية مطلوب.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO colleges (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
            $stmt->execute([$name]);
            flash_set('تم حفظ الكلية.');
            redirect('admin/colleges.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM colleges WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('تم حذف الكلية.');
        redirect('admin/colleges.php');
    }
}

$colleges = $pdo->query('SELECT * FROM colleges ORDER BY name ASC')->fetchAll();

$page_title = 'الكليات';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>إضافة كلية</h2>
    <p class="hint">أي كلية تضيفها هنا تظهر تلقائيًا بقائمة الاختيار عند تسجيل الطالب/التدريسي أو تعديل بياناته.</p>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>اسم الكلية
            <input type="text" name="college_name" placeholder="مثال: كلية الهندسة" required>
        </label>
        <button type="submit" class="btn">حفظ</button>
    </form>
</div>

<div class="panel">
    <h2>الكليات المضافة (<?= count($colleges) ?>)</h2>
    <?php if (empty($colleges)): ?>
        <p class="hint">لا توجد أي كلية مضافة بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>الكلية</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php foreach ($colleges as $c): ?>
                    <tr>
                        <td><?= h($c['name']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('حذف هذه الكلية؟ راح تختفي من قائمة التسجيل أيضًا.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
