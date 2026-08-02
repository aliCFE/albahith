<?php
/**
 * رأس الصفحة المشترك (الشريط العلوي والتنقل)
 * يتطلب أن يكون auth.php قد تم تحميله مسبقًا، ويمكن تمرير $page_title
 */
$user  = current_user();
$flash = flash_get();
$appName = defined('APP_NAME') ? APP_NAME : 'باحث';
$logoFile = get_logo_filename();
$metaDescription = isset($meta_description) ? $meta_description
    : 'باحث — منصة ذكاء اصطناعي عربية متخصصة للطلاب الجامعيين والباحثين وأعضاء هيئة التدريس في العراق والوطن العربي: مساعد أكاديمي ذكي، تحليل مستندات PDF وWord، خطط بحث، بحث عن مصادر علمية، وعروض تقديمية.';
$isPublicPage = !is_logged_in();
$currentUrl = (defined('BASE_URL') ? 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL : '') . ltrim($_SERVER['SCRIPT_NAME'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? h($page_title) . ' - ' : '' ?><?= h($appName) ?></title>
<meta name="description" content="<?= h($metaDescription) ?>">
<meta name="robots" content="<?= $isPublicPage ? 'index, follow' : 'noindex, nofollow' ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= isset($page_title) ? h($page_title) . ' - ' : '' ?><?= h($appName) ?>">
<meta property="og:description" content="<?= h($metaDescription) ?>">
<meta property="og:locale" content="ar_IQ">
<?php if ($logoFile): ?>
<meta property="og:image" content="<?= BASE_URL ?>assets/images/<?= h(rawurlencode($logoFile)) ?>">
<link rel="icon" href="<?= BASE_URL ?>assets/images/<?= h(rawurlencode($logoFile)) ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
<?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="<?= BASE_URL ?><?= $user ? role_dashboard_path($user['role']) : 'index.php' ?>">
            <?php if ($logoFile): ?>
                <img class="brand-logo" src="<?= BASE_URL ?>assets/images/<?= h(rawurlencode($logoFile)) ?>" alt="<?= h($appName) ?>">
            <?php else: ?>
                <span class="brand-mark">ب</span>
                <span><?= h($appName) ?></span>
            <?php endif; ?>
        </a>
        <nav class="main-nav<?= !$user ? ' main-nav-guest' : '' ?>" id="mainNav">
            <?php if ($user && $user['role'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>admin/dashboard.php">الرئيسية</a>
                <a href="<?= BASE_URL ?>admin/users.php">المستخدمون</a>
                <a href="<?= BASE_URL ?>admin/payments.php">طلبات الدفع</a>
                <a href="<?= BASE_URL ?>admin/universities.php">الجامعات</a>
                <a href="<?= BASE_URL ?>admin/colleges.php">الكليات</a>
                <a href="<?= BASE_URL ?>admin/specializations.php">التخصصات</a>
                <a href="<?= BASE_URL ?>admin/report-types.php">أنواع التقارير</a>
                <a href="<?= BASE_URL ?>admin/reports-stats.php">إحصائيات التقارير</a>
                <a href="<?= BASE_URL ?>admin/published-papers.php">الأبحاث المنشورة</a>
                <a href="<?= BASE_URL ?>admin/settings.php">الإعدادات</a>
                <a href="<?= BASE_URL ?>logout.php" class="nav-logout">تسجيل الخروج</a>
            <?php elseif ($user && in_array($user['role'], ['student', 'instructor'], true)): ?>
                <a href="<?= BASE_URL ?>student/dashboard.php">الرئيسية</a>
                <a href="<?= BASE_URL ?>student/chat.php">المساعد الأكاديمي</a>
                <a href="<?= BASE_URL ?>student/documents.php">تحليل المستندات</a>
                <a href="<?= BASE_URL ?>student/research-plan.php">خطط البحث</a>
                <a href="<?= BASE_URL ?>student/references.php">المراجع</a>
                <a href="<?= BASE_URL ?>student/writing-assistant.php">الكتابة الأكاديمية</a>
                <a href="<?= BASE_URL ?>student/presentations.php">العروض التقديمية</a>
                <a href="<?= BASE_URL ?>student/reports.php">التقارير والأوراق البحثية</a>
                <a href="<?= BASE_URL ?>student/publish-paper.php">نشر بحث</a>
                <a href="<?= BASE_URL ?>libraries.php">المكتبات</a>
                <a href="<?= BASE_URL ?>student/subscription.php">الاشتراك</a>
                <a href="<?= BASE_URL ?>student/profile.php">حسابي</a>
                <a href="<?= BASE_URL ?>logout.php" class="nav-logout">تسجيل الخروج</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>index.php">الرئيسية</a>
                <a href="<?= BASE_URL ?>index.php#features">الخدمات</a>
                <a href="<?= BASE_URL ?>libraries.php">المكتبات</a>
                <a href="<?= BASE_URL ?>register.php">إنشاء حساب</a>
                <a href="<?= BASE_URL ?>login.php" class="nav-logout">تسجيل الدخول</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container page">
    <?php if (isset($page_title)): ?>
        <h1 class="page-title"><?= h($page_title) ?></h1>
    <?php endif; ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>
