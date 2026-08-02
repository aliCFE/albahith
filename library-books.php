<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/library_api.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$result = islamhouse_fetch_books($page, 25);

$page_title = 'الكتب الإسلامية';
$meta_description = 'مكتبة كتب إسلامية مجانية (عقيدة، فقه، سيرة، وغيرها) قابلة للتحميل بصيغة PDF.';
include __DIR__ . '/includes/header.php';
?>
<div class="lib-hero">
    <div class="lib-breadcrumb"><a href="<?= BASE_URL ?>libraries.php">المكتبات</a> ← الكتب الإسلامية</div>
    <div class="lib-hero-icon">📗</div>
    <h1>الكتب الإسلامية</h1>
    <?php if (!empty($result['pagination']['total_items'])): ?>
        <p><?= number_format((int)$result['pagination']['total_items']) ?> كتاب متاح للتحميل مجانًا</p>
    <?php endif; ?>
</div>

<div class="panel">
    <input type="search" id="bookSearchInput" class="lib-search-input" placeholder="🔍 ابحث عن كتاب أو مؤلف...">
    <p id="bookSearchStatus" class="hint"></p>
</div>

<div class="panel">
    <?php if (!$result['ok']): ?>
        <div class="alert alert-error">تعذر الوصول لمكتبة الكتب حاليًا. حاول مرة أخرى بعد قليل.</div>
    <?php elseif (empty($result['items'])): ?>
        <p class="hint">لا توجد كتب بهذه الصفحة.</p>
    <?php else: ?>
        <div id="booksGrid">
        <div class="books-grid">
        <?php foreach ($result['items'] as $book): ?>
            <div class="book-card">
                <div class="book-card-title">
                    <span class="icon">📗</span>
                    <h3><?= h($book['title'] ?? '') ?></h3>
                </div>
                <?php
                    $authors = [];
                    foreach (($book['prepared_by'] ?? []) as $p) {
                        if (!empty($p['title'])) $authors[] = $p['title'];
                    }
                ?>
                <?php if (!empty($authors)): ?>
                    <div class="book-card-author"><?= h(implode('، ', $authors)) ?></div>
                <?php endif; ?>
                <?php if (!empty($book['description'])): ?>
                    <p class="book-card-desc"><?= h($book['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($book['attachments'])): ?>
                <div class="book-card-downloads">
                    <?php foreach ($book['attachments'] as $att): ?>
                        <?php if (!empty($att['url'])): ?>
                            <a href="<?= h($att['url']) ?>" class="btn btn-outline btn-xs" target="_blank" rel="noopener">
                                ⬇ <?= h($att['extension_type'] ?? 'PDF') ?><?= !empty($att['size']) ? ' (' . h($att['size']) . ')' : '' ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        </div>

        <div class="pagination" id="booksPagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="btn btn-outline btn-xs">→ الصفحة السابقة</a>
            <?php else: ?><span></span><?php endif; ?>
            <?php if (!empty($result['pagination']['next'])): ?>
                <a href="?page=<?= $page + 1 ?>" class="btn btn-outline btn-xs">الصفحة التالية ←</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<script src="<?= asset_url('assets/js/library.js') ?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
