<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/citation.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM report_documents WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$doc = $stmt->fetch();

if (!$doc) {
    flash_set('المستند غير موجود.', 'error');
    redirect('student/reports.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_manual') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $errors[] = 'عنوان المرجع مطلوب.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO report_references (document_id, title, authors, pub_year, container_title, doi, url, verification_status)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $id, $title,
                trim($_POST['authors'] ?? '') ?: null,
                trim($_POST['pub_year'] ?? '') ?: null,
                trim($_POST['container_title'] ?? '') ?: null,
                trim($_POST['doi'] ?? '') ?: null,
                trim($_POST['url'] ?? '') ?: null,
                'needs_check',
            ]);
            flash_set('تمت إضافة المرجع.');
            redirect('student/reports-references.php?id=' . $id);
        }
    } elseif ($action === 'import_source') {
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM sources WHERE id = ? AND user_id = ?');
        $stmt->execute([$sourceId, $user['id']]);
        $source = $stmt->fetch();
        if ($source) {
            $stmt = $pdo->prepare('INSERT INTO report_references (document_id, title, authors, pub_year, container_title, doi, url, verification_status)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$id, $source['title'], $source['authors'], $source['pub_year'], $source['container_title'], $source['doi'], $source['url'], 'verified']);
            flash_set('تم استيراد المرجع من مراجعك المحفوظة.');
        }
        redirect('student/reports-references.php?id=' . $id);
    } elseif ($action === 'delete') {
        $refId = (int)($_POST['ref_id'] ?? 0);
        $pdo->prepare('DELETE FROM report_references WHERE id = ? AND document_id = ?')->execute([$refId, $id]);
        flash_set('تم حذف المرجع.');
        redirect('student/reports-references.php?id=' . $id);
    }
}

$stmt = $pdo->prepare('SELECT * FROM report_references WHERE document_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$references = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM sources WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$mySources = $stmt->fetchAll();

$page_title = 'مراجع: ' . $doc['title'];
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <p><a href="<?= BASE_URL ?>student/reports-editor.php?id=<?= (int)$id ?>">→ العودة للمحرر</a></p>
    <h2>مراجع "<?= h($doc['title']) ?>" (<?= count($references) ?>)</h2>
    <?php if (empty($references)): ?>
        <p class="hint">لا توجد مراجع مضافة بعد.</p>
    <?php else: ?>
        <?php foreach ($references as $ref): ?>
            <div class="source-card">
                <?php if ($ref['verification_status'] === 'needs_check'): ?>
                    <span class="badge badge-info">يحتاج تحقق قبل الاعتماد</span>
                <?php else: ?>
                    <span class="badge badge-success">موثّق</span>
                <?php endif; ?>
                <p class="citation-text"><?= h(format_citation_apa($ref)) ?></p>
                <form method="post" class="inline-form" onsubmit="return confirm('حذف هذا المرجع؟');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="ref_id" value="<?= (int)$ref['id'] ?>">
                    <button type="submit" class="btn-link">حذف</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!empty($mySources)): ?>
<div class="panel">
    <h2>استيراد من مراجعي المحفوظة</h2>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_source">
        <label>اختر مرجعًا
            <select name="source_id" required>
                <option value="">— اختر —</option>
                <?php foreach ($mySources as $src): ?>
                    <option value="<?= (int)$src['id'] ?>"><?= h($src['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn btn-outline">استيراد</button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <h2>إضافة مرجع يدويًا</h2>
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
        <label>المجلة / الناشر
            <input type="text" name="container_title">
        </label>
        <label>DOI (اختياري)
            <input type="text" name="doi">
        </label>
        <label>الرابط (اختياري)
            <input type="text" name="url" placeholder="https://">
        </label>
        <button type="submit" class="btn">إضافة</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
