<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(role_dashboard_path(current_user()['role']));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'الرجاء إدخال البريد الإلكتروني وكلمة المرور.';
    } else {
        $pdo = get_db();
        if (attempt_login($pdo, $email, $password)) {
            redirect(role_dashboard_path(current_user()['role']));
        } else {
            $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة، أو أن الحساب موقوف.';
        }
    }
}

$page_title = 'تسجيل الدخول';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-box">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label>البريد الإلكتروني
            <input type="email" name="email" required autofocus>
        </label>
        <label>كلمة المرور
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn">دخول</button>
    </form>
    <p class="auth-alt">ليس لديك حساب؟ <a href="<?= BASE_URL ?>register.php">إنشاء حساب جديد</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
