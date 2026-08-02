<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db();

$totalDocuments = (int)$pdo->query('SELECT COUNT(*) FROM report_documents')->fetchColumn();
$completedDocuments = (int)$pdo->query("SELECT COUNT(*) FROM report_documents WHERE status = 'completed'")->fetchColumn();
$totalSections = (int)$pdo->query("SELECT COUNT(*) FROM report_sections WHERE status IN ('generated','edited')")->fetchColumn();
$totalFiles = (int)$pdo->query('SELECT COUNT(*) FROM report_files')->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) AS requests, COALESCE(SUM(input_tokens),0) AS input_tokens,
                             COALESCE(SUM(output_tokens),0) AS output_tokens, COALESCE(SUM(estimated_cost_usd),0) AS cost
                      FROM ai_requests');
$usage = $stmt->fetch();

$stmt = $pdo->query("SELECT COUNT(*) FROM ai_requests WHERE response_status = 'failed'");
$failedRequests = (int)$stmt->fetchColumn();

$byType = $pdo->query("SELECT rt.label_ar, COUNT(rd.id) AS total
                        FROM report_documents rd
                        LEFT JOIN report_types rt ON rt.type_key = rd.document_type
                        GROUP BY rt.label_ar
                        ORDER BY total DESC")->fetchAll();

$stmt = $pdo->query('SELECT ar.*, u.name AS user_name, rd.title AS doc_title
                      FROM ai_requests ar
                      LEFT JOIN users u ON u.id = ar.user_id
                      LEFT JOIN report_documents rd ON rd.id = ar.document_id
                      ORDER BY ar.created_at DESC
                      LIMIT 30');
$recentRequests = $stmt->fetchAll();

$promptLabels = [
    'generate_outline'  => 'توليد مخطط',
    'generate_section'  => 'توليد قسم',
    'ai_tool_rewrite'   => 'إعادة صياغة',
    'ai_tool_academic'  => 'أسلوب أكاديمي',
    'ai_tool_shorten'   => 'اختصار',
    'ai_tool_expand'    => 'توسيع',
    'ai_tool_simplify'  => 'تبسيط',
    'ai_tool_proofread' => 'تصحيح لغوي',
];

$page_title = 'إحصائيات التقارير والأوراق البحثية';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <p><a href="<?= BASE_URL ?>admin/dashboard.php">→ لوحة التحكم</a></p>
    <h2>إحصائيات منشئ التقارير</h2>
</div>

<div class="dashboard-grid">
    <div class="dash-card dash-card-static">
        <h3><?= $totalDocuments ?></h3>
        <p>مستند تم إنشاؤه، منها <?= $completedDocuments ?> مكتمل</p>
    </div>
    <div class="dash-card dash-card-static">
        <h3><?= $totalSections ?></h3>
        <p>قسم تم توليده أو تحريره</p>
    </div>
    <div class="dash-card dash-card-static">
        <h3><?= $totalFiles ?></h3>
        <p>ملف مصدر مرفوع</p>
    </div>
    <div class="dash-card dash-card-static">
        <h3><?= (int)$usage['requests'] ?></h3>
        <p>طلب ذكاء اصطناعي، منها <?= $failedRequests ?> فشل</p>
    </div>
    <div class="dash-card dash-card-static">
        <h3><?= number_format((int)$usage['input_tokens'] + (int)$usage['output_tokens']) ?></h3>
        <p>توكن مستهلك (إدخال + إخراج)</p>
    </div>
    <div class="dash-card dash-card-static">
        <h3>$<?= number_format((float)$usage['cost'], 3) ?></h3>
        <p>التكلفة التقديرية الإجمالية</p>
    </div>
</div>

<div class="panel">
    <h2>حسب نوع المستند</h2>
    <?php if (empty($byType)): ?>
        <p class="hint">لا توجد بيانات بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>النوع</th><th>عدد المستندات</th></tr></thead>
            <tbody>
                <?php foreach ($byType as $row): ?>
                    <tr>
                        <td><?= h($row['label_ar'] ?: 'غير محدد') ?></td>
                        <td><?= (int)$row['total'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>آخر طلبات الذكاء الاصطناعي</h2>
    <?php if (empty($recentRequests)): ?>
        <p class="hint">لا توجد طلبات بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>المستخدم</th><th>المستند</th><th>النوع</th><th>الموديل</th><th>التوكنات</th><th>التكلفة</th><th>الحالة</th><th>التاريخ</th></tr></thead>
            <tbody>
                <?php foreach ($recentRequests as $r): ?>
                    <tr>
                        <td><?= h($r['user_name'] ?? '—') ?></td>
                        <td><?= h($r['doc_title'] ?? '—') ?></td>
                        <td><?= h($promptLabels[$r['prompt_type']] ?? $r['prompt_type']) ?></td>
                        <td><?= h($r['model']) ?></td>
                        <td><?= (int)$r['input_tokens'] + (int)$r['output_tokens'] ?></td>
                        <td>$<?= number_format((float)$r['estimated_cost_usd'], 5) ?></td>
                        <td>
                            <?php if ($r['response_status'] === 'success'): ?>
                                <span class="badge badge-success">نجح</span>
                            <?php else: ?>
                                <span class="badge badge-error">فشل</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($r['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
