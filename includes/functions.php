<?php
/**
 * دوال مساعدة عامة
 */

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * يرجع رابط ملف Static (CSS/JS) مع رقم إصدار مبني على وقت آخر تعديل للملف،
 * حتى يجبر متصفح الزائر على تحميل النسخة الجديدة تلقائيًا بعد أي تحديث
 * بدل ما يبقى عالق بنسخة قديمة محفوظة بالكاش
 */
function asset_url($relativePath) {
    $fsPath = __DIR__ . '/../' . $relativePath;
    $version = @filemtime($fsPath) ?: time();
    return BASE_URL . $relativePath . '?v=' . $version;
}

/**
 * تقدير تقريبي لعدد صفحات ملف PDF بقراءة بنيته الخام (بدون أي مكتبة خارجية)،
 * بالبحث عن أعلى قيمة /Count بأشجار الصفحات (Pages tree) — أسلوب شائع وسريع
 * لتقدير عدد الصفحات دون تحليل PDF كامل. يرجع null إذا تعذّر التقدير.
 * الهدف: رفض الملفات الطويلة جدًا *قبل* إرسالها للذكاء الاصطناعي (يتجاوز حدود
 * السياق للنموذج) بدل ما يفشل الطلب برسالة خطأ إنجليزية غير مفهومة للمستخدم
 */
function pdf_estimate_page_count($filePath) {
    $raw = @file_get_contents($filePath, false, null, 0, 5 * 1024 * 1024);
    if ($raw === false) {
        return null;
    }
    if (preg_match_all('/\/Count\s+(\d+)/', $raw, $matches)) {
        $counts = array_map('intval', $matches[1]);
        if (!empty($counts)) {
            return max($counts);
        }
    }
    // نسخة احتياطية: عدّ كائنات الصفحات المفردة (/Type /Page وليس /Type /Pages)
    if (preg_match_all('/\/Type\s*\/Page(?!s)\b/', $raw, $matches)) {
        return count($matches[0]);
    }
    return null;
}

/**
 * يبحث عن ملف الشعار (logo.*) في assets/images/ ويرجع اسم الملف أو null
 */
function get_logo_filename() {
    $dir = __DIR__ . '/../assets/images';
    foreach (glob($dir . '/logo.{png,jpg,jpeg,webp,PNG,JPG,JPEG,WEBP}', GLOB_BRACE) as $file) {
        return basename($file);
    }
    return null;
}

function redirect($path) {
    $base = defined('BASE_URL') ? BASE_URL : '/';
    header('Location: ' . $base . $path);
    exit;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        die('طلب غير صالح (CSRF). الرجاء العودة وإعادة المحاولة.');
    }
}

function flash_set($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * الطالب والتدريسي يشتركان بنفس صفحات student/*.php، فقط الأدمن له مجلد منفصل
 */
function role_dashboard_path($role) {
    return $role === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php';
}

function role_label($role) {
    $labels = ['student' => 'طالب', 'instructor' => 'تدريسي', 'admin' => 'مدير'];
    return $labels[$role] ?? $role;
}

function degree_level_label($level) {
    $labels = [
        'bachelor' => 'بكالوريوس',
        'master'   => 'ماجستير',
        'phd'      => 'دكتوراه',
    ];
    return $labels[$level] ?? $level;
}

function get_setting(PDO $pdo, $key, $default = null) {
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $default;
}

function set_setting(PDO $pdo, $key, $value) {
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function monthly_free_limit(PDO $pdo) {
    return (int)get_setting($pdo, 'monthly_free_limit', 50);
}

function pro_monthly_limit(PDO $pdo) {
    return (int)get_setting($pdo, 'pro_monthly_limit', 300);
}

function plan_label($plan) {
    return $plan === 'pro' ? 'مدفوعة' : 'مجانية';
}

/**
 * قائمة أسماء الجامعات التي أضافها الأدمن، مرتبة أبجديًا — تُستخدم بقائمة الاختيار بالتسجيل والملف الشخصي
 */
function get_all_universities(PDO $pdo) {
    return $pdo->query('SELECT university_name FROM university_plans ORDER BY university_name ASC')->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * قائمة أسماء الكليات التي أضافها الأدمن، مرتبة أبجديًا
 */
function get_all_colleges(PDO $pdo) {
    return $pdo->query('SELECT name FROM colleges ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * قائمة أسماء التخصصات التي أضافها الأدمن، مرتبة أبجديًا
 */
function get_all_specializations(PDO $pdo) {
    return $pdo->query('SELECT name FROM specializations ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * يحدد قيمة قائمة الاختيار (اسم مطابق أو "__other__") وقيمة الحقل البديل،
 * انطلاقًا من قيمة محفوظة سابقًا (مثلاً عند تعديل الملف الشخصي أو بعد خطأ بالنموذج)
 */
function resolve_choice_selection($currentValue, array $options) {
    $currentValue = (string)$currentValue;
    if ($currentValue !== '' && in_array($currentValue, $options, true)) {
        return ['select' => $currentValue, 'other' => ''];
    }
    return ['select' => $currentValue !== '' ? '__other__' : '', 'other' => $currentValue];
}

/**
 * الحد الشهري الفعلي لمستخدم معيّن، بحسب خطته الشخصية (مجانية/مدفوعة) فقط
 */
function current_plan_limit(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('SELECT plan FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return ($row['plan'] ?? 'free') === 'pro' ? pro_monthly_limit($pdo) : monthly_free_limit($pdo);
}

/**
 * يصفّر عداد الاستخدام الشهري لو دخلنا شهرًا جديدًا منذ آخر تصفير
 */
function ensure_usage_fresh(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('SELECT usage_reset_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $resetAt = $stmt->fetchColumn();
    if ($resetAt === false) return;
    $resetMonth = substr($resetAt, 0, 7);
    $currentMonth = date('Y-m');
    if ($resetMonth !== $currentMonth) {
        $update = $pdo->prepare('UPDATE users SET ai_requests_used = 0, usage_reset_at = ? WHERE id = ?');
        $update->execute([date('Y-m-d'), $userId]);
    }
}

function usage_remaining(PDO $pdo, array $user) {
    ensure_usage_fresh($pdo, $user['id']);
    $stmt = $pdo->prepare('SELECT ai_requests_used FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $used = (int)$stmt->fetchColumn();
    return max(0, current_plan_limit($pdo, $user['id']) - $used);
}

function record_ai_usage(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('UPDATE users SET ai_requests_used = ai_requests_used + 1 WHERE id = ?');
    $stmt->execute([$userId]);
}
