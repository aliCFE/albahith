<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = get_db();

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare(
        "SELECT pp.*, u.name AS author_name
         FROM published_papers pp JOIN users u ON u.id = pp.user_id
         WHERE pp.status = 'approved' AND (pp.title LIKE ? OR pp.description LIKE ?)
         ORDER BY pp.reviewed_at DESC"
    );
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query(
        "SELECT pp.*, u.name AS author_name
         FROM published_papers pp JOIN users u ON u.id = pp.user_id
         WHERE pp.status = 'approved'
         ORDER BY pp.reviewed_at DESC"
    );
}
$papers = $stmt->fetchAll();

$page_title = 'مكتبة الأبحاث';
$meta_description = 'مكتبة الأبحاث والدراسات الأكاديمية المنشورة من طلاب وباحثي منصة باحث — تصفّح وتحميل أبحاث حقيقية بصيغة PDF مجانًا.';
include __DIR__ . '/includes/header.php';
?>
<div class="panel">
    <p class="hint">أبحاث ودراسات أكاديمية نشرها مستخدمو منصة باحث بعد مراجعتها من الإدارة. يمكنك تصفّحها وتحميلها مجانًا.</p>
    <form method="get" class="auth-form">
        <label>ابحث بالعنوان أو الوصف
            <input type="text" name="q" value="<?= h($search) ?>" placeholder="مثال: الذكاء الاصطناعي، إدارة الأعمال...">
        </label>
        <button type="submit" class="btn">بحث</button>
    </form>
</div>

<div class="panel">
    <h2><?= count($papers) ?> بحث منشور</h2>
    <?php if (empty($papers)): ?>
        <p class="hint">لا توجد أبحاث منشورة حاليًا<?= $search !== '' ? ' تطابق بحثك' : '' ?>.</p>
    <?php else: ?>
        <div class="source-list">
        <?php foreach ($papers as $p): ?>
            <div class="source-card">
                <h3><?= h($p['title']) ?></h3>
                <p class="hint">بقلم: <?= h($p['author_name']) ?> — <?= h(substr($p['reviewed_at'] ?? $p['created_at'], 0, 10)) ?></p>
                <?php if (!empty($p['description'])): ?>
                    <p><?= h($p['description']) ?></p>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>research-library-download.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline btn-xs" target="_blank">⬇ تحميل PDF</a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
