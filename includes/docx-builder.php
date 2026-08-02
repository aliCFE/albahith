<?php
/**
 * توليد ملف Word (.docx) حقيقي وصالح من الصفر عبر ZipArchive، بدون أي مكتبة خارجية
 * (لتوافق استضافة Hostinger بدون Composer) — يشبه نمط includes/pptx.php
 */

function docx_escape($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function docx_content_types() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' .
        '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>' .
        '<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>' .
        '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>' .
        '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>' .
        '</Types>';
}

function docx_root_rels() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>' .
        '</Relationships>';
}

function docx_document_rels() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>' .
        '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>' .
        '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>' .
        '</Relationships>';
}

function docx_settings_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
        '<w:updateFields w:val="true"/>' .
        '<w:defaultTabStop w:val="708"/>' .
        '</w:settings>';
}

function docx_styles_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
        '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:rtl/></w:rPr></w:rPrDefault></w:docDefaults>' .
        '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/>' .
        '<w:pPr><w:bidi/><w:jc w:val="both"/><w:spacing w:after="200" w:line="360" w:lineRule="auto"/></w:pPr>' .
        '<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:rtl/></w:rPr></w:style>' .
        '<w:style w:type="paragraph" w:styleId="TitleDoc"><w:name w:val="TitleDoc"/><w:basedOn w:val="Normal"/>' .
        '<w:pPr><w:bidi/><w:jc w:val="center"/><w:spacing w:before="240" w:after="240"/></w:pPr>' .
        '<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:b/><w:sz w:val="44"/><w:szCs w:val="44"/><w:color w:val="1F4D36"/><w:rtl/></w:rPr></w:style>' .
        '<w:style w:type="paragraph" w:styleId="SubtitleDoc"><w:name w:val="SubtitleDoc"/><w:basedOn w:val="Normal"/>' .
        '<w:pPr><w:bidi/><w:jc w:val="center"/><w:spacing w:before="120" w:after="120"/></w:pPr>' .
        '<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:sz w:val="28"/><w:szCs w:val="28"/><w:rtl/></w:rPr></w:style>' .
        '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/>' .
        '<w:pPr><w:bidi/><w:jc w:val="right"/><w:outlineLvl w:val="0"/><w:spacing w:before="360" w:after="180"/><w:pageBreakBefore/></w:pPr>' .
        '<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:b/><w:sz w:val="32"/><w:szCs w:val="32"/><w:color w:val="1F4D36"/><w:rtl/></w:rPr></w:style>' .
        '<w:style w:type="paragraph" w:styleId="TOC1"><w:name w:val="toc 1"/><w:basedOn w:val="Normal"/>' .
        '<w:pPr><w:bidi/><w:jc w:val="right"/><w:spacing w:after="120"/></w:pPr>' .
        '<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:sz w:val="26"/><w:szCs w:val="26"/><w:rtl/></w:rPr></w:style>' .
        '<w:style w:type="paragraph" w:styleId="RefEntry"><w:name w:val="RefEntry"/><w:basedOn w:val="Normal"/>' .
        '<w:pPr><w:bidi/><w:jc w:val="right"/><w:spacing w:after="160"/><w:ind w:left="284" w:hanging="284"/></w:pPr></w:style>' .
        '</w:styles>';
}

function docx_header_xml($title) {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
        '<w:p><w:pPr><w:bidi/><w:jc w:val="center"/><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="4" w:color="DDE5E0"/></w:pBdr></w:pPr>' .
        '<w:r><w:rPr><w:rFonts w:cs="Arial"/><w:sz w:val="18"/><w:color w:val="5C6B64"/><w:rtl/></w:rPr><w:t xml:space="preserve">' . docx_escape($title) . '</w:t></w:r></w:p>' .
        '</w:hdr>';
}

function docx_footer_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
        '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>' .
        '<w:r><w:fldChar w:fldCharType="begin"/></w:r>' .
        '<w:r><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>' .
        '<w:r><w:fldChar w:fldCharType="separate"/></w:r>' .
        '<w:r><w:rPr><w:noProof/></w:rPr><w:t>1</w:t></w:r>' .
        '<w:r><w:fldChar w:fldCharType="end"/></w:r>' .
        '</w:p></w:ftr>';
}

/**
 * يحوّل نص عادي (بفقرات مفصولة بسطر فارغ) إلى فقرات Word سليمة، مع الهروب الآمن من محارف XML
 */
function docx_text_to_paragraphs($text, $styleId = null) {
    $text = trim((string)$text);
    if ($text === '') return '';
    $paragraphs = preg_split('/\n{2,}/', $text);
    $pPr = $styleId ? '<w:pPr><w:pStyle w:val="' . $styleId . '"/></w:pPr>' : '';
    $xml = '';
    foreach ($paragraphs as $para) {
        $para = trim($para);
        if ($para === '') continue;
        $lines = explode("\n", $para);
        $runs = '';
        foreach ($lines as $i => $line) {
            if ($i > 0) $runs .= '<w:br/>';
            $runs .= '<w:t xml:space="preserve">' . docx_escape($line) . '</w:t>';
        }
        $xml .= '<w:p>' . $pPr . '<w:r>' . $runs . '</w:r></w:p>';
    }
    return $xml;
}

function docx_cover_page_xml($doc) {
    $xml = '';
    $xml .= '<w:p><w:pPr><w:pStyle w:val="SubtitleDoc"/></w:pPr><w:r><w:t xml:space="preserve">' . docx_escape($doc['university'] ?: '') . '</w:t></w:r></w:p>';
    if (!empty($doc['college'])) {
        $xml .= '<w:p><w:pPr><w:pStyle w:val="SubtitleDoc"/></w:pPr><w:r><w:t xml:space="preserve">' . docx_escape($doc['college']) . '</w:t></w:r></w:p>';
    }
    $xml .= '<w:p><w:pPr><w:pStyle w:val="Normal"/><w:spacing w:before="1600"/></w:pPr><w:r><w:t> </w:t></w:r></w:p>';
    $xml .= '<w:p><w:pPr><w:pStyle w:val="TitleDoc"/></w:pPr><w:r><w:t xml:space="preserve">' . docx_escape($doc['title']) . '</w:t></w:r></w:p>';
    $xml .= '<w:p><w:pPr><w:pStyle w:val="Normal"/><w:spacing w:before="1600"/><w:jc w:val="center"/></w:pPr><w:r><w:t> </w:t></w:r></w:p>';
    $xml .= '<w:p><w:pPr><w:pStyle w:val="SubtitleDoc"/></w:pPr><w:r><w:t xml:space="preserve">' . date('Y') . '</w:t></w:r></w:p>';
    return $xml;
}

function docx_toc_xml() {
    return '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>الفهرس</w:t></w:r></w:p>' .
        '<w:sdt><w:sdtPr><w:id w:val="1"/><w:docPartObj><w:docPartGallery w:val="Table of Contents"/></w:docPartObj></w:sdtPr>' .
        '<w:sdtContent>' .
        '<w:p><w:pPr><w:pStyle w:val="TOC1"/></w:pPr>' .
        '<w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r>' .
        '<w:r><w:instrText xml:space="preserve"> TOC \\o "1-1" \\h \\z \\u </w:instrText></w:r>' .
        '<w:r><w:fldChar w:fldCharType="separate"/></w:r>' .
        '<w:r><w:t>انقر بالزر الأيمن على الفهرس واختر "تحديث الحقل" لعرض العناوين وأرقام الصفحات.</w:t></w:r>' .
        '<w:r><w:fldChar w:fldCharType="end"/></w:r>' .
        '</w:p></w:sdtContent></w:sdt>';
}

function docx_section_xml($section) {
    $xml = '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t xml:space="preserve">' . docx_escape($section['section_title']) . '</w:t></w:r></w:p>';
    $xml .= docx_html_to_ooxml($section['content'] ?? '');
    return $xml;
}

/**
 * يحوّل HTML بسيط (من محرر المستندات: p/div/br/b/strong/i/em/ul/ol/li) إلى فقرات Word سليمة
 */
function docx_html_to_ooxml($html) {
    $html = trim((string)$html);
    if ($html === '') return '';

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $container = $dom->getElementsByTagName('div')->item(0);
    if (!$container) return '';

    $xml = '';
    foreach ($container->childNodes as $node) {
        $xml .= docx_node_to_paragraphs($node);
    }
    return $xml !== '' ? $xml : '<w:p><w:pPr><w:pStyle w:val="Normal"/></w:pPr></w:p>';
}

function docx_node_to_paragraphs($node) {
    $tag = strtolower($node->nodeName ?? '');

    if ($tag === 'p' || $tag === 'div') {
        $runs = docx_inline_runs($node);
        return $runs !== '' ? '<w:p><w:pPr><w:pStyle w:val="Normal"/></w:pPr>' . $runs . '</w:p>' : '';
    }
    if ($tag === 'ul' || $tag === 'ol') {
        $xml = '';
        $i = 1;
        foreach ($node->childNodes as $li) {
            if (strtolower($li->nodeName ?? '') !== 'li') continue;
            $bullet = $tag === 'ul' ? '•  ' : ($i++ . '.  ');
            $xml .= '<w:p><w:pPr><w:pStyle w:val="Normal"/><w:ind w:right="284"/></w:pPr>' .
                '<w:r><w:t xml:space="preserve">' . $bullet . '</w:t></w:r>' . docx_inline_runs($li) . '</w:p>';
        }
        return $xml;
    }
    if ($node->nodeType === XML_TEXT_NODE) {
        $text = trim($node->textContent);
        if ($text === '') return '';
        return '<w:p><w:pPr><w:pStyle w:val="Normal"/></w:pPr><w:r><w:t xml:space="preserve">' . docx_escape($text) . '</w:t></w:r></w:p>';
    }
    return '';
}

function docx_inline_runs($node) {
    $xml = '';
    foreach ($node->childNodes as $child) {
        $tag = strtolower($child->nodeName ?? '');
        if ($child->nodeType === XML_TEXT_NODE) {
            $t = $child->textContent;
            if ($t === '') continue;
            $xml .= '<w:r><w:t xml:space="preserve">' . docx_escape($t) . '</w:t></w:r>';
        } elseif ($tag === 'br') {
            $xml .= '<w:r><w:br/></w:r>';
        } elseif ($tag === 'b' || $tag === 'strong') {
            $xml .= '<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">' . docx_escape($child->textContent) . '</w:t></w:r>';
        } elseif ($tag === 'i' || $tag === 'em') {
            $xml .= '<w:r><w:rPr><w:i/></w:rPr><w:t xml:space="preserve">' . docx_escape($child->textContent) . '</w:t></w:r>';
        } else {
            $t = $child->textContent;
            if ($t !== '') $xml .= '<w:r><w:t xml:space="preserve">' . docx_escape($t) . '</w:t></w:r>';
        }
    }
    return $xml;
}

function docx_references_xml(array $references) {
    if (empty($references)) return '';
    $xml = '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>قائمة المراجع</w:t></w:r></w:p>';
    foreach ($references as $ref) {
        $citation = function_exists('format_citation_apa') ? format_citation_apa($ref) : ($ref['title'] ?? '');
        $xml .= '<w:p><w:pPr><w:pStyle w:val="RefEntry"/></w:pPr><w:r><w:t xml:space="preserve">' . docx_escape($citation) . '</w:t></w:r></w:p>';
    }
    return $xml;
}

function docx_document_xml($doc, array $sections, array $references) {
    $body = docx_cover_page_xml($doc);
    $body .= docx_toc_xml();
    foreach ($sections as $section) {
        $body .= docx_section_xml($section);
    }
    $body .= docx_references_xml($references);

    // خصائص القسم: حجم صفحة A4، هوامش، ترويسة/تذييل، اتجاه RTL للمستند كامل
    $sectPr = '<w:sectPr>' .
        '<w:headerReference w:type="default" r:id="rId3"/>' .
        '<w:footerReference w:type="default" r:id="rId4"/>' .
        '<w:pgSz w:w="11906" w:h="16838"/>' .
        '<w:pgMar w:top="1417" w:right="1417" w:bottom="1417" w:left="1417" w:header="708" w:footer="708" w:gutter="0"/>' .
        '<w:bidi/>' .
        '</w:sectPr>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" ' .
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<w:body>' . $body . $sectPr . '</w:body>' .
        '</w:document>';
}

/**
 * يبني ملف .docx كامل ويحفظه بالمسار المحدد.
 * $doc: ['title','university','college']
 * $sections: [['section_title','content'], ...]
 * $references: مصفوفة مراجع بنفس بنية جدول sources/report_references
 */
function build_docx_file(array $doc, array $sections, array $references, $destinationPath) {
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $zip->addFromString('[Content_Types].xml', docx_content_types());
    $zip->addFromString('_rels/.rels', docx_root_rels());
    $zip->addFromString('word/document.xml', docx_document_xml($doc, $sections, $references));
    $zip->addFromString('word/_rels/document.xml.rels', docx_document_rels());
    $zip->addFromString('word/styles.xml', docx_styles_xml());
    $zip->addFromString('word/settings.xml', docx_settings_xml());
    $zip->addFromString('word/header1.xml', docx_header_xml($doc['title'] ?? ''));
    $zip->addFromString('word/footer1.xml', docx_footer_xml());

    return $zip->close();
}
