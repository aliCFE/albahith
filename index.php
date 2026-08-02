<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(role_dashboard_path(current_user()['role']));
}

$page_title = null;
$meta_description = 'باحث (Baheth) — منصة الذكاء الاصطناعي الأكاديمية الأولى للطلاب الجامعيين والباحثين وأعضاء هيئة التدريس في العراق والعالم العربي. مساعد أكاديمي ذكي، تحليل PDF وWord، خطط بحث، بحث عن مصادر علمية موثوقة، وعروض تقديمية تلقائية.';
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <?php $logoFile = get_logo_filename(); if ($logoFile): ?>
        <div class="hero-logo-wrap">
            <img class="hero-logo" src="<?= BASE_URL ?>assets/images/<?= h(rawurlencode($logoFile)) ?>" alt="باحث">
        </div>
    <?php endif; ?>
    <span class="hero-eyebrow">منصة عربية بالكامل</span>
    <h1>باحث — مساعدك الأكاديمي الذكي</h1>
    <p class="hero-sub">منصة ذكاء اصطناعي متخصصة للطلاب الجامعيين والباحثين وأعضاء هيئة التدريس، تفهم احتياجات الجامعات العراقية والعربية بدل أن تكون مجرد روبوت محادثة عام.</p>
    <div class="hero-actions">
        <a href="<?= BASE_URL ?>register.php" class="btn btn-lg">ابدأ مجانًا</a>
        <a href="<?= BASE_URL ?>login.php" class="btn btn-outline btn-lg">تسجيل الدخول</a>
    </div>
</section>

<section class="features" id="features">
    <h2>كل شي يحتاجه بحثك بمكان واحد</h2>
    <p class="features-sub">من فكرة البحث إلى العرض التقديمي النهائي</p>
    <div class="features-grid">
        <div class="feature-card">
            <span class="feature-icon">💬</span>
            <h3>مساعد أكاديمي ذكي</h3>
            <p>يفهم تخصصك وكليتك ومرحلتك الدراسية، ويجيب بأسلوب أكاديمي دقيق باللغة العربية.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">📄</span>
            <h3>تحليل المستندات</h3>
            <p>ارفع ملف PDF أو Word واحصل على تلخيص فوري، واسأل أسئلة متابعة عن نفس الملف.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">🧭</span>
            <h3>مولّد خطط البحث</h3>
            <p>أدخل موضوعك وتخصصك ومتطلبات جامعتك، واحصل على خطة بحث منظمة قابلة للتعديل.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">🔎</span>
            <h3>بحث عن مصادر حقيقية</h3>
            <p>نتائج فعلية من قواعد بيانات علمية عالمية، وليست مصادر مختلقة بالذكاء الاصطناعي.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">📚</span>
            <h3>تنظيم المراجع</h3>
            <p>احفظ مصادرك واحصل على توثيق تلقائي بأنماط APA وMLA وChicago.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">✍️</span>
            <h3>مساعد الكتابة الأكاديمية</h3>
            <p>مسودة جاهزة لأي قسم من بحثك: مقدمة، إطار نظري، منهجية، خاتمة، وأكثر.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">📊</span>
            <h3>عروض تقديمية تلقائية</h3>
            <p>حوّل بحثك إلى عرض PowerPoint جاهز للتنزيل والتعديل بضغطة واحدة.</p>
        </div>
    </div>
    <div class="features-note-wrap">
        <p class="features-note">قريبًا: تخصيص المنصة لكل جامعة بتعليماتها الخاصة، وتوسّع لبقية الجامعات العربية</p>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
