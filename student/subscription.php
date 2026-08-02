<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/telegram.php';
require_role(['student', 'instructor']);

$pdo = get_db();
$user = current_user();

$stmt = $pdo->prepare('SELECT plan, university FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$row = $stmt->fetch();
$plan = $row['plan'];

$errors = [];
const MAX_PROOF_BYTES = 8 * 1024 * 1024; // 8 ميجابايت

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (empty($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'الرجاء اختيار صورة إثبات الدفع.';
    } else {
        $file = $_FILES['proof'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $errors[] = 'يُسمح فقط بصور JPG أو PNG أو WEBP.';
        } elseif ($file['size'] > MAX_PROOF_BYTES) {
            $errors[] = 'حجم الصورة كبير جدًا (الحد الأقصى 8 ميجابايت).';
        } else {
            $storedName = uniqid('proof_', true) . '.' . $ext;
            $destination = __DIR__ . '/../uploads/payment_proofs/' . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $errors[] = 'تعذر حفظ الصورة على الخادم.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO payment_requests (user_id, proof_filename, status) VALUES (?, ?, \'pending\')');
                $stmt->execute([$user['id'], $storedName]);
                telegram_notify_admin(
                    "💳 <b>طلب ترقية جديد بباحث</b>\n\n" .
                    'الاسم: ' . telegram_escape($user['name']) . "\n" .
                    'البريد: ' . telegram_escape($user['email']) . "\n" .
                    'الخطة الحالية: ' . telegram_escape(plan_label($plan))
                );
                flash_set('تم إرسال طلب الترقية. راح يتم مراجعته من الإدارة قريبًا.');
                redirect('student/subscription.php');
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM payment_requests WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();

$zaincashNumber = get_setting($pdo, 'zaincash_number', '');
$superkeyNumber = get_setting($pdo, 'superkey_number', '');
$proPriceDisplay = get_setting($pdo, 'pro_price_display', '');
$paymentInfo = get_setting($pdo, 'payment_account_info', '');
$hasPaymentMethod = $zaincashNumber !== '' || $superkeyNumber !== '';
$proLimit = pro_monthly_limit($pdo);
$freeLimit = monthly_free_limit($pdo);

$page_title = 'الاشتراك';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>خطتك الحالية: <?= h(plan_label($plan)) ?></h2>
    <p class="hint">الخطة المجانية: <?= $freeLimit ?> طلب شهريًا. الخطة المدفوعة: <?= $proLimit ?> طلب شهريًا.</p>
</div>

<?php if ($plan !== 'pro'): ?>
<div class="panel">
    <h2>ترقية للخطة المدفوعة</h2>
    <?php if (!$hasPaymentMethod): ?>
        <p class="hint">لم تُضف إدارة المنصة بيانات حساب الدفع بعد. تواصل معهم مباشرة.</p>
    <?php else: ?>
        <?php if ($proPriceDisplay !== ''): ?>
            <div class="plan-price-card">
                <span class="plan-price-card-label">باحث Plus</span>
                <span class="plan-price-card-value"><?= h($proPriceDisplay) ?></span>
            </div>
        <?php endif; ?>
        <p class="hint">حوّل قيمة الاشتراك إلى أحد الحسابات التالية، ثم ارفع صورة إثبات التحويل بالأسفل ليتم تفعيل الخطة بعد المراجعة:</p>
        <div class="payment-methods">
            <?php if ($zaincashNumber !== ''): ?>
                <div class="payment-method-card">
                    <span class="badge badge-info">زين كاش</span>
                    <p class="payment-method-number"><?= h($zaincashNumber) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($superkeyNumber !== ''): ?>
                <div class="payment-method-card">
                    <span class="badge badge-info">سوبر كي</span>
                    <p class="payment-method-number"><?= h($superkeyNumber) ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($paymentInfo !== ''): ?>
            <div class="ai-output"><?= nl2br(h($paymentInfo)) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="auth-form">
            <?= csrf_field() ?>
            <label>صورة إثبات الدفع
                <input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp" required>
            </label>
            <button type="submit" class="btn">إرسال طلب الترقية</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
    <h2>طلباتي السابقة</h2>
    <?php if (empty($requests)): ?>
        <p class="hint">لا يوجد طلبات ترقية بعد.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>التاريخ</th><th>الحالة</th><th>ملاحظة الإدارة</th></tr></thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= h($r['created_at']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'approved'): ?>
                                <span class="badge badge-success">تمت الموافقة</span>
                            <?php elseif ($r['status'] === 'rejected'): ?>
                                <span class="badge badge-error">مرفوض</span>
                            <?php else: ?>
                                <span class="badge badge-info">قيد المراجعة</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($r['admin_note'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
