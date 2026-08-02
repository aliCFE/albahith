<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
require_once __DIR__ . '/../includes/pptx.php';
require_once __DIR__ . '/../includes/docx.php';
require_role(['student', 'instructor']);

const PRESENTATION_SOURCE_MAX_BYTES = 40 * 1024 * 1024; // 40 ميجابايت
const PDF_MAX_ESTIMATED_PAGES = 80; // فوق هذا يتجاوز حدود سياق نماذج الذكاء الاصطناعي غالبًا

$pdo = get_db();
$user = current_user();
$errors = [];

$stmt = $pdo->prepare('SELECT id, topic FROM research_plans WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$researchPlans = $stmt->fetchAll();

$old = ['topic' => '', 'research_plan_id' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['topic'] = trim($_POST['topic'] ?? '');
    $old['research_plan_id'] = trim($_POST['research_plan_id'] ?? '');
    $old['notes'] = trim($_POST['notes'] ?? '');

    $sourceContent = '';
    $hasUploadedFile = !empty($_FILES['source_file']) && $_FILES['source_file']['error'] === UPLOAD_ERR_OK;

    if ($hasUploadedFile) {
        $file = $_FILES['source_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['pdf', 'docx'], true)) {
            $errors[] = 'يُسمح فقط برفع ملفات PDF أو Word (.docx).';
        } elseif ($file['size'] > PRESENTATION_SOURCE_MAX_BYTES) {
            $errors[] = 'حجم الملف كبير جدًا (الحد الأقصى 40 ميجابايت).';
        } elseif (usage_remaining($pdo, $user) <= 0) {
            $errors[] = 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.';
        } else {
            $stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmtUser->execute([$user['id']]);
            $fullUser = $stmtUser->fetch();
            $systemPrompt = build_academic_system_prompt($fullUser);

            if ($ext === 'pdf') {
                $estimatedPages = pdf_estimate_page_count($file['tmp_name']);
                if ($estimatedPages !== null && $estimatedPages > PDF_MAX_ESTIMATED_PAGES) {
                    $errors[] = 'هذا الملف طويل جدًا (تقريبًا ' . $estimatedPages . ' صفحة) ويتجاوز الحد الذي يقدر الذكاء الاصطناعي يعالجه بطلب واحد (الحد الأقصى تقريبًا ' . PDF_MAX_ESTIMATED_PAGES . ' صفحة). الرجاء تقسيم الملف لأجزاء أصغر ورفع كل جزء لحاله.';
                } else {
                    $base64 = base64_encode(file_get_contents($file['tmp_name']));
                    $instruction = 'استخرج أهم المحتوى والنقاط الرئيسية من هذا المستند بشكل نصي منظم يصلح ليكون مصدرًا لإعداد عرض تقديمي عنه. اكتب النتيجة كنص متصل دون تعليق إضافي.';
                    $extractResult = ai_analyze_pdf($base64, $instruction, $systemPrompt);
                    if ($extractResult['ok']) {
                        $sourceContent = $extractResult['text'];
                        record_ai_usage($pdo, $user['id']);
                    } else {
                        $errors[] = 'تعذر استخراج محتوى ملف PDF: ' . $extractResult['error'];
                    }
                }
            } else {
                $text = extract_text_from_docx($file['tmp_name']);
                if ($text === null) {
                    $errors[] = 'تعذر استخراج النص من ملف Word هذا. تأكد أنه ملف .docx سليم.';
                } else {
                    $sourceContent = $text;
                }
            }
        }
    } elseif ($old['research_plan_id'] !== '') {
        $stmt = $pdo->prepare('SELECT * FROM research_plans WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)$old['research_plan_id'], $user['id']]);
        $plan = $stmt->fetch();
        if ($plan) {
            $sourceContent = $plan['generated_content'];
            if ($old['topic'] === '') $old['topic'] = $plan['topic'];
        }
    }

    if ($old['topic'] === '' && $sourceContent === '') {
        $errors[] = 'الرجاء إدخال موضوع العرض التقديمي، أو اختيار خطة بحث محفوظة، أو رفع ملف مصدر.';
    } elseif ($old['topic'] === '') {
        $old['topic'] = 'عرض تقديمي من الملف المرفوع';
    }

    if (empty($errors) && usage_remaining($pdo, $user) <= 0) {
        $errors[] = 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.';
    }

    if (empty($errors)) {
        $stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmtUser->execute([$user['id']]);
        $fullUser = $stmtUser->fetch();

        $prompt = "أنشئ مخطط عرض تقديمي أكاديمي (بين 6 و9 شرائح شاملة شريحة العنوان) لموضوع: " . $old['topic'] . ".\n";
        if ($sourceContent !== '') {
            $prompt .= "استند إلى محتوى خطة البحث التالية:\n" . mb_substr($sourceContent, 0, 4000) . "\n";
        }
        if ($old['notes'] !== '') {
            $prompt .= "ملاحظات إضافية: " . $old['notes'] . "\n";
        }
        $prompt .= "\nأجب حصرًا بصيغة JSON صحيحة وبدون أي نص أو شرح خارج الـ JSON وبدون Markdown، بهذا الشكل بالضبط:\n" .
            '{"slides":[{"title":"عنوان الشريحة","icon":"🎯","image_query":"english keywords","bullets":["نقطة 1","نقطة 2"]}]}' . "\n" .
            "اجعل الشريحة الأولى شريحة عنوان (title فقط بعنوان الموضوع، وbullets تحتوي جملة وصفية واحدة قصيرة)، وبقية الشرائح بعناوين ونقاط موجزة (3-5 نقاط لكل شريحة) بالعربية الفصحى.\n" .
            "لكل شريحة اختر رمزًا تعبيريًا (emoji) واحدًا فقط يعبّر فعليًا عن مضمون تلك الشريحة تحديدًا (مثلًا 📊 للبيانات، 🎯 للأهداف، 🔬 للمنهجية، 💡 للأفكار، ✅ للنتائج، 📚 للدراسات السابقة)، وضعه بحقل icon، بشرط أن يكون رمزًا مختلفًا ومناسبًا لكل شريحة حسب محتواها الفعلي وليس نفس الرمز مكررًا.\n" .
            "لكل شريحة ما عدا شريحة العنوان، ضع بحقل image_query عبارة بحث قصيرة بالإنجليزية (كلمتين إلى أربع كلمات) تصف صورة فوتوغرافية مناسبة لمحتوى تلك الشريحة تحديدًا، لتُستخدم بالبحث عن صورة حقيقية من مكتبة صور مجانية. " .
            "قيد إلزامي مهم جدًا: يُمنع منعًا باتًا أن تتضمن عبارة البحث أي إشارة لأشخاص أو بشر (ممنوع كلمات مثل person, people, man, woman, student, human, face, portrait وما شابهها) — اختر دائمًا موضوعات بديلة لا تحتوي بشرًا مثل: أشياء (books, laptop, notebook)، رموز ومخططات (chart, graph, icon)، طبيعة (nature, library interior, classroom empty)، أو مفاهيم مجردة (lightbulb idea, growth arrow, puzzle pieces). مثال صحيح: \"open books stack\" أو \"data analysis chart\" — مثال خاطئ ممنوع: \"students studying\" أو \"woman reading\".";

        $systemPrompt = build_academic_system_prompt($fullUser);
        $result = ai_chat([['role' => 'user', 'content' => $prompt]], $systemPrompt, 3000);

        if (!$result['ok']) {
            $errors[] = 'تعذر توليد العرض التقديمي: ' . $result['error'];
        } else {
            $jsonText = trim($result['text']);
            $jsonText = preg_replace('/^```(json)?/i', '', $jsonText);
            $jsonText = preg_replace('/```$/', '', trim($jsonText));
            $parsed = json_decode(trim($jsonText), true);

            if (!is_array($parsed) || empty($parsed['slides']) || !is_array($parsed['slides'])) {
                $errors[] = 'تعذر تحليل مخطط العرض الناتج من الذكاء الاصطناعي. الرجاء المحاولة مرة أخرى.';
            } else {
                $slides = [];
                foreach ($parsed['slides'] as $s) {
                    $slides[] = [
                        'title'       => (string)($s['title'] ?? ''),
                        'icon'        => mb_substr((string)($s['icon'] ?? ''), 0, 8, 'UTF-8'),
                        'image_query' => mb_substr((string)($s['image_query'] ?? ''), 0, 100, 'UTF-8'),
                        'bullets'     => array_values(array_filter(array_map('strval', $s['bullets'] ?? []))),
                    ];
                }

                $storedName = uniqid('pres_', true) . '.pptx';
                $destination = __DIR__ . '/../uploads/presentations/' . $storedName;

                if (!build_pptx_file($slides, $destination)) {
                    $errors[] = 'تعذر إنشاء ملف العرض التقديمي على الخادم.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO presentations (user_id, topic, outline_json, stored_filename, status) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$user['id'], $old['topic'], json_encode($slides, JSON_UNESCAPED_UNICODE), $storedName, 'done']);
                    record_ai_usage($pdo, $user['id']);
                    redirect('student/presentation-view.php?id=' . $pdo->lastInsertId());
                }
            }
        }
    }

    if (!empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO presentations (user_id, topic, status, error_message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user['id'], $old['topic'] !== '' ? $old['topic'] : 'محاولة فاشلة', 'failed', implode(' | ', $errors)]);
    }
}

$stmt = $pdo->prepare('SELECT * FROM presentations WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$presentations = $stmt->fetchAll();

$page_title = 'العروض التقديمية';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>إنشاء عرض تقديمي جديد</h2>
    <p class="hint">يولّد ملف PowerPoint (.pptx) حقيقي بتصميم جاهز، قابل للتحميل والتعديل مباشرة.</p>
    <form method="post" enctype="multipart/form-data" class="auth-form">
        <?= csrf_field() ?>
        <label>ارفع ملف PDF أو Word ليكون مصدر العرض (اختياري)
            <input type="file" name="source_file" accept=".pdf,.docx">
        </label>
        <p class="hint">إذا رفعت ملفًا، راح يُعتمد كمصدر رئيسي للمحتوى بدل خطة البحث أدناه.</p>
        <label>موضوع العرض
            <input type="text" name="topic" value="<?= h($old['topic']) ?>" placeholder="اتركه فارغًا إذا رفعت ملفًا أو اخترت خطة بحث">
        </label>
        <?php if (!empty($researchPlans)): ?>
        <label>أو ولّد من خطة بحث محفوظة (اختياري)
            <select name="research_plan_id">
                <option value="">— بدون —</option>
                <?php foreach ($researchPlans as $rp): ?>
                    <option value="<?= (int)$rp['id'] ?>" <?= $old['research_plan_id'] == $rp['id'] ? 'selected' : '' ?>><?= h($rp['topic']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label>ملاحظات إضافية (اختياري)
            <textarea name="notes" rows="3"><?= h($old['notes']) ?></textarea>
        </label>
        <button type="submit" class="btn">توليد العرض التقديمي</button>
    </form>
</div>

<div class="panel">
    <h2>عروضي السابقة</h2>
    <?php if (empty($presentations)): ?>
        <p class="hint">لا توجد عروض تقديمية بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>الموضوع</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($presentations as $p): ?>
                    <tr>
                        <td><?= h($p['topic']) ?></td>
                        <td>
                            <?php if ($p['status'] === 'done'): ?>
                                <span class="badge badge-success">تم التوليد</span>
                            <?php else: ?>
                                <span class="badge badge-error">فشل</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($p['created_at']) ?></td>
                        <td>
                            <?php if ($p['status'] === 'done'): ?>
                                <a href="<?= BASE_URL ?>student/presentation-view.php?id=<?= (int)$p['id'] ?>">عرض</a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>student/presentation-view.php?id=<?= (int)$p['id'] ?>">عرض سبب الفشل</a>
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
