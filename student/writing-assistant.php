<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();
$errors = [];

$sectionLabels = [
    'abstract'     => 'ملخص / مستخلص البحث',
    'introduction' => 'المقدمة',
    'literature'   => 'الإطار النظري ومراجعة الدراسات السابقة',
    'methodology'  => 'منهجية البحث',
    'discussion'   => 'عرض ومناقشة النتائج',
    'conclusion'   => 'الخاتمة والتوصيات',
];

$old = ['section_type' => 'introduction', 'topic' => '', 'instructions' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['section_type'] = array_key_exists($_POST['section_type'] ?? '', $sectionLabels) ? $_POST['section_type'] : 'introduction';
    $old['topic'] = trim($_POST['topic'] ?? '');
    $old['instructions'] = trim($_POST['instructions'] ?? '');

    if ($old['topic'] === '') {
        $errors[] = 'الرجاء إدخال موضوع البحث.';
    } elseif (usage_remaining($pdo, $user) <= 0) {
        $errors[] = 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.';
    } else {
        $stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmtUser->execute([$user['id']]);
        $fullUser = $stmtUser->fetch();

        $sectionLabel = $sectionLabels[$old['section_type']];
        $prompt = "اكتب قسم \"$sectionLabel\" فقط (وليس البحث كاملًا) لبحث أكاديمي، بأسلوب أكاديمي رصين ولغة عربية فصحى.\n" .
            "موضوع البحث: " . $old['topic'] . "\n";
        if ($old['instructions'] !== '') {
            $prompt .= "نقاط/ملاحظات يجب مراعاتها: " . $old['instructions'] . "\n";
        }
        $prompt .= "\nاكتب نصًا متماسكًا مباشرًا بدون عناوين تعريفية زائدة، جاهزًا للنسخ داخل البحث.";

        $systemPrompt = build_academic_system_prompt($fullUser);
        $result = ai_chat([['role' => 'user', 'content' => $prompt]], $systemPrompt, 2500);

        if ($result['ok']) {
            $stmt = $pdo->prepare('INSERT INTO writing_drafts (user_id, section_type, topic, instructions, generated_content)
                                    VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], $old['section_type'], $old['topic'], $old['instructions'] !== '' ? $old['instructions'] : null, $result['text']]);
            record_ai_usage($pdo, $user['id']);
            redirect('student/writing-draft-view.php?id=' . $pdo->lastInsertId());
        } else {
            $errors[] = 'تعذر توليد النص: ' . $result['error'];
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM writing_drafts WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$drafts = $stmt->fetchAll();

$page_title = 'الكتابة الأكاديمية';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>مساعد الكتابة الأكاديمية</h2>
    <p class="hint">يولّد مسودة قسم واحد محدد من بحثك (وليس محادثة عامة)، جاهزة للمراجعة والتعديل.</p>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label>القسم المطلوب
            <select name="section_type">
                <?php foreach ($sectionLabels as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $old['section_type'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>موضوع البحث
            <input type="text" name="topic" value="<?= h($old['topic']) ?>" required>
        </label>
        <label>نقاط أو ملاحظات تريد مراعاتها (اختياري)
            <textarea name="instructions" rows="4" placeholder="مثال: ركّز على السياق العراقي، اذكر 3 دراسات سابقة عربية إن أمكن..."><?= h($old['instructions']) ?></textarea>
        </label>
        <button type="submit" class="btn">توليد المسودة</button>
    </form>
</div>

<div class="panel">
    <h2>مسوداتي السابقة</h2>
    <?php if (empty($drafts)): ?>
        <p class="hint">لا توجد مسودات بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>القسم</th><th>الموضوع</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($drafts as $d): ?>
                    <tr>
                        <td><?= h($sectionLabels[$d['section_type']] ?? $d['section_type']) ?></td>
                        <td><?= h($d['topic']) ?></td>
                        <td><?= h($d['created_at']) ?></td>
                        <td><a href="<?= BASE_URL ?>student/writing-draft-view.php?id=<?= (int)$d['id'] ?>">عرض</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
