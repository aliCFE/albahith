<?php
/**
 * استخراج النص من ملفات Word (.docx) دون أي مكتبات خارجية.
 * ملف docx هو أرشيف ZIP يحتوي XML، ونستخدم ZipArchive المدمجة بـ PHP لفتحه مباشرة.
 */
function extract_text_from_docx($filePath) {
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return null;
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        return null;
    }

    $xml  = preg_replace('/<\/w:p>/', "\n", $xml);
    $xml  = preg_replace('/<w:tab\/>/', "\t", $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));

    return $text === '' ? null : $text;
}
