<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/library_api.php';

$surahs = quran_surah_list();

$page_title = 'القرآن الكريم';
$meta_description = 'تصفّح سور القرآن الكريم الـ114 بالنص العربي، أو ابحث عن سورة أو آية مباشرة.';
include __DIR__ . '/includes/header.php';
?>
<div class="lib-hero">
    <div class="lib-breadcrumb"><a href="<?= BASE_URL ?>libraries.php">المكتبات</a> ← القرآن الكريم</div>
    <div class="lib-hero-icon">📖</div>
    <h1>القرآن الكريم</h1>
    <p>اختر سورة لعرض آياتها، أو ابحث عن اسم سورة، نص آية، أو اكتب رقم السورة:الآية مباشرة (مثل 2:255)</p>
</div>

<div class="panel">
    <input type="search" id="quranSearchInput" class="lib-search-input" placeholder="🔍 ابحث عن سورة، أو نص آية، أو رقم سورة:آية...">
    <p id="quranSearchStatus" class="hint"></p>
</div>

<div id="quranSearchResults"></div>

<div id="surahGridWrap">
    <div class="surah-grid">
        <?php foreach ($surahs as $s): [$num, $nameAr, $nameEn, $ayahCount, $revelation] = $s; ?>
            <a href="<?= BASE_URL ?>library-quran-view.php?sura=<?= (int)$num ?>" class="surah-card" data-search="<?= h(mb_strtolower($nameAr . ' ' . $nameEn . ' ' . $num)) ?>">
                <span class="surah-num"><?= (int)$num ?></span>
                <span class="surah-info">
                    <span class="surah-name-ar"><?= h($nameAr) ?></span>
                    <span class="surah-name-en"><?= h($nameEn) ?></span>
                    <span class="surah-meta">
                        <span class="surah-pill"><?= (int)$ayahCount ?> آية</span>
                        <span class="surah-pill place"><?= $revelation === 'meccan' ? '🕋 مكية' : '🕌 مدنية' ?></span>
                    </span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<script type="application/json" id="surahData"><?= json_encode(array_map(function ($s) {
    return ['num' => $s[0], 'name_ar' => $s[1], 'name_en' => $s[2], 'ayah_count' => $s[3], 'revelation' => $s[4]];
}, $surahs), JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= asset_url('assets/js/library.js') ?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
