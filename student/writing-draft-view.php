<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$sectionLabels = [
    'abstract'     => 'ملخص / مستخلص البحث',
    'introduction' => 'المقدمة',
    'literature'   => 'الإطار النظري ومراجعة الدراسات السابقة',
    'methodology'  => 'منهجية البحث',
    'discussion'   => 'عرض ومناقشة النتائج',
    'conclusion'   => 'الخاتمة والتوصيات',
];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM writing_drafts WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$draft = $stmt->fetch();

if (!$draft) {
    flash_set('المسودة غير موجودة.', 'error');
    redirect('student/writing-assistant.php');
}

$page_title = $sectionLabels[$draft['section_type']] ?? $draft['section_type'];
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <p><a href="<?= BASE_URL ?>student/writing-assistant.php">→ العودة لمساعد الكتابة</a></p>
    <p class="hint">الموضوع: <?= h($draft['topic']) ?></p>
    <div class="ai-output"><?= nl2br(h($draft['generated_content'])) ?></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
