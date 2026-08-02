<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$universities = get_all_universities($pdo);
$colleges = get_all_colleges($pdo);
$specializations = get_all_specializations($pdo);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $universityChoice = trim($_POST['university_choice'] ?? '');
        $university = $universityChoice === '__other__' ? trim($_POST['university_other'] ?? '') : $universityChoice;
        $collegeChoice = trim($_POST['college_choice'] ?? '');
        $college = $collegeChoice === '__other__' ? trim($_POST['college_other'] ?? '') : $collegeChoice;
        $specializationChoice = trim($_POST['specialization_choice'] ?? '');
        $specialization = $specializationChoice === '__other__' ? trim($_POST['specialization_other'] ?? '') : $specializationChoice;
        $degree_level = $_POST['degree_level'] ?? 'bachelor';
        if (!in_array($degree_level, ['bachelor', 'master', 'phd'], true)) {
            $degree_level = 'bachelor';
        }

        if ($name === '') {
            $errors[] = 'الاسم مطلوب.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, university = ?, college = ?, specialization = ?, degree_level = ? WHERE id = ?');
            $stmt->execute([
                $name,
                $university !== '' ? $university : null,
                $college !== '' ? $college : null,
                $specialization !== '' ? $specialization : null,
                $degree_level,
                $user['id'],
            ]);
            $_SESSION['user']['name'] = $name;
            flash_set('تم تحديث بياناتك بنجاح.');
            redirect('student/profile.php');
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password2'] ?? '';

        if (!password_verify($current, $profile['password_hash'])) {
            $errors[] = 'كلمة المرور الحالية غير صحيحة.';
        } elseif (strlen($new) < 6) {
            $errors[] = 'يجب أن تتكون كلمة المرور الجديدة من 6 أحرف على الأقل.';
        } elseif ($new !== $new2) {
            $errors[] = 'كلمتا المرور الجديدتان غير متطابقتين.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash_set('تم تغيير كلمة المرور بنجاح.');
            redirect('student/profile.php');
        }
    }

    if (!empty($errors)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
    }
}

$universitySel = resolve_choice_selection($profile['university'] ?? '', $universities);
$collegeSel = resolve_choice_selection($profile['college'] ?? '', $colleges);
$specializationSel = resolve_choice_selection($profile['specialization'] ?? '', $specializations);

$page_title = 'حسابي';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>بياناتي الأكاديمية</h2>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">
        <label>الاسم الكامل
            <input type="text" name="name" value="<?= h($profile['name']) ?>" required>
        </label>
        <label>الجامعة
            <select name="university_choice" class="dropdown-with-other" data-other-target="university-other-wrap">
                <option value="">— اختر جامعتك —</option>
                <?php foreach ($universities as $uni): ?>
                    <option value="<?= h($uni) ?>" <?= $universitySel['select'] === $uni ? 'selected' : '' ?>><?= h($uni) ?></option>
                <?php endforeach; ?>
                <option value="__other__" <?= $universitySel['select'] === '__other__' ? 'selected' : '' ?>>جامعتي غير موجودة بالقائمة</option>
            </select>
        </label>
        <label id="university-other-wrap" class="other-field<?= $universitySel['select'] === '__other__' ? '' : ' hidden' ?>">اكتب اسم جامعتك
            <input type="text" name="university_other" value="<?= h($universitySel['other']) ?>">
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
            <input type="text" name="college_other" value="<?= h($collegeSel['other']) ?>">
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
            <input type="text" name="specialization_other" value="<?= h($specializationSel['other']) ?>">
        </label>
        <label>المرحلة الدراسية
            <select name="degree_level">
                <option value="bachelor" <?= $profile['degree_level'] === 'bachelor' ? 'selected' : '' ?>>بكالوريوس</option>
                <option value="master" <?= $profile['degree_level'] === 'master' ? 'selected' : '' ?>>ماجستير</option>
                <option value="phd" <?= $profile['degree_level'] === 'phd' ? 'selected' : '' ?>>دكتوراه</option>
            </select>
        </label>
        <button type="submit" class="btn">حفظ البيانات</button>
    </form>
</div>

<div class="panel">
    <h2>تغيير كلمة المرور</h2>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <label>كلمة المرور الحالية
            <input type="password" name="current_password" required>
        </label>
        <label>كلمة المرور الجديدة
            <input type="password" name="new_password" required minlength="6">
        </label>
        <label>تأكيد كلمة المرور الجديدة
            <input type="password" name="new_password2" required minlength="6">
        </label>
        <button type="submit" class="btn">تغيير كلمة المرور</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
