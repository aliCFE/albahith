<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
require_once __DIR__ . '/../includes/ai_cost.php';
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

$stmt = $pdo->prepare('SELECT * FROM report_types WHERE type_key = ?');
$stmt->execute([$doc['document_type']]);
$type = $stmt->fetch();
$defaultSections = $type ? (json_decode($type['default_sections'] ?? '[]', true) ?: []) : [];

$errors = [];

function reports_fetch_sections(PDO $pdo, $documentId) {
    $stmt = $pdo->prepare('SELECT * FROM report_sections WHERE document_id = ? ORDER BY sort_order ASC');
    $stmt->execute([$documentId]);
    return $stmt->fetchAll();
}

function reports_renumber(PDO $pdo, $documentId) {
    $sections = reports_fetch_sections($pdo, $documentId);
    $stmt = $pdo->prepare('UPDATE report_sections SET sort_order = ? WHERE id = ?');
    foreach ($sections as $i => $s) {
        $stmt->execute([$i, $s['id']]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_outline') {
        if (usage_remaining($pdo, $user) <= 0) {
            $errors[] = 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.';
        } else {
            $stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmtUser->execute([$user['id']]);
            $fullUser = $stmtUser->fetch();
            $systemPrompt = build_academic_system_prompt($fullUser);
            $prompt = report_outline_prompt($doc, $defaultSections ?: ['المقدمة', 'المحتوى', 'الخاتمة'], $type['label_ar'] ?? $doc['document_type']);

            $result = ai_chat([['role' => 'user', 'content' => $prompt]], $systemPrompt, 2000);
            log_ai_request($pdo, $user['id'], $id, null, 'generate_outline', $result);

            if (!$result['ok']) {
                $errors[] = 'تعذر توليد المخطط: ' . $result['error'];
            } else {
                $jsonText = trim($result['text']);
                $jsonText = preg_replace('/^```(json)?/i', '', $jsonText);
                $jsonText = preg_replace('/```$/', '', trim($jsonText));
                $parsed = json_decode(trim($jsonText), true);

                if (!is_array($parsed) || empty($parsed['sections']) || !is_array($parsed['sections'])) {
                    $errors[] = 'تعذر تحليل المخطط الناتج من الذكاء الاصطناعي. جرّب مرة أخرى أو استخدم الهيكل الافتراضي.';
                } else {
                    $pdo->prepare('DELETE FROM report_sections WHERE document_id = ?')->execute([$id]);
                    $stmtIns = $pdo->prepare('INSERT INTO report_sections (document_id, section_title, sort_order, target_word_count) VALUES (?, ?, ?, ?)');
                    foreach ($parsed['sections'] as $i => $s) {
                        $stmtIns->execute([$id, (string)($s['title'] ?? 'قسم'), $i, (int)($s['word_count'] ?? 300)]);
                    }
                    $pdo->prepare("UPDATE report_documents SET status = 'outline_ready' WHERE id = ?")->execute([$id]);
                    record_ai_usage($pdo, $user['id']);
                    flash_set('تم توليد المخطط. راجعه وعدّله حسب حاجتك.');
                    redirect('student/reports-outline.php?id=' . $id);
                }
            }
        }
    } elseif ($action === 'use_default_template') {
        $pdo->prepare('DELETE FROM report_sections WHERE document_id = ?')->execute([$id]);
        $wordEach = !empty($doc['target_words']) && count($defaultSections) > 0 ? intdiv((int)$doc['target_words'], count($defaultSections)) : 300;
        $stmtIns = $pdo->prepare('INSERT INTO report_sections (document_id, section_title, sort_order, target_word_count) VALUES (?, ?, ?, ?)');
        foreach ($defaultSections as $i => $title) {
            $stmtIns->execute([$id, $title, $i, $wordEach]);
        }
        $pdo->prepare("UPDATE report_documents SET status = 'outline_ready' WHERE id = ?")->execute([$id]);
        flash_set('تم استخدام الهيكل الافتراضي لهذا النوع.');
        redirect('student/reports-outline.php?id=' . $id);
    } elseif ($action === 'save_sections') {
        $titles = $_POST['title'] ?? [];
        $words = $_POST['word_count'] ?? [];
        $enabled = $_POST['enabled'] ?? [];
        $stmtUpd = $pdo->prepare('UPDATE report_sections SET section_title = ?, target_word_count = ?, status = ? WHERE id = ? AND document_id = ?');
        foreach ($titles as $sectionId => $title) {
            $sectionId = (int)$sectionId;
            $title = trim($title);
            if ($title === '') continue;
            $wordCount = (int)($words[$sectionId] ?? 300);
            $isEnabled = isset($enabled[$sectionId]);
            $stmtCur = $pdo->prepare('SELECT status FROM report_sections WHERE id = ? AND document_id = ?');
            $stmtCur->execute([$sectionId, $id]);
            $currentStatus = $stmtCur->fetchColumn();
            if ($currentStatus === false) continue;
            $newStatus = $isEnabled ? ($currentStatus === 'disabled' ? 'pending' : $currentStatus) : 'disabled';
            $stmtUpd->execute([$title, $wordCount, $newStatus, $sectionId, $id]);
        }
        flash_set('تم حفظ التعديلات على المخطط.');
        redirect('student/reports-outline.php?id=' . $id);
    } elseif ($action === 'add_section') {
        $title = trim($_POST['new_section_title'] ?? '');
        if ($title !== '') {
            $sections = reports_fetch_sections($pdo, $id);
            $stmt = $pdo->prepare('INSERT INTO report_sections (document_id, section_title, sort_order, target_word_count) VALUES (?, ?, ?, ?)');
            $stmt->execute([$id, $title, count($sections), 300]);
        }
        redirect('student/reports-outline.php?id=' . $id);
    } elseif ($action === 'delete_section') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $pdo->prepare('DELETE FROM report_sections WHERE id = ? AND document_id = ?')->execute([$sectionId, $id]);
        reports_renumber($pdo, $id);
        redirect('student/reports-outline.php?id=' . $id);
    } elseif ($action === 'move_section') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        $sections = reports_fetch_sections($pdo, $id);
        $ids = array_column($sections, 'id');
        $pos = array_search($sectionId, $ids, true);
        if ($pos !== false) {
            if ($direction === 'up' && $pos > 0) {
                [$ids[$pos - 1], $ids[$pos]] = [$ids[$pos], $ids[$pos - 1]];
            } elseif ($direction === 'down' && $pos < count($ids) - 1) {
                [$ids[$pos + 1], $ids[$pos]] = [$ids[$pos], $ids[$pos + 1]];
            }
            $stmt = $pdo->prepare('UPDATE report_sections SET sort_order = ? WHERE id = ?');
            foreach ($ids as $i => $sid) {
                $stmt->execute([$i, $sid]);
            }
        }
        redirect('student/reports-outline.php?id=' . $id);
    } elseif ($action === 'proceed_to_writing') {
        $pdo->prepare("UPDATE report_documents SET status = 'writing' WHERE id = ? AND status = 'outline_ready'")->execute([$id]);
        redirect('student/reports-editor.php?id=' . $id);
    }
}

$sections = reports_fetch_sections($pdo, $id);

$page_title = 'مخطط: ' . $doc['title'];
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <p><a href="<?= BASE_URL ?>student/reports.php">→ العودة لمستنداتي</a></p>
    <h2><?= h($doc['title']) ?></h2>
    <p class="hint"><?= h($type['label_ar'] ?? $doc['document_type']) ?> — <?= h($doc['topic']) ?></p>
</div>

<?php if (empty($sections)): ?>
<div class="panel">
    <h2>الخطوة 1: أنشئ مخطط المستند</h2>
    <p class="hint">يقترح الذكاء الاصطناعي هيكل أقسام مناسب لموضوعك، أو تقدر تبدأ بالهيكل الافتراضي لهذا النوع وتعدّله بنفسك.</p>
    <form method="post" class="inline-form" style="display:inline-block; margin-inline-end: 10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate_outline">
        <button type="submit" class="btn">✨ اقترح مخططًا بالذكاء الاصطناعي</button>
    </form>
    <form method="post" class="inline-form" style="display:inline-block;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="use_default_template">
        <button type="submit" class="btn btn-outline">استخدم الهيكل الافتراضي</button>
    </form>
</div>
<?php else: ?>
<div class="panel">
    <h2>الخطوة 2: راجع وعدّل المخطط</h2>
    <p class="hint">عدّل عناوين الأقسام وعدد الكلمات، رتّبها، أو عطّل أي قسم ما تحتاجه. بعد ما ترتاح للمخطط اضغط "ابدأ الكتابة".</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_sections">
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>مفعّل</th><th>عنوان القسم</th><th>عدد الكلمات</th><th>ترتيب</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($sections as $s): ?>
                    <tr>
                        <td><input type="checkbox" name="enabled[<?= (int)$s['id'] ?>]" <?= $s['status'] !== 'disabled' ? 'checked' : '' ?>></td>
                        <td><input type="text" name="title[<?= (int)$s['id'] ?>]" value="<?= h($s['section_title']) ?>" style="min-width:220px;"></td>
                        <td><input type="number" name="word_count[<?= (int)$s['id'] ?>]" value="<?= (int)$s['target_word_count'] ?>" min="50" style="width:100px;"></td>
                        <td>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_section">
                                <input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" name="direction" value="up" class="btn-link">↑</button>
                                <button type="submit" name="direction" value="down" class="btn-link">↓</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="inline-form" onsubmit="return confirm('حذف هذا القسم؟');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_section">
                                <input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn-link">✕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <button type="submit" class="btn">حفظ التعديلات</button>
    </form>
</div>

<div class="panel">
    <h3>إضافة قسم جديد</h3>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_section">
        <label>عنوان القسم
            <input type="text" name="new_section_title" required>
        </label>
        <button type="submit" class="btn btn-outline">+ إضافة</button>
    </form>
</div>

<div class="panel">
    <h2>الخطوة 3: ابدأ الكتابة</h2>
    <p class="hint">راح يولّد الذكاء الاصطناعي محتوى كل قسم مفعّل بشكل منفصل ويحفظه تلقائيًا.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="proceed_to_writing">
        <button type="submit" class="btn">ابدأ الكتابة ←</button>
    </form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
