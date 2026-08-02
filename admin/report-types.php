<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $typeKey = trim($_POST['type_key'] ?? '');
        $label = trim($_POST['label_ar'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sectionsRaw = trim($_POST['default_sections'] ?? '');
        $sections = array_values(array_filter(array_map('trim', explode("\n", $sectionsRaw))));

        if ($typeKey === '' || !preg_match('/^[a-z0-9_]+$/', $typeKey)) {
            $errors[] = 'مفتاح النوع مطلوب، ويجب أن يحتوي فقط على حروف إنكليزية صغيرة وأرقام وشرطة سفلية (مثال: lab_report).';
        }
        if ($label === '') {
            $errors[] = 'اسم النوع بالعربي مطلوب.';
        }
        if (empty($sections)) {
            $errors[] = 'أضف قسم واحد على الأقل بالهيكل الافتراضي.';
        }

        if (empty($errors)) {
            $sectionsJson = json_encode($sections, JSON_UNESCAPED_UNICODE);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE report_types SET type_key = ?, label_ar = ?, description = ?, default_sections = ? WHERE id = ?');
                $stmt->execute([$typeKey, $label, $description, $sectionsJson, $id]);
                flash_set('تم تحديث نوع المستند.');
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM report_types');
                $nextOrder = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO report_types (type_key, label_ar, description, default_sections, sort_order) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$typeKey, $label, $description, $sectionsJson, $nextOrder]);
                flash_set('تمت إضافة نوع المستند.');
            }
            redirect('admin/report-types.php');
        }
    } elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT is_active FROM report_types WHERE id = ?');
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        if ($current !== false) {
            $stmt = $pdo->prepare('UPDATE report_types SET is_active = ? WHERE id = ?');
            $stmt->execute([$current ? 0 : 1, $id]);
            flash_set($current ? 'تم تعطيل النوع.' : 'تم تفعيل النوع.');
        }
        redirect('admin/report-types.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM report_types WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('تم حذف النوع.');
        redirect('admin/report-types.php');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM report_types WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

$formValues = [
    'id'               => $editing['id'] ?? 0,
    'type_key'         => $editing['type_key'] ?? '',
    'label_ar'         => $editing['label_ar'] ?? '',
    'description'      => $editing['description'] ?? '',
    'default_sections' => $editing ? implode("\n", json_decode($editing['default_sections'] ?? '[]', true) ?: []) : '',
];

$types = $pdo->query('SELECT * FROM report_types ORDER BY sort_order ASC, id ASC')->fetchAll();

$page_title = 'أنواع المستندات (منشئ التقارير)';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2><?= $editing ? 'تعديل نوع مستند' : 'إضافة نوع مستند جديد' ?></h2>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$formValues['id'] ?>">
        <label>مفتاح النوع (إنكليزي، بدون مسافات — مثال: case_study)
            <input type="text" name="type_key" value="<?= h($formValues['type_key']) ?>" <?= $editing ? '' : 'required' ?> <?= $editing ? 'readonly' : '' ?>>
        </label>
        <label>اسم النوع بالعربي
            <input type="text" name="label_ar" value="<?= h($formValues['label_ar']) ?>" required>
        </label>
        <label>وصف مختصر (يظهر للطالب عند الاختيار)
            <input type="text" name="description" value="<?= h($formValues['description']) ?>">
        </label>
        <label>الهيكل الافتراضي للأقسام (قسم واحد بكل سطر)
            <textarea name="default_sections" rows="8" placeholder="المقدمة
مشكلة البحث
منهجية البحث
النتائج
الخاتمة"><?= h($formValues['default_sections']) ?></textarea>
        </label>
        <button type="submit" class="btn"><?= $editing ? 'حفظ التعديلات' : 'إضافة النوع' ?></button>
        <?php if ($editing): ?>
            <a href="<?= BASE_URL ?>admin/report-types.php" class="btn btn-outline">إلغاء</a>
        <?php endif; ?>
    </form>
</div>

<div class="panel">
    <h2>الأنواع الحالية (<?= count($types) ?>)</h2>
    <div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>النوع</th><th>عدد الأقسام</th><th>الحالة</th><th>إجراءات</th></tr></thead>
        <tbody>
            <?php foreach ($types as $t): ?>
                <?php $sectionCount = count(json_decode($t['default_sections'] ?? '[]', true) ?: []); ?>
                <tr>
                    <td><?= h($t['label_ar']) ?><br><span class="hint"><?= h($t['type_key']) ?></span></td>
                    <td><?= $sectionCount ?></td>
                    <td>
                        <?php if ($t['is_active']): ?>
                            <span class="badge badge-success">مفعّل</span>
                        <?php else: ?>
                            <span class="badge badge-error">معطّل</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-menu">
                            <button type="button" class="row-menu-toggle" aria-label="إجراءات">⋮</button>
                            <div class="row-menu-dropdown">
                                <a href="<?= BASE_URL ?>admin/report-types.php?edit=<?= (int)$t['id'] ?>">✎ تعديل</a>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit"><?= $t['is_active'] ? '⏸ تعطيل' : '▶ تفعيل' ?></button>
                                </form>
                                <form method="post" onsubmit="return confirm('حذف هذا النوع نهائيًا؟');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="danger">✕ حذف</button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($types)): ?>
                <tr><td colspan="4">لا توجد أنواع مضافة بعد.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
