<?php
/**
 * دوال مشتركة لميزة منشئ التقارير والأوراق البحثية (البرومبتات وبناء السياق)
 */

function report_word_count($textOrHtml) {
    $text = trim(strip_tags((string)$textOrHtml));
    if ($text === '') return 0;
    return count(preg_split('/\s+/u', $text));
}

/**
 * يحوّل نصًا عاديًا (فقرات مفصولة بسطر فارغ) إلى HTML بسيط (p/br) آمن،
 * ليُستخدم كقيمة أولية لمحتوى القسم داخل محرر النصوص
 */
function report_text_to_html($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    $paragraphs = preg_split('/\n{2,}/', $text);
    $html = '';
    foreach ($paragraphs as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $html .= '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    return $html;
}

function report_writing_level_label($key) {
    $labels = [
        'bachelor'     => 'طالب بكالوريوس',
        'diploma'      => 'طالب دبلوم',
        'master'       => 'طالب ماجستير',
        'phd'          => 'طالب دكتوراه',
        'researcher'   => 'باحث متخصص',
        'professional' => 'تقرير مهني',
    ];
    return $labels[$key] ?? $key;
}

/**
 * يبني وصفًا نصيًا لبيانات المستند لاستخدامه ضمن برومبتات الذكاء الاصطناعي
 */
function report_intake_summary(array $doc) {
    $lines = [];
    $lines[] = 'العنوان: ' . $doc['title'];
    $lines[] = 'الموضوع: ' . $doc['topic'];
    if (!empty($doc['academic_field'])) $lines[] = 'التخصص العلمي: ' . $doc['academic_field'];
    if (!empty($doc['university'])) $lines[] = 'الجامعة: ' . $doc['university'];
    if (!empty($doc['college'])) $lines[] = 'الكلية: ' . $doc['college'];
    if (!empty($doc['department'])) $lines[] = 'القسم: ' . $doc['department'];
    $lines[] = 'مستوى الكتابة: ' . report_writing_level_label($doc['writing_level'] ?? 'bachelor');
    if (!empty($doc['audience'])) $lines[] = 'الجمهور المستهدف: ' . $doc['audience'];
    if (!empty($doc['target_words'])) $lines[] = 'عدد الكلمات التقريبي الإجمالي: ' . $doc['target_words'];
    if (!empty($doc['notes'])) $lines[] = 'ملاحظات إضافية من المستخدم: ' . $doc['notes'];
    $lines[] = 'اللغة المطلوبة: ' . ($doc['language'] === 'en' ? 'الإنكليزية' : 'العربية الفصحى');
    return implode("\n", $lines);
}

/**
 * يعيد حساب إجمالي عدد كلمات المستند من مجموع أقسامه المفعّلة، ويحدّث report_documents
 */
function recalculate_report_word_count(PDO $pdo, $documentId) {
    $stmt = $pdo->prepare("SELECT content FROM report_sections WHERE document_id = ? AND status != 'disabled'");
    $stmt->execute([$documentId]);
    $total = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $content) {
        $total += report_word_count($content);
    }
    $pdo->prepare('UPDATE report_documents SET total_words = ? WHERE id = ?')->execute([$total, $documentId]);
}

/**
 * برومبت اقتراح مخطط (أقسام) المستند بصيغة JSON
 */
function report_outline_prompt(array $doc, array $defaultSections, $typeLabel) {
    $suggested = implode('، ', $defaultSections);
    return "أنت تساعد بإعداد مخطط أقسام لمستند أكاديمي من نوع \"$typeLabel\".\n\n" .
        report_intake_summary($doc) . "\n\n" .
        "الهيكل النموذجي لهذا النوع من المستندات عادة يشمل أقسامًا مثل: $suggested\n" .
        "عدّل هذا الهيكل ليناسب الموضوع تحديدًا (احذف ما لا يلزم، أضف ما هو ضروري، أعد الصياغة إذا لزم).\n\n" .
        "أجب حصرًا بصيغة JSON صحيحة بدون أي نص خارج الـ JSON وبدون Markdown، بهذا الشكل بالضبط:\n" .
        '{"sections":[{"title":"عنوان القسم","word_count":300}]}' . "\n" .
        "اجعل عدد الأقسام مناسبًا (عادة 5-12 قسم)، وعدد الكلمات لكل قسم رقمًا واقعيًا يتناسب مع إجمالي عدد الكلمات المطلوب إن ذُكر.";
}

/**
 * برومبت توليد محتوى قسم واحد، مع إعطاء سياق الأقسام الأخرى (عناوين فقط) لتفادي التكرار
 */
function report_section_prompt(array $doc, array $section, array $otherSectionTitles, $sourceText = '') {
    $prompt = "اكتب محتوى قسم \"" . $section['section_title'] . "\" فقط (وليس المستند كاملًا)، ضمن مستند أكاديمي بعنوان \"" . $doc['title'] . "\".\n\n" .
        report_intake_summary($doc) . "\n\n";
    if (!empty($otherSectionTitles)) {
        $prompt .= "أقسام أخرى موجودة بنفس المستند (لا تكرر محتواها، فقط لسياقك): " . implode('، ', $otherSectionTitles) . "\n\n";
    }
    if ($sourceText !== '') {
        $prompt .= "مصادر ومحتوى مرجعي رفعه المستخدم لاستخدامه كسياق:\n" . mb_substr($sourceText, 0, 6000) . "\n\n";
    }
    if (!empty($section['target_word_count'])) {
        $prompt .= "الطول المطلوب لهذا القسم تقريبًا: " . $section['target_word_count'] . " كلمة.\n";
    }
    $prompt .= "اكتب نصًا متماسكًا مباشرًا بأسلوب أكاديمي رصين، بدون تكرار عنوان القسم داخل النص، وبدون عناوين فرعية زائدة ما لم يكن ضروريًا لطبيعة القسم.";
    return $prompt;
}
