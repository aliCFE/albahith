<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
require_role('admin');

$pdo = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $monthlyLimit = (int)($_POST['monthly_free_limit'] ?? 0);
    $proLimit = (int)($_POST['pro_monthly_limit'] ?? 0);
    $provider = ($_POST['ai_provider'] ?? 'openai') === 'anthropic' ? 'anthropic' : 'openai';
    $aiModelOpenai = trim($_POST['ai_model_openai'] ?? '');
    $aiModelAnthropic = trim($_POST['ai_model_anthropic'] ?? '');
    $zaincashNumber = trim($_POST['zaincash_number'] ?? '');
    $superkeyNumber = trim($_POST['superkey_number'] ?? '');
    $paymentInfo = trim($_POST['payment_account_info'] ?? '');

    if ($monthlyLimit < 1) {
        $errors[] = 'الحد الشهري للخطة المجانية يجب أن يكون رقمًا أكبر من صفر.';
    }
    if ($proLimit < 1) {
        $errors[] = 'الحد الشهري للخطة المدفوعة يجب أن يكون رقمًا أكبر من صفر.';
    }
    if ($aiModelOpenai === '' || $aiModelAnthropic === '') {
        $errors[] = 'أسماء الموديلات مطلوبة لكلا المزوّدين.';
    }

    if (empty($errors)) {
        set_setting($pdo, 'monthly_free_limit', (string)$monthlyLimit);
        set_setting($pdo, 'pro_monthly_limit', (string)$proLimit);
        set_setting($pdo, 'ai_provider', $provider);
        set_setting($pdo, 'ai_model_openai', $aiModelOpenai);
        set_setting($pdo, 'ai_model_anthropic', $aiModelAnthropic);
        set_setting($pdo, 'zaincash_number', $zaincashNumber);
        set_setting($pdo, 'superkey_number', $superkeyNumber);
        set_setting($pdo, 'payment_account_info', $paymentInfo);
        flash_set('تم حفظ الإعدادات. لتغيير مفاتيح API نفسها، عدّل ملف config.php مباشرة عبر مدير ملفات Hostinger.');
        redirect('admin/settings.php');
    }
}

$monthlyLimit = monthly_free_limit($pdo);
$proLimit = pro_monthly_limit($pdo);
$provider = active_ai_provider();
$aiModelOpenai = get_setting($pdo, 'ai_model_openai', 'gpt-4o-mini');
$aiModelAnthropic = get_setting($pdo, 'ai_model_anthropic', 'claude-sonnet-5');
$zaincashNumber = get_setting($pdo, 'zaincash_number', '');
$superkeyNumber = get_setting($pdo, 'superkey_number', '');
$paymentInfo = get_setting($pdo, 'payment_account_info', '');
$hasOpenaiKey = defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '';
$hasAnthropicKey = defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY !== '';
$hasPexelsKey = defined('PEXELS_API_KEY') && PEXELS_API_KEY !== '';

$page_title = 'الإعدادات';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>حالة مزوّدي الذكاء الاصطناعي</h2>
    <p>المزوّد النشط حاليًا: <strong><?= h(ai_provider_label()) ?></strong></p>
    <p class="badge <?= $hasOpenaiKey ? 'badge-success' : 'badge-error' ?>">OpenAI: <?= $hasOpenaiKey ? 'مفتاح مُعدّ' : 'بدون مفتاح' ?></p>
    <p class="badge <?= $hasAnthropicKey ? 'badge-success' : 'badge-error' ?>">Anthropic: <?= $hasAnthropicKey ? 'مفتاح مُعدّ' : 'بدون مفتاح' ?></p>
    <p class="hint">لإضافة/تعديل أي مفتاح: افتح ملف <code>config.php</code> عبر مدير ملفات Hostinger وعدّل سطر <code>OPENAI_API_KEY</code> أو <code>ANTHROPIC_API_KEY</code>.</p>
</div>

<div class="panel">
    <h2>صور العروض التقديمية (Pexels)</h2>
    <p class="badge <?= $hasPexelsKey ? 'badge-success' : 'badge-error' ?>">Pexels: <?= $hasPexelsKey ? 'مفتاح مُعدّ' : 'بدون مفتاح' ?></p>
    <p class="hint">
        عند إعداد مفتاح Pexels، تُضاف صور حقيقية مرتبطة بمحتوى كل شريحة تلقائيًا عند توليد العروض التقديمية. بدونه، تُبنى العروض بدون صور (بقية التصميم يعمل طبيعيًا).
        احصل على مفتاح مجاني فوري من <strong>pexels.com/api</strong> (تسجيل بسيط، بدون بطاقة دفع)، ثم أضفه بملف <code>config.php</code>:
    </p>
    <p class="hint"><code>define('PEXELS_API_KEY', 'مفتاحك هنا...');</code></p>
</div>

<div class="panel">
    <h2>إعدادات الاستخدام والذكاء الاصطناعي</h2>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label>الحد الشهري للخطة المجانية (عدد الطلبات)
            <input type="number" name="monthly_free_limit" value="<?= (int)$monthlyLimit ?>" min="1" required>
        </label>
        <label>الحد الشهري للخطة المدفوعة (عدد الطلبات)
            <input type="number" name="pro_monthly_limit" value="<?= (int)$proLimit ?>" min="1" required>
        </label>
        <label>مزوّد الذكاء الاصطناعي النشط
            <select name="ai_provider">
                <option value="openai" <?= $provider === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                <option value="anthropic" <?= $provider === 'anthropic' ? 'selected' : '' ?>>Anthropic Claude</option>
            </select>
        </label>
        <label>اسم موديل OpenAI
            <input type="text" name="ai_model_openai" value="<?= h($aiModelOpenai) ?>" required>
        </label>
        <label>اسم موديل Anthropic
            <input type="text" name="ai_model_anthropic" value="<?= h($aiModelAnthropic) ?>" required>
        </label>
        <button type="submit" class="btn">حفظ إعدادات الاستخدام</button>
    </form>
</div>

<div class="panel">
    <h2>بيانات استلام الدفع (تظهر للطلاب عند طلب الترقية)</h2>
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="monthly_free_limit" value="<?= (int)$monthlyLimit ?>">
        <input type="hidden" name="pro_monthly_limit" value="<?= (int)$proLimit ?>">
        <input type="hidden" name="ai_provider" value="<?= h($provider) ?>">
        <input type="hidden" name="ai_model_openai" value="<?= h($aiModelOpenai) ?>">
        <input type="hidden" name="ai_model_anthropic" value="<?= h($aiModelAnthropic) ?>">
        <label>رقم زين كاش (ZainCash)
            <input type="text" name="zaincash_number" value="<?= h($zaincashNumber) ?>" placeholder="07xxxxxxxxx">
        </label>
        <label>رقم سوبر كي (Super Key)
            <input type="text" name="superkey_number" value="<?= h($superkeyNumber) ?>" placeholder="رقم حساب سوبر كي">
        </label>
        <label>ملاحظات إضافية (اختياري — اسم صاحب الحساب، تعليمات إضافية...)
            <textarea name="payment_account_info" rows="3" placeholder="مثال: الاسم: باحث - يرجى كتابة اسم الطالب بخانة الملاحظة عند التحويل"><?= h($paymentInfo) ?></textarea>
        </label>
        <button type="submit" class="btn">حفظ بيانات الدفع</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
