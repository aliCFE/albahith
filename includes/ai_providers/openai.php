<?php
/**
 * محوّل مزوّد OpenAI — لا يُستدعى مباشرة من الصفحات، فقط عبر includes/ai.php
 */

function openai_send(array $messages, $systemPrompt, $maxTokens) {
    if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
        return ['ok' => false, 'error' => 'لم يتم إعداد مفتاح OpenAI API بعد. تواصل مع إدارة المنصة.'];
    }

    $model = get_setting(get_db(), 'ai_model_openai', 'gpt-4o-mini');

    $fullMessages = [];
    if ($systemPrompt !== '') {
        $fullMessages[] = ['role' => 'system', 'content' => $systemPrompt];
    }
    foreach ($messages as $m) {
        $fullMessages[] = $m;
    }

    $payload = [
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'messages'   => $fullMessages,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json',
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

    $text = $data['choices'][0]['message']['content'] ?? '';

    if ($text === '') {
        return ['ok' => false, 'error' => 'لم يصل رد نصي من خدمة الذكاء الاصطناعي.'];
    }

    return [
        'ok'             => true,
        'text'           => $text,
        'model'          => $model,
        'input_tokens'   => (int)($data['usage']['prompt_tokens'] ?? 0),
        'output_tokens'  => (int)($data['usage']['completion_tokens'] ?? 0),
    ];
}

/**
 * تحليل PDF عبر إرفاقه مباشرة داخل الرسالة (مدعوم في gpt-4o وما بعده)
 */
function openai_send_pdf($base64Pdf, $instruction, $systemPrompt) {
    $messages = [[
        'role'    => 'user',
        'content' => [
            [
                'type' => 'file',
                'file' => [
                    'filename'  => 'document.pdf',
                    'file_data' => 'data:application/pdf;base64,' . $base64Pdf,
                ],
            ],
            [
                'type' => 'text',
                'text' => $instruction,
            ],
        ],
    ]];
    return openai_send($messages, $systemPrompt, 3000);
}
