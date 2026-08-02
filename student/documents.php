<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
require_once __DIR__ . '/../includes/docx.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

const MAX_UPLOAD_BYTES = 40 * 1024 * 1024; // 40 ميجابايت
const PDF_MAX_ESTIMATED_PAGES = 80; // فوق هذا يتجاوز حدود سياق نماذج الذكاء الاصطناعي غالبًا
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (usage_remaining($pdo, $user) <= 0) {
        $errors[] = 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.';
    } elseif (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'الرجاء اختيار ملف صالح.';
    } else {
        $file = $_FILES['document'];
        $originalName = $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['pdf', 'docx'], true)) {
            $errors[] = 'يُسمح فقط برفع ملفات PDF أو Word (.docx).';
        } elseif ($file['size'] > MAX_UPLOAD_BYTES) {
            $errors[] = 'حجم الملف كبير جدًا (الحد الأقصى 40 ميجابايت).';
        } else {
            $storedName = uniqid('doc_', true) . '.' . $ext;
            $destination = __DIR__ . '/../uploads/documents/' . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $errors[] = 'تعذر حفظ الملف على الخادم.';
            } else {
                $stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                $stmtUser->execute([$user['id']]);
                $fullUser = $stmtUser->fetch();

                $instruction = 'لخّص هذا المستند الأكاديمي بشكل منظم (نقاط رئيسية، أهم الأفكار، الاستنتاجات إن وجدت)، ثم أضف ملاحظات موجزة حول نقاط القوة أو المآخذ المنهجية إن وجدت. أجب باللغة العربية بصيغة نقاط واضحة.';
                $systemPrompt = build_academic_system_prompt($fullUser);

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
                        $result = ai_analyze_text($text, $instruction, $systemPrompt);
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO documents (user_id, original_filename, stored_filename, file_type, ai_summary, status, error_message)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)');
                if ($result['ok']) {
                    $stmt->execute([$user['id'], $originalName, $storedName, $ext, $result['text'], 'done', null]);
                    record_ai_usage($pdo, $user['id']);
                    $newId = (int)$pdo->lastInsertId();
                    redirect('student/document-view.php?id=' . $newId);
                } else {
                    $stmt->execute([$user['id'], $originalName, $storedName, $ext, null, 'failed', $result['error']]);
                    $errors[] = 'تعذر تحليل المستند: ' . $result['error'];
                }
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$documents = $stmt->fetchAll();

$page_title = 'تحليل المستندات';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>رفع مستند جديد</h2>
    <p class="hint">يدعم ملفات PDF وWord (.docx) حتى 40 ميجابايت. سيقوم المساعد الذكي بتلخيص المستند وتحليله تلقائيًا.</p>
    <form method="post" enctype="multipart/form-data" class="auth-form">
        <?= csrf_field() ?>
        <label>اختر ملف
            <input type="file" name="document" accept=".pdf,.docx" required>
        </label>
        <button type="submit" class="btn">رفع وتحليل</button>
    </form>
</div>

<div class="panel">
    <h2>مستنداتي</h2>
    <?php if (empty($documents)): ?>
        <p class="hint">لم ترفع أي مستند بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>الملف</th><th>النوع</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?= h($doc['original_filename']) ?></td>
                        <td><?= strtoupper(h($doc['file_type'])) ?></td>
                        <td>
                            <?php if ($doc['status'] === 'done'): ?>
                                <span class="badge badge-success">تم التحليل</span>
                            <?php else: ?>
                                <span class="badge badge-error">فشل</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($doc['created_at']) ?></td>
                        <td>
                            <?php if ($doc['status'] === 'done'): ?>
                                <a href="<?= BASE_URL ?>student/document-view.php?id=<?= (int)$doc['id'] ?>">عرض التحليل</a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>student/document-view.php?id=<?= (int)$doc['id'] ?>">عرض سبب الفشل</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
