<?php
/**
 * إشعارات تيليكرام للأدمن — رسالة فورية عند حدث مهم (تسجيل جديد، طلب ترقية جديد...)
 * لا يوقف أي عملية بالموقع لو فشل الإرسال (مفتاح غير مُعد، تيليكرام غير متاح...)
 */

function telegram_is_configured() {
    return defined('TELEGRAM_BOT_TOKEN') && TELEGRAM_BOT_TOKEN !== ''
        && defined('TELEGRAM_ADMIN_CHAT_ID') && TELEGRAM_ADMIN_CHAT_ID !== '';
}

/**
 * يهرّب الأحرف الخاصة بوضع HTML بتيليكرام (& < >) لمنع كسر تنسيق الرسالة
 * أو حقن وسوم غير مقصودة عند تضمين بيانات أدخلها المستخدم (اسم، بريد...)
 */
function telegram_escape($text) {
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string)$text);
}

function telegram_notify_admin($text) {
    if (!telegram_is_configured()) {
        return false;
    }

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $payload = http_build_query([
        'chat_id'                  => TELEGRAM_ADMIN_CHAT_ID,
        'text'                     => $text,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    $ok = !curl_errno($ch);
    curl_close($ch);

    return $ok;
}
