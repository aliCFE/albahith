<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/citation.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_manual') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $errors[] = 'عنوان المصدر مطلوب.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO sources (user_id, title, authors, pub_year, container_title, doi, url, source_type)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $user['id'],
                $title,
                trim($_POST['authors'] ?? ''),
                trim($_POST['pub_year'] ?? ''),
                trim($_POST['container_title'] ?? ''),
                trim($_POST['doi'] ?? ''),
                trim($_POST['url'] ?? ''),
                in_array($_POST['source_type'] ?? '', ['journal', 'book', 'conference', 'website', 'other'], true) ? $_POST['source_type'] : 'other',
            ]);
            flash_set('تمت إضافة المرجع.');
            redirect('student/references.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM sources WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        flash_set('تم حذف المرجع.');
        redirect('student/references.php');
    }
}

$style = in_array($_GET['style'] ?? '', ['apa', 'mla', 'chicago'], true) ? $_GET['style'] : 'apa';

$stmt = $pdo->prepare('SELECT * FROM sources WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$sources = $stmt->fetchAll();

$page_title = 'مراجعي';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <p>
        <a href="<?= BASE_URL ?>student/sources-search.php" class="btn">+ بحث عن مصدر جديد</a>
    </p>
    <h2>مراجعي المحفوظة (<?= count($sources) ?>)</h2>
    <p class="hint">نمط التوثيق:
        <a href="?style=apa" class="<?= $style === 'apa' ? 'active' : '' ?>">APA</a> |
        <a href="?style=mla" class="<?= $style === 'mla' ? 'active' : '' ?>">MLA</a> |
        <a href="?style=chicago" class="<?= $style === 'chicago' ? 'active' : '' ?>">Chicago</a>
    </p>

    <?php if (empty($sources)): ?>
        <p class="hint">لا توجد مراجع محفوظة بعد.</p>
    <?php else: ?>
        <?php foreach ($sources as $s): ?>
            <div class="source-card">
                <span class="badge badge-info"><?= h(source_type_label($s['source_type'])) ?></span>
                <p class="citation-text"><?= h(format_citation($s, $style)) ?></p>
                <form method="post" class="inline-form" onsubmit="return confirm('حذف هذا المرجع؟');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn-link">حذف</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>إضافة مرجع يدويًا</h2>
    <p class="hint">لمصدر ما لقيته بالبحث التلقائي (كتاب، موقع إلكتروني، إلخ).</p>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_manual">
        <label>العنوان
            <input type="text" name="title" required>
        </label>
        <label>المؤلف/المؤلفون
            <input type="text" name="authors">
        </label>
        <label>سنة النشر
            <input type="text" name="pub_year" placeholder="2024">
        </label>
        <label>المجلة / الناشر / الموقع
            <input type="text" name="container_title">
        </label>
        <label>الرابط (اختياري)
            <input type="text" name="url" placeholder="https://">
        </label>
        <label>النوع
            <select name="source_type">
                <option value="journal">مقال علمي</option>
                <option value="book">كتاب</option>
                <option value="conference">ورقة مؤتمر</option>
                <option value="website">موقع إلكتروني</option>
                <option value="other">أخرى</option>
            </select>
        </label>
        <button type="submit" class="btn">إضافة</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
