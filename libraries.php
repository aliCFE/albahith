<?php
require_once __DIR__ . '/includes/auth.php';

$page_title = 'المكتبات';
$meta_description = 'مكتبات باحث: القرآن الكريم بترجماته، مكتبة الكتب الإسلامية، ومكتبة الأبحاث الأكاديمية المنشورة — تصفّح مجاني بدون تسجيل.';
include __DIR__ . '/includes/header.php';
?>
<div class="lib-hero">
    <div class="lib-hero-icon">📚</div>
    <h1>المكتبات</h1>
    <p>مجموعة مكتبات مجانية متاحة للجميع، بدون تسجيل دخول.</p>
</div>

<div class="lib-cards">
    <a href="<?= BASE_URL ?>library-quran.php" class="lib-card">
        <div class="lib-card-icon">📖</div>
        <h3>القرآن الكريم</h3>
        <p>تصفّح السور الـ114 بالنص العربي، مع إمكانية عرض ترجمة بلغة أخرى لكل سورة.</p>
        <span class="lib-card-arrow">تصفّح المصحف ←</span>
    </a>
    <a href="<?= BASE_URL ?>library-books.php" class="lib-card">
        <div class="lib-card-icon">📗</div>
        <h3>الكتب الإسلامية</h3>
        <p>مكتبة كتب إسلامية (عقيدة، فقه، سيرة، شروح أحاديث وغيرها) مع تحميل مباشر بصيغة PDF.</p>
        <span class="lib-card-arrow">تصفّح الكتب ←</span>
    </a>
    <a href="<?= BASE_URL ?>research-library.php" class="lib-card">
        <div class="lib-card-icon">🎓</div>
        <h3>مكتبة الأبحاث</h3>
        <p>أبحاث ودراسات أكاديمية نشرها مستخدمو منصة باحث بعد مراجعتها من الإدارة.</p>
        <span class="lib-card-arrow">تصفّح الأبحاث ←</span>
    </a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
