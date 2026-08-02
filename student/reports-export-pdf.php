<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/citation.php';
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

$stmt = $pdo->prepare('SELECT * FROM report_references WHERE document_id = ? ORDER BY created_at ASC');
$stmt->execute([$id]);
$references = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?= h($doc['title']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
        direction: rtl;
        color: #1a1a1a;
        line-height: 1.9;
        max-width: 800px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    .print-bar {
        text-align: center;
        margin-bottom: 24px;
    }
    .print-bar button {
        font-family: inherit;
        font-size: 1rem;
        padding: 10px 24px;
        border-radius: 8px;
        border: none;
        background: #2f6f4f;
        color: #fff;
        cursor: pointer;
    }
    .cover-page {
        min-height: 90vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        page-break-after: always;
    }
    .cover-page .university { font-size: 1.3rem; font-weight: 700; margin-bottom: 6px; }
    .cover-page .college { font-size: 1.05rem; color: #444; margin-bottom: 60px; }
    .cover-page .title { font-size: 2rem; font-weight: 900; margin: 20px 0; }
    .cover-page .year { font-size: 1.1rem; color: #444; margin-top: 60px; }
    .toc { page-break-after: always; }
    .toc h2 { font-size: 1.5rem; margin-bottom: 20px; }
    .toc ol { padding-inline-start: 24px; }
    .toc li { margin-bottom: 10px; font-size: 1.05rem; }
    .section { page-break-before: always; }
    .section h2 {
        font-size: 1.4rem;
        font-weight: 700;
        border-bottom: 2px solid #2f6f4f;
        padding-bottom: 8px;
        margin-bottom: 20px;
    }
    .section .content p { margin: 0 0 14px; text-align: justify; }
    .section .content ul, .section .content ol { padding-inline-start: 28px; margin-bottom: 14px; }
    .references { page-break-before: always; }
    .references h2 { font-size: 1.4rem; margin-bottom: 20px; }
    .references p { margin: 0 0 14px; line-height: 1.8; }
    @media print {
        .print-bar { display: none; }
        body { padding: 0; max-width: none; }
    }
</style>
</head>
<body>
    <div class="print-bar">
        <button type="button" onclick="window.print()">🖨 طباعة / حفظ كملف PDF</button>
    </div>

    <div class="cover-page">
        <?php if (!empty($doc['university'])): ?><div class="university"><?= h($doc['university']) ?></div><?php endif; ?>
        <?php if (!empty($doc['college'])): ?><div class="college"><?= h($doc['college']) ?></div><?php endif; ?>
        <div class="title"><?= h($doc['title']) ?></div>
        <div class="year"><?= date('Y') ?></div>
    </div>

    <?php if (!empty($sections)): ?>
    <div class="toc">
        <h2>الفهرس</h2>
        <ol>
            <?php foreach ($sections as $s): ?>
                <li><?= h($s['section_title']) ?></li>
            <?php endforeach; ?>
            <?php if (!empty($references)): ?><li>المراجع</li><?php endif; ?>
        </ol>
    </div>
    <?php endif; ?>

    <?php foreach ($sections as $s): ?>
        <div class="section">
            <h2><?= h($s['section_title']) ?></h2>
            <div class="content"><?= $s['content'] ?? '<p class="hint">لم يُكتب محتوى هذا القسم بعد.</p>' ?></div>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($references)): ?>
    <div class="references">
        <h2>المراجع</h2>
        <?php foreach ($references as $ref): ?>
            <p><?= h(format_citation_apa($ref)) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</body>
</html>
