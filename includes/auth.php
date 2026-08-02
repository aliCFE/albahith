<?php
/**
 * إدارة الجلسات والصلاحيات
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * الصفحات المحمية (بعد تسجيل الدخول) تعرض بيانات تخص المستخدم نفسه،
 * فلازم نمنع أي جهة (متصفح، أو أي طبقة CDN/بروكسي أمام الاستضافة) من تخزين
 * نسخة منها وعرضها لزائر آخر — لهذا نجبر عدم التخزين المؤقت صراحة بكل صفحة محمية
 */
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_logged_in() {
    return !empty($_SESSION['user']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_role($roles) {
    require_login();
    if (!in_array(current_user()['role'], (array)$roles, true)) {
        redirect('login.php');
    }
}

function attempt_login(PDO $pdo, $email, $password) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        session_regenerate_id(true);
        return true;
    }
    return false;
}

/**
 * تسجيل طالب أو تدريسي جديد. يرجع مصفوفة الخطأ (['error' => ...]) أو معرف المستخدم الجديد عند النجاح
 */
function register_student(PDO $pdo, array $data) {
    $email = trim($data['email']);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['error' => 'هذا البريد الإلكتروني مسجّل مسبقًا.'];
    }

    $role = ($data['role'] ?? 'student') === 'instructor' ? 'instructor' : 'student';

    $stmt = $pdo->prepare('INSERT INTO users
        (name, email, password_hash, role, university, college, specialization, degree_level, usage_reset_at)
        VALUES (:name, :email, :password_hash, :role, :university, :college, :specialization, :degree_level, :usage_reset_at)');
    $stmt->execute([
        'name'           => trim($data['name']),
        'email'          => $email,
        'password_hash'  => password_hash($data['password'], PASSWORD_DEFAULT),
        'role'           => $role,
        'university'     => $data['university'] !== '' ? $data['university'] : null,
        'college'        => $data['college'] !== '' ? $data['college'] : null,
        'specialization' => $data['specialization'] !== '' ? $data['specialization'] : null,
        'degree_level'   => $data['degree_level'],
        'usage_reset_at' => date('Y-m-d'),
    ]);

    return (int)$pdo->lastInsertId();
}

function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
