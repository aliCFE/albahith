<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

const PUBLISH_PDF_MAX_BYTES = 40 * 1024 * 1024; // 40 ميجابايت
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $documentId = (int)($_POST['document_id'] ?? 0);

    if ($title === '') {
        $errors[] = 'عنوان البحث مطلوب.';
    } elseif (empty($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'الرجاء اختيار ملف PDF للبحث.';
    } else {
        $file = $_FILES['pdf_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            $errors[] = 'يُسمح فقط برفع ملفات PDF.';
        } elseif ($file['size'] > PUBLISH_PDF_MAX_BYTES) {
            $errors[] = 'حجم الملف كبير جدًا (الحد الأقصى 40 ميجابايت).';
        } else {
            $linkedDocumentId = null;
            if ($documentId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM report_documents WHERE id = ? AND user_id = ?');
                $stmt->execute([$documentId, $user['id']]);
                if ($stmt->fetch()) {
                    $linkedDocumentId = $documentId;
                }
            }

            $storedName = uniqid('paper_', true) . '.pdf';
            $destination = __DIR__ . '/../uploads/published_papers/' . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $errors[] = 'تعذر حفظ الملف على الخادم.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO published_papers (user_id, document_id, title, description, stored_filename, status)
                                        VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$user['id'], $linkedDocumentId, $title, $description ?: null, $storedName, 'pending']);
                flash_set('تم إرسال بحثك للمراجعة. راح يظهر بمكتبة الأبحاث بعد موافقة الإدارة.');
                redirect('student/publish-paper.php');
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM published_papers WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$papers = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, title FROM report_documents WHERE user_id = ? AND status = 'completed' ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$completedDocs = $stmt->fetchAll();

$page_title = 'نشر بحث';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>نشر بحث جديد</h2>
    <p class="hint">
        ارفع نسخة PDF من بحثك المكتمل ليُنشر بمكتبة الأبحاث العامة بعد مراجعة الإدارة والموافقة عليه.
        إذا أنجزت بحثك عبر ميزة "التقارير والأوراق البحثية"، صدّره أولًا كـ PDF من صفحة المحرر ثم ارفعه هنا.
    </p>
    <form method="post" enctype="multipart/form-data" class="auth-form">
        <?= csrf_field() ?>
        <label>عنوان البحث
            <input type="text" name="title" required>
        </label>
        <label>وصف مختصر (اختياري)
            <textarea name="description" rows="3"></textarea>
        </label>
        <?php if (!empty($completedDocs)): ?>
        <label>ربط بمستند من "التقارير والأوراق البحثية" (اختياري)
            <select name="document_id">
                <option value="0">— بدون ربط —</option>
                <?php foreach ($completedDocs as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= h($d['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label>ملف PDF
            <input type="file" name="pdf_file" accept=".pdf" required>
        </label>
        <button type="submit" class="btn">إرسال للمراجعة</button>
    </form>
</div>

<div class="panel">
    <h2>أبحاثي المرسلة (<?= count($papers) ?>)</h2>
    <?php if (empty($papers)): ?>
        <p class="hint">لم ترسل أي بحث بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>العنوان</th><th>الحالة</th><th>ملاحظة الإدارة</th><th>التاريخ</th></tr></thead>
            <tbody>
                <?php foreach ($papers as $p): ?>
                    <tr>
                        <td><?= h($p['title']) ?></td>
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
                        <td><?= h($p['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
