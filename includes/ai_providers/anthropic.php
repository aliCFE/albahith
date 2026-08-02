<?php
/**
 * محوّل مزوّد Anthropic (Claude) — لا يُستدعى مباشرة من الصفحات، فقط عبر includes/ai.php
 */

function anthropic_send(array $messages, $systemPrompt, $maxTokens) {
    if (!defined('ANTHROPIC_API_KEY') || ANTHROPIC_API_KEY === '') {
        return ['ok' => false, 'error' => 'لم يتم إعداد مفتاح Anthropic API بعد. تواصل مع إدارة المنصة.'];
    }

    $model = get_setting(get_db(), 'ai_model_anthropic', 'claude-sonnet-5');

    $payload = [
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'messages'   => $messages,
    ];
    if ($systemPrompt !== '') {
        $payload['system'] = $systemPrompt;
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT    => 290,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'تعذر الاتصال بخدمة الذكاء الاصطناعي: ' . $curlError];
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $message = $data['error']['message'] ?? 'حدث خطأ غير معروف من خدمة الذكاء الاصطناعي (رمز ' . $httpCode . ').';
        return ['ok' => false, 'error' => $message];
    }

    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }

    if ($text === '') {
        return ['ok' => false, 'error' => 'لم يصل رد نصي من خدمة الذكاء الاصطناعي.'];
    }

    return [
        'ok'            => true,
        'text'          => $text,
        'model'         => $model,
        'input_tokens'  => (int)($data['usage']['input_tokens'] ?? 0),
        'output_tokens' => (int)($data['usage']['output_tokens'] ?? 0),
    ];
}

/**
 * تحليل PDF عبر إرفاقه مباشرة داخل الرسالة (مدعوم أصليًا بـ Claude)
 */
function anthropic_send_pdf($base64Pdf, $instruction, $systemPrompt) {
    $messages = [[
        'role'    => 'user',
        'content' => [
            [
                'type'   => 'document',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => 'application/pdf',
                    'data'       => $base64Pdf,
                ],
            ],
            [
                'type' => 'text',
                'text' => $instruction,
            ],
        ],
    ]];
    return anthropic_send($messages, $systemPrompt, 3000);
}
