<?php
/**
 * غلاف موحّد للذكاء الاصطناعي — يوجّه الطلب لمزوّد OpenAI أو Anthropic حسب إعداد
 * 'ai_provider' بجدول settings، دون أن تحتاج بقية صفحات المنصة معرفة تفاصيل أي مزوّد.
 * لإضافة مزوّد جديد لاحقًا: أنشئ ملف مشابه بمجلد ai_providers/ ووجّه له هنا.
 */
require_once __DIR__ . '/ai_providers/openai.php';
require_once __DIR__ . '/ai_providers/anthropic.php';

function active_ai_provider() {
    return get_setting(get_db(), 'ai_provider', 'openai');
}

/**
 * هل يوجد مفتاح API مُعدّ للمزوّد النشط حاليًا؟
 */
function ai_provider_ready() {
    if (active_ai_provider() === 'anthropic') {
        return defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY !== '';
    }
    return defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '';
}

function ai_provider_label() {
    return active_ai_provider() === 'anthropic' ? 'Anthropic Claude' : 'OpenAI';
}

function ai_send(array $messages, $systemPrompt = '', $maxTokens = 2000) {
    if (active_ai_provider() === 'anthropic') {
        return anthropic_send($messages, $systemPrompt, $maxTokens);
    }
    return openai_send($messages, $systemPrompt, $maxTokens);
}

function ai_chat(array $messages, $systemPrompt = '', $maxTokens = 2000) {
    return ai_send($messages, $systemPrompt, $maxTokens);
}

/**
 * تحليل مستند PDF مرفق مباشرة (base64) — كلا المزوّدين يدعمان PDF كمُدخل مباشر،
 * ما يلغي الحاجة لأي مكتبة استخراج نص خارجية (مهم لاستضافة Hostinger بدون Composer)
 */
function ai_analyze_pdf($base64Pdf, $instruction, $systemPrompt = '') {
    if (active_ai_provider() === 'anthropic') {
        return anthropic_send_pdf($base64Pdf, $instruction, $systemPrompt);
    }
    return openai_send_pdf($base64Pdf, $instruction, $systemPrompt);
}

/**
 * تحليل نص مستخرج مسبقًا (مستندات Word)
 */
function ai_analyze_text($documentText, $instruction, $systemPrompt = '') {
    $messages = [[
        'role'    => 'user',
        'content' => $instruction . "\n\n---\n\n" . $documentText,
    ]];
    return ai_send($messages, $systemPrompt, 3000);
}

/**
 * يبني نظام تعريف (system prompt) للمساعد الأكاديمي بحسب بيانات الطالب
 */
function build_academic_system_prompt(array $user) {
    $parts   = [];
    $parts[] = 'أنت "باحث"، مساعد أكاديمي ذكي متخصص ضمن منصة تعليمية عربية موجهة للطلاب الجامعيين والباحثين.';
    $parts[] = 'أجب دائمًا بأسلوب أكاديمي واضح ودقيق باللغة العربية الفصحى ما لم يطلب المستخدم لغة أخرى صراحة.';
    if (!empty($user['specialization'])) {
        $parts[] = 'تخصص الطالب: ' . $user['specialization'] . '.';
    }
    if (!empty($user['college'])) {
        $parts[] = 'الكلية: ' . $user['college'] . '.';
    }
    if (!empty($user['university'])) {
        $parts[] = 'الجامعة: ' . $user['university'] . '.';
    }
    if (!empty($user['degree_level'])) {
        $parts[] = 'المرحلة الدراسية: ' . degree_level_label($user['degree_level']) . '.';
    }
    $parts[] = 'إذا اقترحت مصادر أو مراجع علمية، وضّح دائمًا أنها اقتراحات على الطالب التحقق منها بنفسه، ولا تختلق أسماء أبحاث أو أرقام DOI أو اقتباسات غير مؤكدة.';
    return implode(' ', $parts);
}
