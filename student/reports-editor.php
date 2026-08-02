<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reports.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM report_documents WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$doc = $stmt->fetch();

if (!$doc) {
    flash_set('المستند غير موجود.', 'error');
    redirect('student/reports.php');
}

$stmt = $pdo->prepare("SELECT * FROM report_sections WHERE document_id = ? AND status != 'disabled' ORDER BY sort_order ASC");
$stmt->execute([$id]);
$sections = $stmt->fetchAll();

if (empty($sections)) {
    redirect('student/reports-outline.php?id=' . $id);
}

$activeSectionId = (int)($_GET['section'] ?? 0);
$activeSection = null;
foreach ($sections as $s) {
    if ($s['id'] === $activeSectionId) { $activeSection = $s; break; }
}
if (!$activeSection) {
    $activeSection = $sections[0];
    $activeSectionId = $activeSection['id'];
}

$pendingIds = array_values(array_map(fn($s) => (int)$s['id'], array_filter($sections, fn($s) => $s['status'] === 'pending' || $s['status'] === 'failed')));
$remaining = usage_remaining($pdo, $user);

$statusIcons = [
    'pending'    => '○',
    'generating' => '◐',
    'generated'  => '✓',
    'edited'     => '✎',
    'failed'     => '✕',
];

$page_title = $doc['title'];
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <p>
        <a href="<?= BASE_URL ?>student/reports.php">مستنداتي</a> ←
        <a href="<?= BASE_URL ?>student/reports-outline.php?id=<?= (int)$id ?>">المخطط</a>
    </p>
    <div class="report-toolbar">
        <h2><?= h($doc['title']) ?></h2>
        <div class="report-toolbar-actions">
            <span class="hint"><?= (int)$doc['total_words'] ?> كلمة</span>
            <a href="<?= BASE_URL ?>student/reports-references.php?id=<?= (int)$id ?>" class="btn btn-outline btn-xs">المراجع</a>
            <a href="<?= BASE_URL ?>student/reports-files.php?id=<?= (int)$id ?>" class="btn btn-outline btn-xs">المصادر</a>
            <a href="<?= BASE_URL ?>student/reports-export-pdf.php?id=<?= (int)$id ?>" target="_blank" class="btn btn-outline btn-xs">⬇ PDF</a>
            <a href="<?= BASE_URL ?>student/reports-export-word.php?id=<?= (int)$id ?>" class="btn btn-xs">⬇ Word</a>
        </div>
    </div>
</div>

<?php if (!empty($pendingIds)): ?>
<div class="panel" id="generateBar">
    <p class="hint">لسا عندك <strong id="pendingCount"><?= count($pendingIds) ?></strong> قسم ما تولّد.</p>
    <?php if ($remaining <= 0): ?>
        <div class="alert alert-error">وصلت للحد الشهري من طلبات الذكاء الاصطناعي.</div>
    <?php else: ?>
        <button type="button" class="btn" id="generateAllBtn">✨ توليد كل الأقسام المتبقية</button>
        <div id="generateProgress" class="generate-progress"></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="report-layout">
    <aside class="report-sidebar">
        <ul class="report-section-list">
            <?php foreach ($sections as $s): ?>
                <li>
                    <a href="?id=<?= (int)$id ?>&section=<?= (int)$s['id'] ?>" class="<?= $s['id'] === $activeSectionId ? 'active' : '' ?>">
                        <span class="report-status-icon status-<?= h($s['status']) ?>"><?= h($statusIcons[$s['status']] ?? '•') ?></span>
                        <?= h($s['section_title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <section class="report-editor-main">
        <div class="report-editor-header">
            <h3><?= h($activeSection['section_title']) ?></h3>
            <span class="hint">الهدف: <?= (int)$activeSection['target_word_count'] ?> كلمة — الحالي: <span id="liveWordCount"><?= report_word_count($activeSection['content'] ?? '') ?></span></span>
        </div>

        <?php if ($activeSection['status'] === 'pending'): ?>
            <div class="panel">
                <p class="hint">هذا القسم لسا ما تولّد. اضغط الزر بالأعلى "توليد كل الأقسام المتبقية"، أو ولّده لحاله:</p>
                <?php if ($remaining > 0): ?>
                    <button type="button" class="btn" id="generateSingleBtn" data-section-id="<?= (int)$activeSection['id'] ?>">توليد هذا القسم</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="editor-toolbar">
                <button type="button" data-cmd="bold" title="عريض"><b>B</b></button>
                <button type="button" data-cmd="italic" title="مائل"><i>I</i></button>
                <button type="button" data-cmd="insertUnorderedList" title="قائمة نقطية">• قائمة</button>
                <button type="button" data-cmd="insertOrderedList" title="قائمة رقمية">1. قائمة</button>
                <span class="editor-toolbar-sep"></span>
                <select id="aiToolSelect">
                    <option value="">✨ أدوات الذكاء الاصطناعي (حدّد نص أولًا)</option>
                    <option value="rewrite">إعادة صياغة</option>
                    <option value="academic">جعل النص أكاديميًا</option>
                    <option value="shorten">اختصار</option>
                    <option value="expand">توسيع</option>
                    <option value="simplify">تبسيط</option>
                    <option value="proofread">تصحيح لغوي</option>
                </select>
                <span id="autosaveStatus" class="hint"></span>
            </div>
            <div id="sectionEditor" class="section-editor" contenteditable="true" dir="rtl"><?= $activeSection['content'] ?? '' ?></div>
        <?php endif; ?>
    </section>
</div>

<input type="hidden" id="documentId" value="<?= (int)$id ?>">
<input type="hidden" id="activeSectionId" value="<?= (int)$activeSectionId ?>">
<input type="hidden" id="reportCsrfToken" value="<?= h(csrf_token()) ?>">
<input type="hidden" id="pendingSectionIds" value="<?= h(implode(',', $pendingIds)) ?>">

<script src="<?= asset_url('assets/js/reports-editor.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
