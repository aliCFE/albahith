<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();
$errors = [];

$old = ['topic' => '', 'academic_field' => '', 'degree_level' => 'bachelor', 'university_notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['topic'] = trim($_POST['topic'] ?? '');
    $old['academic_field'] = trim($_POST['academic_field'] ?? '');
    $old['degree_level'] = $_POST['degree_level'] ?? 'bachelor';
    $old['university_notes'] = trim($_POST['university_notes'] ?? '');

    if (!in_array($old['degree_level'], ['bachelor', 'master', 'phd'], true)) {
        $old['degree_level'] = 'bachelor';
    }

    if ($old['topic'] === '' || $old['academic_field'] === '') {
        $errors[] = 'الرجاء إدخال موضوع البحث والتخصص على الأقل.';
    } elseif (usage_remaining($pdo, $user) <= 0) {
        $errors[] = 'وصلت للحد الشهري من طلبات الذكاء الاصطناعي.';
    } else {
        $stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmtUser->execute([$user['id']]);
        $fullUser = $stmtUser->fetch();

        $prompt = "أنشئ خطة بحث أكاديمية منظمة وكاملة بالعناصر التالية:\n" .
            "1) عنوان مقترح للبحث\n2) مقدمة موجزة عن المشكلة البحثية\n3) أهمية البحث\n" .
            "4) أهداف البحث\n5) أسئلة أو فرضيات البحث\n6) منهجية البحث المقترحة\n" .
            "7) الهيكل المقترح للفصول/المحاور\n8) جدول زمني تقريبي\n\n" .
            "موضوع البحث: " . $old['topic'] . "\n" .
            "التخصص: " . $old['academic_field'] . "\n" .
            "المرحلة الدراسية: " . degree_level_label($old['degree_level']) . "\n";
        if ($old['university_notes'] !== '') {
            $prompt .= "متطلبات أو تعليمات خاصة بالجامعة يجب مراعاتها: " . $old['university_notes'] . "\n";
        }
        $prompt .= "\nاكتب الخطة باللغة العربية الفصحى وبأسلوب أكاديمي منظم بعناوين فرعية واضحة.";

        $systemPrompt = build_academic_system_prompt($fullUser);
        $result = ai_chat([['role' => 'user', 'content' => $prompt]], $systemPrompt, 3000);

        if ($result['ok']) {
            $stmt = $pdo->prepare('INSERT INTO research_plans (user_id, topic, academic_field, degree_level, university_notes, generated_content)
                                    VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $user['id'], $old['topic'], $old['academic_field'], $old['degree_level'],
                $old['university_notes'] !== '' ? $old['university_notes'] : null, $result['text'],
            ]);
            record_ai_usage($pdo, $user['id']);
            redirect('student/research-plan-view.php?id=' . $pdo->lastInsertId());
        } else {
            $errors[] = 'تعذر توليد خطة البحث: ' . $result['error'];
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM research_plans WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$plans = $stmt->fetchAll();

$page_title = 'مولّد خطط البحث';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>إنشاء خطة بحث جديدة</h2>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label>موضوع البحث
            <input type="text" name="topic" value="<?= h($old['topic']) ?>" required>
        </label>
        <label>التخصص / المجال الأكاديمي
            <input type="text" name="academic_field" value="<?= h($old['academic_field']) ?>" required>
        </label>
        <label>المرحلة الدراسية
            <select name="degree_level">
                <option value="bachelor" <?= $old['degree_level'] === 'bachelor' ? 'selected' : '' ?>>بكالوريوس</option>
                <option value="master" <?= $old['degree_level'] === 'master' ? 'selected' : '' ?>>ماجستير</option>
                <option value="phd" <?= $old['degree_level'] === 'phd' ? 'selected' : '' ?>>دكتوراه</option>
            </select>
        </label>
        <label>متطلبات أو تعليمات جامعتك الخاصة بالبحث (اختياري)
            <textarea name="university_notes" rows="3" placeholder="مثال: يشترط القسم منهجية وصفية تحليلية، وعدد فصول لا يقل عن 4..."><?= h($old['university_notes']) ?></textarea>
        </label>
        <button type="submit" class="btn">توليد خطة البحث</button>
    </form>
</div>

<div class="panel">
    <h2>خططي السابقة</h2>
    <?php if (empty($plans)): ?>
        <p class="hint">لم تُنشئ أي خطة بحث بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>الموضوع</th><th>التخصص</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td><?= h($plan['topic']) ?></td>
                        <td><?= h($plan['academic_field']) ?></td>
                        <td><?= h($plan['created_at']) ?></td>
                        <td><a href="<?= BASE_URL ?>student/research-plan-view.php?id=<?= (int)$plan['id'] ?>">عرض</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
