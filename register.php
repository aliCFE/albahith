<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/telegram.php';

if (is_logged_in()) {
    redirect(role_dashboard_path(current_user()['role']));
}

$pdo = get_db();
$universities = get_all_universities($pdo);
$colleges = get_all_colleges($pdo);
$specializations = get_all_specializations($pdo);

$errors = [];
$old = ['name' => '', 'email' => '', 'role' => 'student', 'university' => '', 'college' => '', 'specialization' => '', 'degree_level' => 'bachelor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['name'] = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['role'] = ($_POST['role'] ?? 'student') === 'instructor' ? 'instructor' : 'student';
    $universityChoice = trim($_POST['university_choice'] ?? '');
    $old['university'] = $universityChoice === '__other__' ? trim($_POST['university_other'] ?? '') : $universityChoice;
    $collegeChoice = trim($_POST['college_choice'] ?? '');
    $old['college'] = $collegeChoice === '__other__' ? trim($_POST['college_other'] ?? '') : $collegeChoice;
    $specializationChoice = trim($_POST['specialization_choice'] ?? '');
    $old['specialization'] = $specializationChoice === '__other__' ? trim($_POST['specialization_other'] ?? '') : $specializationChoice;
    $old['degree_level'] = $_POST['degree_level'] ?? 'bachelor';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($old['name'] === '' || $old['email'] === '' || $password === '') {
        $errors[] = 'الرجاء تعبئة الاسم والبريد الإلكتروني وكلمة المرور.';
    }
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'صيغة البريد الإلكتروني غير صحيحة.';
    }
    if (strlen($password) > 0 && strlen($password) < 6) {
        $errors[] = 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل.';
    }
    if ($password !== $password2) {
        $errors[] = 'كلمتا المرور غير متطابقتين.';
    }
    if (!in_array($old['degree_level'], ['bachelor', 'master', 'phd'], true)) {
        $old['degree_level'] = 'bachelor';
    }

    if (empty($errors)) {
        $result = register_student($pdo, [
            'name'           => $old['name'],
            'email'          => $old['email'],
            'password'       => $password,
            'role'           => $old['role'],
            'university'     => $old['university'],
            'college'        => $old['college'],
            'specialization' => $old['specialization'],
            'degree_level'   => $old['degree_level'],
        ]);

        if (is_array($result) && isset($result['error'])) {
            $errors[] = $result['error'];
        } else {
            $uniLine = $old['university'] !== '' ? "\nالجامعة: " . telegram_escape($old['university']) : '';
            telegram_notify_admin(
                "🆕 <b>تسجيل حساب جديد بباحث</b>\n\n" .
                'الاسم: ' . telegram_escape($old['name']) . "\n" .
                'البريد: ' . telegram_escape($old['email']) . "\n" .
                'النوع: ' . telegram_escape(role_label($old['role'])) . $uniLine
            );
            attempt_login($pdo, $old['email'], $password);
            flash_set('مرحبًا بك في باحث! أكمل بياناتك من صفحة "حسابي" في أي وقت.');
            redirect('student/dashboard.php');
        }
    }
}

$universitySel = resolve_choice_selection($old['university'], $universities);
$collegeSel = resolve_choice_selection($old['college'], $colleges);
$specializationSel = resolve_choice_selection($old['specialization'], $specializations);

$page_title = 'إنشاء حساب';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-box auth-box-wide">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label>نوع الحساب
            <select name="role">
                <option value="student" <?= $old['role'] === 'student' ? 'selected' : '' ?>>طالب</option>
                <option value="instructor" <?= $old['role'] === 'instructor' ? 'selected' : '' ?>>تدريسي</option>
            </select>
        </label>
        <label>الاسم الكامل
            <input type="text" name="name" value="<?= h($old['name']) ?>" required autofocus>
        </label>
        <label>البريد الإلكتروني
            <input type="email" name="email" value="<?= h($old['email']) ?>" required>
        </label>
        <label>كلمة المرور
            <input type="password" name="password" required minlength="6">
        </label>
        <label>تأكيد كلمة المرور
            <input type="password" name="password2" required minlength="6">
        </label>

        <label>الجامعة (اختياري الآن)
            <select name="university_choice" class="dropdown-with-other" data-other-target="university-other-wrap">
                <option value="">— اختر جامعتك —</option>
                <?php foreach ($universities as $uni): ?>
                    <option value="<?= h($uni) ?>" <?= $universitySel['select'] === $uni ? 'selected' : '' ?>><?= h($uni) ?></option>
                <?php endforeach; ?>
                <option value="__other__" <?= $universitySel['select'] === '__other__' ? 'selected' : '' ?>>جامعتي غير موجودة بالقائمة</option>
            </select>
        </label>
        <label id="university-other-wrap" class="other-field<?= $universitySel['select'] === '__other__' ? '' : ' hidden' ?>">اكتب اسم جامعتك
            <input type="text" name="university_other" value="<?= h($universitySel['other']) ?>" placeholder="مثال: جامعة بغداد">
        </label>

        <label>الكلية
            <select name="college_choice" class="dropdown-with-other" data-other-target="college-other-wrap">
                <option value="">— اختر كليتك —</option>
                <?php foreach ($colleges as $col): ?>
                    <option value="<?= h($col) ?>" <?= $collegeSel['select'] === $col ? 'selected' : '' ?>><?= h($col) ?></option>
                <?php endforeach; ?>
                <option value="__other__" <?= $collegeSel['select'] === '__other__' ? 'selected' : '' ?>>كليتي غير موجودة بالقائمة</option>
            </select>
        </label>
        <label id="college-other-wrap" class="other-field<?= $collegeSel['select'] === '__other__' ? '' : ' hidden' ?>">اكتب اسم كليتك
            <input type="text" name="college_other" value="<?= h($collegeSel['other']) ?>" placeholder="مثال: كلية الهندسة">
        </label>

        <label>التخصص
            <select name="specialization_choice" class="dropdown-with-other" data-other-target="specialization-other-wrap">
                <option value="">— اختر تخصصك —</option>
                <?php foreach ($specializations as $spec): ?>
                    <option value="<?= h($spec) ?>" <?= $specializationSel['select'] === $spec ? 'selected' : '' ?>><?= h($spec) ?></option>
                <?php endforeach; ?>
                <option value="__other__" <?= $specializationSel['select'] === '__other__' ? 'selected' : '' ?>>تخصصي غير موجود بالقائمة</option>
            </select>
        </label>
        <label id="specialization-other-wrap" class="other-field<?= $specializationSel['select'] === '__other__' ? '' : ' hidden' ?>">اكتب اسم تخصصك
            <input type="text" name="specialization_other" value="<?= h($specializationSel['other']) ?>" placeholder="مثال: هندسة حاسوب">
        </label>
        <label>المرحلة الدراسية
            <select name="degree_level">
                <option value="bachelor" <?= $old['degree_level'] === 'bachelor' ? 'selected' : '' ?>>بكالوريوس</option>
                <option value="master" <?= $old['degree_level'] === 'master' ? 'selected' : '' ?>>ماجستير</option>
                <option value="phd" <?= $old['degree_level'] === 'phd' ? 'selected' : '' ?>>دكتوراه</option>
            </select>
        </label>
        <button type="submit" class="btn">إنشاء الحساب</button>
    </form>
    <p class="auth-alt">لديك حساب مسبقًا؟ <a href="<?= BASE_URL ?>login.php">تسجيل الدخول</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
