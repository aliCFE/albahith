<?php
/**
 * تقدير تكلفة استدعاءات الذكاء الاصطناعي وتسجيلها بجدول ai_requests
 * الأسعار تقريبية (دولار لكل مليون توكن) وتحتاج تحديث دوري حسب تسعير المزوّدين الفعلي
 */

function ai_pricing_table() {
    return [
        'gpt-4o-mini'      => ['input' => 0.15, 'output' => 0.60],
        'gpt-4o'           => ['input' => 2.50, 'output' => 10.00],
        'claude-sonnet-5'  => ['input' => 3.00, 'output' => 15.00],
        'claude-haiku-4-5' => ['input' => 0.80, 'output' => 4.00],
    ];
}

function estimate_ai_cost($model, $inputTokens, $outputTokens) {
    $table = ai_pricing_table();
    $rates = $table[$model] ?? ['input' => 1.00, 'output' => 3.00]; // تقدير احتياطي لموديل غير مدرج
    $cost = ($inputTokens / 1000000 * $rates['input']) + ($outputTokens / 1000000 * $rates['output']);
    return round($cost, 5);
}

/**
 * يسجّل عملية ذكاء اصطناعي واحدة (نجحت أو فشلت) لأغراض المحاسبة والتقارير الإدارية
 * $result هي القيمة المرجعة من ai_send()/ai_chat()
 */
function log_ai_request(PDO $pdo, $userId, $documentId, $sectionId, $promptType, array $result) {
    $model = $result['model'] ?? get_setting($pdo, active_ai_provider() === 'anthropic' ? 'ai_model_anthropic' : 'ai_model_openai', '');
    $inputTokens = (int)($result['input_tokens'] ?? 0);
    $outputTokens = (int)($result['output_tokens'] ?? 0);
    $cost = estimate_ai_cost($model, $inputTokens, $outputTokens);

    $stmt = $pdo->prepare('INSERT INTO ai_requests
        (user_id, document_id, section_id, provider, model, prompt_type, input_tokens, output_tokens, estimated_cost_usd, response_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $documentId,
        $sectionId,
        active_ai_provider(),
        $model,
        $promptType,
        $inputTokens,
        $outputTokens,
        $cost,
        !empty($result['ok']) ? 'success' : 'failed',
    ]);
}
