<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
require_once __DIR__ . '/../includes/docx.php';
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

const REPORT_FILE_MAX_BYTES = 40 * 1024 * 1024; // 40 ميجابايت
const PDF_MAX_ESTIMATED_PAGES = 80; // فوق هذا يتجاوز حدود سياق نماذج الذكاء الاصطناعي غالبًا
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (usage_remaining($pdo, $user) <= 0) {
            $errors[] = 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.';
        } elseif (empty($_FILES['source_file']) || $_FILES['source_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'الرجاء اختيار ملف صالح.';
        } else {
            $file = $_FILES['source_file'];
            $originalName = $file['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['pdf', 'docx'], true)) {
                $errors[] = 'يُسمح فقط برفع ملفات PDF أو Word (.docx).';
            } elseif ($file['size'] > REPORT_FILE_MAX_BYTES) {
                $errors[] = 'حجم الملف كبير جدًا (الحد الأقصى 40 ميجابايت).';
            } else {
                $storedName = uniqid('src_', true) . '.' . $ext;
                $destination = __DIR__ . '/../uploads/report_files/' . $storedName;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'تعذر حفظ الملف على الخادم.';
                } else {
                    $stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                    $stmtUser->execute([$user['id']]);
                    $fullUser = $stmtUser->fetch();
                    $systemPrompt = build_academic_system_prompt($fullUser);
                    $instruction = 'استخرج ولخّص أهم المحتوى العلمي من هذا المستند بشكل نصي مفصّل ومنظم (الأفكار الرئيسية، البيانات، الاقتباسات المهمة إن وجدت)، بحيث يصلح لاستخدامه كمصدر مرجعي عند كتابة بحث أكاديمي عن نفس الموضوع. اكتب النتيجة كنص متصل دون تعليق إضافي.';

                    if ($ext === 'pdf') {
                        $estimatedPages = pdf_estimate_page_count($destination);
                        if ($estimatedPages !== null && $estimatedPages > PDF_MAX_ESTIMATED_PAGES) {
                            $result = ['ok' => false, 'error' => 'هذا الملف طويل جدًا (تقريبًا ' . $estimatedPages . ' صفحة) ويتجاوز الحد الذي يقدر الذكاء الاصطناعي يعالجه بطلب واحد (الحد الأقصى تقريبًا ' . PDF_MAX_ESTIMATED_PAGES . ' صفحة). الرجاء تقسيم الملف لأجزاء أصغر ورفع كل جزء لحاله.'];
                        } else {
                            $base64 = base64_encode(file_get_contents($destination));
                            $result = ai_analyze_pdf($base64, $instruction, $systemPrompt);
                        }
                    } else {
                        $text = extract_text_from_docx($destination);
                        if ($text === null) {
                            $result = ['ok' => false, 'error' => 'تعذر استخراج النص من ملف Word هذا. تأكد أنه ملف .docx سليم.'];
                        } else {
                            $result = ['ok' => true, 'text' => $text];
                        }
                    }

                    $stmt = $pdo->prepare('INSERT INTO report_files (document_id, user_id, original_filename, stored_filename, file_type, file_size, processing_status, extracted_text, error_message)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    if ($result['ok']) {
                        $stmt->execute([$id, $user['id'], $originalName, $storedName, $ext, (int)$file['size'], 'done', $result['text'], null]);
                        if ($ext === 'pdf') {
                            record_ai_usage($pdo, $user['id']);
                        }
                        flash_set('تم رفع الملف واستخراج محتواه، وسيُستخدم كمصدر عند توليد الأقسام.');
                    } else {
                        $stmt->execute([$id, $user['id'], $originalName, $storedName, $ext, (int)$file['size'], 'failed', null, $result['error']]);
                        $errors[] = 'تعذر معالجة الملف: ' . $result['error'];
                    }
                    redirect('student/reports-files.php?id=' . $id);
                }
            }
        }
    } elseif ($action === 'delete') {
        $fileId = (int)($_POST['file_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM report_files WHERE id = ? AND document_id = ?');
        $stmt->execute([$fileId, $id]);
        $sourceFile = $stmt->fetch();
        if ($sourceFile) {
            $path = __DIR__ . '/../uploads/report_files/' . $sourceFile['stored_filename'];
            if (is_file($path)) {
                unlink($path);
            }
            $pdo->prepare('DELETE FROM report_files WHERE id = ?')->execute([$fileId]);
            flash_set('تم حذف الملف.');
        }
        redirect('student/reports-files.php?id=' . $id);
    }
}

$stmt = $pdo->prepare('SELECT * FROM report_files WHERE document_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$files = $stmt->fetchAll();

$page_title = 'مصادر: ' . $doc['title'];
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <p><a href="<?= BASE_URL ?>student/reports-editor.php?id=<?= (int)$id ?>">→ العودة للمحرر</a></p>
    <h2>رفع مصادر لـ "<?= h($doc['title']) ?>"</h2>
    <p class="hint">ارفع ملفات PDF أو Word تحتوي على مصادر أو دراسات تريد أن يعتمد عليها المساعد الذكي عند كتابة الأقسام. يدعم حتى 40 ميجابايت لكل ملف.</p>
    <form method="post" enctype="multipart/form-data" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <label>اختر ملف
            <input type="file" name="source_file" accept=".pdf,.docx" required>
        </label>
        <button type="submit" class="btn">رفع ومعالجة</button>
    </form>
</div>

<div class="panel">
    <h2>الملفات المرفوعة (<?= count($files) ?>)</h2>
    <?php if (empty($files)): ?>
        <p class="hint">لا توجد ملفات مصادر بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>الملف</th><th>النوع</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($files as $f): ?>
                    <tr>
                        <td><?= h($f['original_filename']) ?></td>
                        <td><?= strtoupper(h($f['file_type'])) ?></td>
                        <td>
                            <?php if ($f['processing_status'] === 'done'): ?>
                                <span class="badge badge-success">جاهز كمصدر</span>
                            <?php elseif ($f['processing_status'] === 'failed'): ?>
                                <span class="badge badge-error">فشلت المعالجة</span>
                            <?php else: ?>
                                <span class="badge badge-info">قيد المعالجة</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($f['created_at']) ?></td>
                        <td>
                            <form method="post" class="inline-form" onsubmit="return confirm('حذف هذا الملف؟');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
                                <button type="submit" class="btn-link">حذف</button>
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
