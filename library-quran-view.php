<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/library_api.php';

$surahs = quran_surah_list();
$suraNum = max(1, min(114, (int)($_GET['sura'] ?? 1)));
$currentSurah = null;
foreach ($surahs as $s) {
    if ((int)$s[0] === $suraNum) { $currentSurah = $s; break; }
}
if (!$currentSurah) {
    flash_set('السورة غير موجودة.', 'error');
    redirect('library-quran.php');
}
[, $nameAr, $nameEn, $ayahCount, $revelation] = $currentSurah;

$translationsResult = quranenc_fetch_translations('en');
$translationKey = trim($_GET['translation'] ?? '');
$validKeys = array_column($translationsResult['items'] ?? [], 'key');
if ($translationKey !== '' && !in_array($translationKey, $validKeys, true)) {
    $translationKey = '';
}

$ayatResult = quranenc_fetch_sura($translationKey !== '' ? $translationKey : 'english_saheeh', $suraNum);
$showBismillah = $suraNum !== 1 && $suraNum !== 9;

$page_title = $nameAr . ' - القرآن الكريم';
$extra_head = '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:wght@400;700&display=swap" rel="stylesheet">';
include __DIR__ . '/includes/header.php';
?>
<div class="lib-hero">
    <div class="lib-breadcrumb"><a href="<?= BASE_URL ?>libraries.php">المكتبات</a> ← <a href="<?= BASE_URL ?>library-quran.php">القرآن الكريم</a> ← <?= h($nameAr) ?></div>
    <h1>سورة <?= h($nameAr) ?> <span class="hint">(<?= h($nameEn) ?>)</span></h1>
    <p><?= (int)$ayahCount ?> آية — <?= $revelation === 'meccan' ? 'مكية' : 'مدنية' ?></p>
</div>

<div class="panel">
    <form method="get" class="quran-select-bar">
        <input type="hidden" name="sura" value="<?= (int)$suraNum ?>">
        <label>🌐 عرض ترجمة (اختياري)</label>
        <select name="translation" onchange="this.form.requestSubmit()">
            <option value="">بدون ترجمة (العربية فقط)</option>
            <?php foreach (($translationsResult['items'] ?? []) as $t): ?>
                <option value="<?= h($t['key']) ?>" <?= $translationKey === $t['key'] ? 'selected' : '' ?>><?= h($t['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-outline btn-xs">عرض</button></noscript>
    </form>
</div>

<?php if (!$ayatResult['ok'] || empty($ayatResult['ayat'])): ?>
    <div class="panel">
        <div class="alert alert-error">تعذر تحميل آيات هذه السورة حاليًا. حاول مرة أخرى بعد قليل.</div>
    </div>
<?php else: ?>
    <div class="quran-reader">
        <?php if ($showBismillah): ?>
            <p class="quran-bismillah">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</p>
        <?php endif; ?>
        <div class="quran-ayat" dir="rtl">
        <?php foreach ($ayatResult['ayat'] as $ayah): ?>
            <div class="quran-ayah-block" id="ayah-<?= h($ayah['aya'] ?? '') ?>">
                <p class="quran-ayah"><?= h($ayah['arabic_text'] ?? '') ?><span class="quran-ayah-num"><?= h($ayah['aya'] ?? '') ?></span></p>
                <?php if ($translationKey !== '' && !empty($ayah['translation'])): ?>
                    <p class="quran-ayah-translation" dir="ltr"><?= h(strip_tags($ayah['translation'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="pagination">
        <?php if ($suraNum > 1): ?>
            <a href="?sura=<?= $suraNum - 1 ?><?= $translationKey !== '' ? '&translation=' . h($translationKey) : '' ?>" class="btn btn-outline btn-xs">→ السورة السابقة</a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($suraNum < 114): ?>
            <a href="?sura=<?= $suraNum + 1 ?><?= $translationKey !== '' ? '&translation=' . h($translationKey) : '' ?>" class="btn btn-outline btn-xs">السورة التالية ←</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<script src="<?= asset_url('assets/js/library.js') ?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
