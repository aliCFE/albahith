<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/citation.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$query = trim($_GET['q'] ?? '');
$results = [];
$searchError = null;

if ($query !== '') {
    $search = crossref_search($query);
    if ($search['ok']) {
        $results = $search['results'];
        if (empty($results)) {
            $searchError = 'لم يتم العثور على مصادر مطابقة. جرّب كلمات مفتاحية مختلفة.';
        }
    } else {
        $searchError = $search['error'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare('INSERT INTO sources (user_id, title, authors, pub_year, container_title, doi, url, source_type)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $user['id'],
        trim($_POST['title'] ?? ''),
        trim($_POST['authors'] ?? ''),
        trim($_POST['pub_year'] ?? ''),
        trim($_POST['container_title'] ?? ''),
        trim($_POST['doi'] ?? ''),
        trim($_POST['url'] ?? ''),
        in_array($_POST['source_type'] ?? '', ['journal', 'book', 'conference', 'website', 'other'], true) ? $_POST['source_type'] : 'journal',
    ]);
    flash_set('تم حفظ المصدر في مراجعك.');
    redirect('student/sources-search.php?q=' . urlencode($query));
}

$page_title = 'البحث عن مصادر علمية';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h2>ابحث عن مصادر علمية حقيقية (عبر CrossRef)</h2>
    <p class="hint">نتائج فعلية من قاعدة بيانات CrossRef العالمية (ملايين الأبحاث المفهرسة)، وليست مولّدة بالذكاء الاصطناعي — لتفادي أي مصادر وهمية.</p>
    <form method="get" class="auth-form">
        <label>كلمات البحث (بالإنكليزية غالبًا أدق للنتائج)
            <input type="text" name="q" value="<?= h($query) ?>" placeholder="مثال: artificial intelligence in higher education" required>
        </label>
        <button type="submit" class="btn">بحث</button>
    </form>
</div>

<?php if ($searchError): ?>
    <div class="alert alert-error"><?= h($searchError) ?></div>
<?php endif; ?>

<?php if (!empty($results)): ?>
<div class="panel">
    <h2>النتائج</h2>
    <?php foreach ($results as $r): ?>
        <div class="source-card">
            <h3><?= h($r['title']) ?></h3>
            <p class="hint">
                <?= h($r['authors'] ?: 'مؤلف غير معروف') ?>
                <?php if ($r['pub_year']): ?> — <?= h($r['pub_year']) ?><?php endif; ?>
                <?php if ($r['container_title']): ?> — <?= h($r['container_title']) ?><?php endif; ?>
            </p>
            <?php if ($r['url']): ?><p><a href="<?= h($r['url']) ?>" target="_blank" rel="noopener"><?= h($r['url']) ?></a></p><?php endif; ?>
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="title" value="<?= h($r['title']) ?>">
                <input type="hidden" name="authors" value="<?= h($r['authors']) ?>">
                <input type="hidden" name="pub_year" value="<?= h($r['pub_year']) ?>">
                <input type="hidden" name="container_title" value="<?= h($r['container_title']) ?>">
                <input type="hidden" name="doi" value="<?= h($r['doi']) ?>">
                <input type="hidden" name="url" value="<?= h($r['url']) ?>">
                <input type="hidden" name="source_type" value="<?= h($r['source_type']) ?>">
                <button type="submit" class="btn">+ حفظ في مراجعي</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<p><a href="<?= BASE_URL ?>student/references.php">عرض مراجعي المحفوظة ←</a></p>
<?php include __DIR__ . '/../includes/footer.php'; ?>
