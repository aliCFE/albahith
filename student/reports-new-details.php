<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$typeKey = trim($_GET['type'] ?? $_POST['document_type'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM report_types WHERE type_key = ? AND is_active = 1');
$stmt->execute([$typeKey]);
$type = $stmt->fetch();

if (!$type) {
    flash_set('نوع المستند غير موجود.', 'error');
    redirect('student/reports-new.php');
}

$universities = get_all_universities($pdo);
$colleges = get_all_colleges($pdo);
$specializations = get_all_specializations($pdo);

$stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmtUser->execute([$user['id']]);
$profile = $stmtUser->fetch();

$errors = [];
$old = [
    'title' => '', 'topic' => '', 'department' => '', 'audience' => '', 'notes' => '',
    'language' => 'ar', 'academic_level' => $profile['degree_level'] ?: 'bachelor',
    'writing_level' => 'bachelor', 'target_pages' => '', 'target_words' => '',
    'university' => $profile['university'] ?: '', 'college' => $profile['college'] ?: '', 'specialization' => $profile['specialization'] ?: '',
];

$writingLevels = [
    'bachelor'     => 'طالب بكالوريوس',
    'diploma'      => 'طالب دبلوم',
    'master'       => 'طالب ماجستير',
    'phd'          => 'طالب دكتوراه',
    'researcher'   => 'باحث متخصص',
    'professional' => 'تقرير مهني',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['title'] = trim($_POST['title'] ?? '');
    $old['topic'] = trim($_POST['topic'] ?? '');
    $old['department'] = trim($_POST['department'] ?? '');
    $old['audience'] = trim($_POST['audience'] ?? '');
    $old['notes'] = trim($_POST['notes'] ?? '');
    $old['language'] = ($_POST['language'] ?? 'ar') === 'en' ? 'en' : 'ar';
    $old['academic_level'] = in_array($_POST['academic_level'] ?? '', ['bachelor', 'master', 'phd'], true) ? $_POST['academic_level'] : 'bachelor';
    $old['writing_level'] = array_key_exists($_POST['writing_level'] ?? '', $writingLevels) ? $_POST['writing_level'] : 'bachelor';
    $old['target_pages'] = trim($_POST['target_pages'] ?? '');
    $old['target_words'] = trim($_POST['target_words'] ?? '');

    $universityChoice = trim($_POST['university_choice'] ?? '');
    $old['university'] = $universityChoice === '__other__' ? trim($_POST['university_other'] ?? '') : $universityChoice;
    $collegeChoice = trim($_POST['college_choice'] ?? '');
    $old['college'] = $collegeChoice === '__other__' ? trim($_POST['college_other'] ?? '') : $collegeChoice;
    $specializationChoice = trim($_POST['specialization_choice'] ?? '');
    $old['specialization'] = $specializationChoice === '__other__' ? trim($_POST['specialization_other'] ?? '') : $specializationChoice;

    if ($old['title'] === '') {
        $errors[] = 'عنوان المستند مطلوب.';
    }
    if ($old['topic'] === '') {
        $errors[] = 'موضوع البحث مطلوب.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO report_documents
            (user_id, title, document_type, topic, language, academic_level, university, college, department, academic_field, target_pages, target_words, writing_level, audience, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user['id'], $old['title'], $type['type_key'], $old['topic'], $old['language'], $old['academic_level'],
            $old['university'] !== '' ? $old['university'] : null,
            $old['college'] !== '' ? $old['college'] : null,
            $old['department'] !== '' ? $old['department'] : null,
            $old['specialization'] !== '' ? $old['specialization'] : null,
            $old['target_pages'] !== '' ? (int)$old['target_pages'] : null,
            $old['target_words'] !== '' ? (int)$old['target_words'] : null,
            $old['writing_level'], $old['audience'] !== '' ? $old['audience'] : null,
            $old['notes'] !== '' ? $old['notes'] : null,
        ]);
        $newId = (int)$pdo->lastInsertId();
        redirect('student/reports-outline.php?id=' . $newId);
    }
}

$universitySel = resolve_choice_selection($old['university'], $universities);
$collegeSel = resolve_choice_selection($old['college'], $colleges);
$specializationSel = resolve_choice_selection($old['specialization'], $specializations);

$page_title = $type['label_ar'];
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <p><a href="<?= BASE_URL ?>student/reports-new.php">→ تغيير النوع</a></p>
    <h2>بيانات <?= h($type['label_ar']) ?></h2>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="document_type" value="<?= h($type['type_key']) ?>">

        <label>عنوان البحث أو التقرير
            <input type="text" name="title" value="<?= h($old['title']) ?>" required autofocus>
        </label>
        <label>موضوع البحث بالتفصيل
            <textarea name="topic" rows="3" required><?= h($old['topic']) ?></textarea>
        </label>

        <label>التخصص العلمي
            <select name="specialization_choice" class="dropdown-with-other" data-other-target="specialization-other-wrap">
                <option value="">— اختر —</option>
                <?php foreach ($specializations as $spec): ?>
                    <option value="<?= h($spec) ?>" <?= $specializationSel['select'] === $spec ? 'selected' : '' ?>><?= h($spec) ?></option>
                <?php endforeach; ?>
                <option value="__other__" <?= $specializationSel['select'] === '__other__' ? 'selected' : '' ?>>غير موجود بالقائمة</option>
            </select>
        </label>
        <label id="specialization-other-wrap" class="other-field<?= $specializationSel['select'] === '__other__' ? '' : ' hidden' ?>">اكتب التخصص
            <input type="text" name="specialization_other" value="<?= h($specializationSel['other']) ?>">
        </label>

        <label>المرحلة الدراسية
            <select name="academic_level">
                <option value="bachelor" <?= $old['academic_level'] === 'bachelor' ? 'selected' : '' ?>>بكالوريوس</option>
                <option value="master" <?= $old['academic_level'] === 'master' ? 'selected' : '' ?>>ماجستير</option>
                <option value="phd" <?= $old['academic_level'] === 'phd' ? 'selected' : '' ?>>دكتوراه</option>
            </select>
        </label>

        <label>اسم الجامعة
            <select name="university_choice" class="dropdown-with-other" data-other-target="university-other-wrap">
                <option value="">— اختر —</option>
                <?php foreach ($universities as $uni): ?>
                    <option value="<?= h($uni) ?>" <?= $universitySel['select'] === $uni ? 'selected' : '' ?>><?= h($uni) ?></option>
                <?php endforeach; ?>
                <option value="__other__" <?= $universitySel['select'] === '__other__' ? 'selected' : '' ?>>غير موجودة بالقائمة</option>
            </select>
        </label>
        <label id="university-other-wrap" class="other-field<?= $universitySel['select'] === '__other__' ? '' : ' hidden' ?>">اكتب اسم الجامعة
            <input type="text" name="university_other" value="<?= h($universitySel['other']) ?>">
        </label>

        <label>اسم الكلية
            <select name="college_choice" class="dropdown-with-other" data-other-target="college-other-wrap">
                <option value="">— اختر —</option>
                <?php foreach ($colleges as $col): ?>
                    <option value="<?= h($col) ?>" <?= $collegeSel['select'] === $col ? 'selected' : '' ?>><?= h($col) ?></option>
                <?php endforeach; ?>
                <option value="__other__" <?= $collegeSel['select'] === '__other__' ? 'selected' : '' ?>>غير موجودة بالقائمة</option>
            </select>
        </label>
        <label id="college-other-wrap" class="other-field<?= $collegeSel['select'] === '__other__' ? '' : ' hidden' ?>">اكتب اسم الكلية
            <input type="text" name="college_other" value="<?= h($collegeSel['other']) ?>">
        </label>

        <label>اسم القسم (اختياري)
            <input type="text" name="department" value="<?= h($old['department']) ?>">
        </label>

        <label>لغة المستند
            <select name="language">
                <option value="ar" <?= $old['language'] === 'ar' ? 'selected' : '' ?>>العربية</option>
                <option value="en" <?= $old['language'] === 'en' ? 'selected' : '' ?>>English</option>
            </select>
        </label>

        <label>عدد الصفحات التقريبي (اختياري)
            <input type="number" name="target_pages" min="1" value="<?= h($old['target_pages']) ?>">
        </label>
        <label>عدد الكلمات التقريبي (اختياري)
            <input type="number" name="target_words" min="100" value="<?= h($old['target_words']) ?>">
        </label>

        <label>مستوى الكتابة
            <select name="writing_level">
                <?php foreach ($writingLevels as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $old['writing_level'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>الجمهور المستهدف (اختياري)
            <input type="text" name="audience" value="<?= h($old['audience']) ?>" placeholder="مثال: أساتذة القسم، لجنة المناقشة...">
        </label>

        <label>ملاحظات وتعليمات إضافية (اختياري)
            <textarea name="notes" rows="4"><?= h($old['notes']) ?></textarea>
        </label>

        <button type="submit" class="btn">متابعة لإعداد المخطط</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
