<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();

$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student','instructor')")->fetchColumn();
$activeStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student','instructor') AND is_active = 1")->fetchColumn();
$proUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student','instructor') AND plan = 'pro'")->fetchColumn();
$pendingPayments = (int)$pdo->query("SELECT COUNT(*) FROM payment_requests WHERE status = 'pending'")->fetchColumn();
$totalConversations = (int)$pdo->query('SELECT COUNT(*) FROM conversations')->fetchColumn();
$totalMessages = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
$totalDocuments = (int)$pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn();
$totalPlans = (int)$pdo->query('SELECT COUNT(*) FROM research_plans')->fetchColumn();
$todayUsage = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE role = 'assistant' AND DATE(created_at) = CURDATE()")->fetchColumn();

$page_title = 'لوحة تحكم المدير';
include __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-grid">
    <div class="dash-card dash-card-static">
        <h3><?= $totalStudents ?></h3>
        <p>إجمالي المستخدمين (طلاب وتدريسيين) — <?= $activeStudents ?> نشط، <?= $proUsers ?> بخطة مدفوعة</p>
    </div>
    <div class="dash-card dash-card-static">
        <h3><?= $totalConversations ?></h3>
        <p>محادثة، بإجمالي <?= $totalMessages ?> رسالة</p>
    </div>
    <div class="dash-card dash-card-static">
        <h3><?= $totalDocuments ?></h3>
        <p>مستند تم تحليله</p>
    </div>
    <div class="dash-card dash-card-static">
        <h3><?= $totalPlans ?></h3>
        <p>خطة بحث مولّدة</p>
    </div>
    <div class="dash-card dash-card-static">
        <h3><?= $todayUsage ?></h3>
        <p>ردّ ذكاء اصطناعي اليوم</p>
    </div>
    <a href="<?= BASE_URL ?>admin/payments.php" class="dash-card">
        <h3><?= $pendingPayments ?></h3>
        <p>طلب دفع بانتظار المراجعة</p>
    </a>
</div>

<div class="panel">
    <p><a href="<?= BASE_URL ?>admin/users.php" class="btn">إدارة المستخدمين</a>
       <a href="<?= BASE_URL ?>admin/payments.php" class="btn btn-outline">طلبات الدفع</a>
       <a href="<?= BASE_URL ?>admin/universities.php" class="btn btn-outline">الجامعات</a>
       <a href="<?= BASE_URL ?>admin/colleges.php" class="btn btn-outline">الكليات</a>
       <a href="<?= BASE_URL ?>admin/specializations.php" class="btn btn-outline">التخصصات</a>
       <a href="<?= BASE_URL ?>admin/report-types.php" class="btn btn-outline">أنواع التقارير</a>
       <a href="<?= BASE_URL ?>admin/reports-stats.php" class="btn btn-outline">إحصائيات التقارير</a>
       <a href="<?= BASE_URL ?>admin/settings.php" class="btn btn-outline">الإعدادات</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
