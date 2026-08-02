<?php
/**
 * توليد ملف PowerPoint (.pptx) حقيقي وصالح من الصفر عبر ZipArchive،
 * بدون أي مكتبة خارجية (لتوافق استضافة Hostinger بدون Composer).
 * $slides: مصفوفة عناصرها ['title' => '...', 'icon' => '🎯', 'image_query' => '...', 'bullets' => ['...', '...']]
 */
require_once __DIR__ . '/pexels.php';

function pptx_escape($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function pptx_content_types(int $slideCount) {
    $overrides = '';
    for ($i = 1; $i <= $slideCount; $i++) {
        $overrides .= '<Override PartName="/ppt/slides/slide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Default Extension="jpeg" ContentType="image/jpeg"/>' .
        '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>' .
        '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>' .
        '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>' .
        '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>' .
        $overrides .
        '</Types>';
}

function pptx_root_rels() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>' .
        '</Relationships>';
}

function pptx_presentation_xml(int $slideCount) {
    $sldIds = '';
    for ($i = 1; $i <= $slideCount; $i++) {
        $sldIds .= '<p:sldId id="' . (255 + $i) . '" r:id="rId' . (1 + $i) . '"/>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">' .
        '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>' .
        '<p:sldIdLst>' . $sldIds . '</p:sldIdLst>' .
        '<p:sldSz cx="12192000" cy="6858000" type="screen16x9"/>' .
        '<p:notesSz cx="6858000" cy="9144000"/>' .
        '</p:presentation>';
}

function pptx_presentation_rels(int $slideCount) {
    $rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>';
    for ($i = 1; $i <= $slideCount; $i++) {
        $rels .= '<Relationship Id="rId' . (1 + $i) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $i . '.xml"/>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
}

function pptx_slide_master() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">' .
        '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/></p:spTree></p:cSld>' .
        '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>' .
        '<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>' .
        '</p:sldMaster>';
}

function pptx_slide_master_rels() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>' .
        '</Relationships>';
}

function pptx_slide_layout() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">' .
        '<p:cSld name="Blank"><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/></p:spTree></p:cSld>' .
        '<p:clrMapOvr><a:overrideClrMapping bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/></p:clrMapOvr>' .
        '</p:sldLayout>';
}

function pptx_slide_layout_rels() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>' .
        '</Relationships>';
}

function pptx_theme() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Baheth Theme"><a:themeElements>' .
        '<a:clrScheme name="Baheth">' .
        '<a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>' .
        '<a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>' .
        '<a:dk2><a:srgbClr val="1F4D36"/></a:dk2>' .
        '<a:lt2><a:srgbClr val="E8F3EE"/></a:lt2>' .
        '<a:accent1><a:srgbClr val="2F6F4E"/></a:accent1>' .
        '<a:accent2><a:srgbClr val="1F4D36"/></a:accent2>' .
        '<a:accent3><a:srgbClr val="5C6B64"/></a:accent3>' .
        '<a:accent4><a:srgbClr val="E8F3EE"/></a:accent4>' .
        '<a:accent5><a:srgbClr val="1A4D8F"/></a:accent5>' .
        '<a:accent6><a:srgbClr val="B3261E"/></a:accent6>' .
        '<a:hlink><a:srgbClr val="1A4D8F"/></a:hlink>' .
        '<a:folHlink><a:srgbClr val="5C6B64"/></a:folHlink>' .
        '</a:clrScheme>' .
        '<a:fontScheme name="Baheth"><a:majorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface="Arial"/></a:majorFont><a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface="Arial"/></a:minorFont></a:fontScheme>' .
        '<a:fmtScheme name="Baheth">' .
        '<a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst>' .
        '<a:lnStyleLst><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="12700"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="19050"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst>' .
        '<a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst>' .
        '<a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst>' .
        '</a:fmtScheme>' .
        '</a:themeElements></a:theme>';
}

/**
 * عنصر صورة مضمّنة بالشريحة (إطار مستدير الحواف بحد ذهبي رفيع)
 */
function pptx_pic_xml($id, $relId, $x, $y, $w, $h) {
    return '<p:pic><p:nvPicPr><p:cNvPr id="' . $id . '" name="SlideImage"/><p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr><p:nvPr/></p:nvPicPr>' .
        '<p:blipFill><a:blip r:embed="' . $relId . '"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>' .
        '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $w . '" cy="' . $h . '"/></a:xfrm>' .
        '<a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 5000"/></a:avLst></a:prstGeom>' .
        '<a:ln w="25400"><a:solidFill><a:srgbClr val="D9A441"/></a:solidFill></a:ln>' .
        '<a:effectLst><a:outerShdw blurRad="90000" dist="30000" dir="5400000" rotWithShape="0"><a:srgbClr val="000000"><a:alpha val="25000"/></a:srgbClr></a:outerShdw></a:effectLst>' .
        '</p:spPr></p:pic>';
}

/**
 * انتقال بصري بين الشرائح (Transition) — يمنح العرض إحساسًا احترافيًا عند التشغيل
 */
function pptx_transition_xml($style) {
    $inner = '<p:fade/>';
    if ($style === 'push') {
        $inner = '<p:push dir="l"/>';
    } elseif ($style === 'wipe') {
        $inner = '<p:wipe dir="l"/>';
    }
    return '<p:transition spd="med">' . $inner . '</p:transition>';
}

/**
 * دائرة زخرفية شفافة (لعمق بصري بالخلفية) — بدون علاقة بالمحتوى، تكرر بكل الشرائح
 */
function pptx_decor_circle_xml($id, $x, $y, $diameter, $color, $alphaPct) {
    return '<p:sp><p:nvSpPr><p:cNvPr id="' . $id . '" name="Decor' . $id . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $diameter . '" cy="' . $diameter . '"/></a:xfrm>' .
        '<a:prstGeom prst="ellipse"><a:avLst/></a:prstGeom>' .
        '<a:solidFill><a:srgbClr val="' . $color . '"><a:alpha val="' . ($alphaPct * 1000) . '"/></a:srgbClr></a:solidFill>' .
        '<a:ln><a:noFill/></a:ln></p:spPr>' .
        '<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="ar-IQ"/></a:p></p:txBody></p:sp>';
}

/**
 * دائرة زخرفية بخط محيطي فقط (بدون تعبئة) — لمسة تصميم هندسي بالخلفية
 */
function pptx_decor_ring_xml($id, $x, $y, $diameter, $color, $alphaPct, $lineWidth = 25400) {
    return '<p:sp><p:nvSpPr><p:cNvPr id="' . $id . '" name="Ring' . $id . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $diameter . '" cy="' . $diameter . '"/></a:xfrm>' .
        '<a:prstGeom prst="ellipse"><a:avLst/></a:prstGeom>' .
        '<a:noFill/>' .
        '<a:ln w="' . $lineWidth . '"><a:solidFill><a:srgbClr val="' . $color . '"><a:alpha val="' . ($alphaPct * 1000) . '"/></a:srgbClr></a:solidFill></a:ln></p:spPr>' .
        '<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="ar-IQ"/></a:p></p:txBody></p:sp>';
}

/**
 * عنقود نقاط زخرفية صغيرة (نمط تصميم عصري شائع بالقوالب الاحترافية) بركن الشريحة
 */
function pptx_dot_cluster_xml($startId, $x, $y, $color, $alphaPct) {
    $xml = '';
    $dot = 76200;
    $gap = 152400;
    $id = $startId;
    for ($row = 0; $row < 3; $row++) {
        for ($col = 0; $col < 4 - $row; $col++) {
            $xml .= pptx_decor_circle_xml($id, $x + $col * $gap, $y + $row * $gap, $dot, $color, $alphaPct);
            $id++;
        }
    }
    return $xml;
}

/**
 * شارة أيقونة دائرية ملوّنة تعرض رمزًا تعبيريًا مرتبطًا بمحتوى الشريحة (يقترحه الذكاء الاصطناعي)
 */
function pptx_icon_badge_xml($id, $icon, $x, $y, $diameter, $bgColor, $emojiSz) {
    if ($icon === '') return '';
    return '<p:sp><p:nvSpPr><p:cNvPr id="' . $id . '" name="IconBadge"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $diameter . '" cy="' . $diameter . '"/></a:xfrm>' .
        '<a:prstGeom prst="ellipse"><a:avLst/></a:prstGeom>' .
        '<a:solidFill><a:srgbClr val="' . $bgColor . '"/></a:solidFill><a:ln><a:noFill/></a:ln></p:spPr>' .
        '<p:txBody><a:bodyPr wrap="square" anchor="ctr" lIns="0" tIns="0" rIns="0" bIns="0"/><a:lstStyle/><a:p><a:pPr algn="ctr"/>' .
        '<a:r><a:rPr lang="en-US" sz="' . $emojiSz . '" dirty="0"/><a:t>' . pptx_escape($icon) . '</a:t></a:r></a:p></p:txBody></p:sp>';
}

/**
 * شريحة العنوان الافتتاحية: خلفية متدرجة بألوان العلامة التجارية، أيقونة كبيرة مرتبطة بموضوع
 * العرض، عنوان كبير بالوسط، خط فاصل ذهبي، جملة وصفية فرعية، وعلامة "باحث" أسفل الشريحة
 */
function pptx_title_slide_xml($title, $subtitle, $icon = '') {
    $subtitleBlock = '';
    if ($subtitle !== '') {
        $subtitleBlock = '<p:sp><p:nvSpPr><p:cNvPr id="4" name="Subtitle"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
            '<p:spPr><a:xfrm><a:off x="1219200" y="3886200"/><a:ext cx="9753600" cy="914400"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>' .
            '<p:txBody><a:bodyPr wrap="square" anchor="t"/><a:lstStyle/><a:p><a:pPr rtl="1" algn="ctr"/>' .
            '<a:r><a:rPr lang="ar-IQ" sz="2000" dirty="0"><a:solidFill><a:srgbClr val="E8F3EE"/></a:solidFill></a:rPr><a:t>' . pptx_escape($subtitle) . '</a:t></a:r></a:p></p:txBody></p:sp>';
    }

    $iconBlock = $icon !== ''
        ? pptx_icon_badge_xml(10, $icon, 5334000, 990600, 1524000, 'FFFFFF', 6000)
        : '';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">' .
        '<p:cSld>' .
        '<p:bg><p:bgPr><a:gradFill rotWithShape="1"><a:gsLst>' .
        '<a:gs pos="0"><a:srgbClr val="2F6F4E"/></a:gs><a:gs pos="100000"><a:srgbClr val="1F4D36"/></a:gs>' .
        '</a:gsLst><a:lin ang="2700000" scaled="1"/></a:gradFill><a:effectLst/></p:bgPr></p:bg>' .
        '<p:spTree>' .
        '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>' .
        // دوائر وحلقات زخرفية شفافة للعمق البصري
        pptx_decor_circle_xml(11, -914400, -914400, 3200400, 'FFFFFF', 6) .
        pptx_decor_circle_xml(12, 10058400, 5029200, 2895600, 'D9A441', 10) .
        pptx_decor_ring_xml(31, 9601200, -685800, 2438400, 'FFFFFF', 18, 19050) .
        pptx_decor_ring_xml(32, -457200, 5486400, 1524000, 'D9A441', 22, 19050) .
        pptx_dot_cluster_xml(40, 685800, 5943600, 'FFFFFF', 20) .
        // شارة الأيقونة المرتبطة بموضوع العرض
        $iconBlock .
        // خط ذهبي زخرفي أعلى العنوان
        '<p:sp><p:nvSpPr><p:cNvPr id="5" name="AccentLine"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="4876800" y="2971800"/><a:ext cx="2438400" cy="28575"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="D9A441"/></a:solidFill></p:spPr>' .
        '<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="ar-IQ"/></a:p></p:txBody></p:sp>' .
        // العنوان الرئيسي
        '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="1219200" y="3200400"/><a:ext cx="9753600" cy="609600"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>' .
        '<p:txBody><a:bodyPr wrap="square" anchor="t"/><a:lstStyle/><a:p><a:pPr rtl="1" algn="ctr"/><a:r><a:rPr lang="ar-IQ" sz="4000" b="1" dirty="0"><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill></a:rPr><a:t>' . pptx_escape($title) . '</a:t></a:r></a:p></p:txBody>' .
        '</p:sp>' .
        $subtitleBlock .
        // علامة باحث أسفل الشريحة
        '<p:sp><p:nvSpPr><p:cNvPr id="6" name="Brand"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="1219200" y="6248400"/><a:ext cx="9753600" cy="365125"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>' .
        '<p:txBody><a:bodyPr wrap="square" anchor="t"/><a:lstStyle/><a:p><a:pPr rtl="1" algn="ctr"/><a:r><a:rPr lang="ar-IQ" sz="1200" dirty="0"><a:solidFill><a:srgbClr val="B7D6C6"/></a:solidFill></a:rPr><a:t>باحث — Baheth</a:t></a:r></a:p></p:txBody></p:sp>' .
        '</p:spTree></p:cSld>' .
        '<p:clrMapOvr><a:overrideClrMapping bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/></p:clrMapOvr>' .
        pptx_transition_xml('fade') .
        '</p:sld>';
}

/**
 * شريحة محتوى: شريط علوي أخضر بعنوان الشريحة، أيقونة مرتبطة بمحتوى الشريحة، خط فاصل ذهبي،
 * صورة حقيقية مرتبطة بمحتوى الشريحة (إن توفّرت) بجانب النقاط، ورقم الشريحة وانتقال بصري
 */
function pptx_content_slide_xml($title, array $bullets, $slideNumber, $totalSlides, $icon = '', $hasImage = false, $transitionStyle = 'fade') {
    $bulletParagraphs = '';
    foreach ($bullets as $bullet) {
        $bulletParagraphs .= '<a:p><a:pPr marL="342900" indent="-342900" rtl="1" algn="r"><a:spcBef><a:spcPts val="1400"/></a:spcBef><a:buClr><a:srgbClr val="D9A441"/></a:buClr><a:buFont typeface="Arial"/><a:buChar char="&#8226;"/></a:pPr>' .
            '<a:r><a:rPr lang="ar-IQ" sz="1900" dirty="0"><a:solidFill><a:srgbClr val="2B2B2B"/></a:solidFill></a:rPr><a:t>' . pptx_escape($bullet) . '</a:t></a:r></a:p>';
    }
    if ($bulletParagraphs === '') {
        $bulletParagraphs = '<a:p><a:pPr rtl="1" algn="r"/><a:endParaRPr lang="ar-IQ"/></a:p>';
    }

    $iconBlock = $icon !== ''
        ? pptx_icon_badge_xml(10, $icon, 342900, 190500, 762000, 'FFFFFF', 3200)
        : '';

    // عند وجود صورة: توضع يسار الشريحة، ويضيق عمود النقاط يمينها
    $imageBlock = $hasImage
        ? pptx_pic_xml(15, 'rId2', 685800, 1828800, 4267200, 3200400)
        : '';
    $bodyX = $hasImage ? 5334000 : 838200;
    $bodyW = $hasImage ? 6019800 : 10515600;

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">' .
        '<p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>' .
        '<p:spTree>' .
        '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>' .
        // دوائر وحلقات ونقاط زخرفية شفافة (عمق بصري بالخلفية)
        pptx_decor_circle_xml(13, -685800, 5257800, 2286000, '2F6F4E', 5) .
        pptx_decor_ring_xml(31, 11277600, 4800600, 1600200, 'D9A441', 15, 12700) .
        pptx_dot_cluster_xml(40, 10820400, 1524000, '2F6F4E', 10) .
        // الشريط العلوي الأخضر
        '<p:sp><p:nvSpPr><p:cNvPr id="7" name="HeaderBand"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="12192000" cy="1143000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="1F4D36"/></a:solidFill></p:spPr>' .
        '<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="ar-IQ"/></a:p></p:txBody></p:sp>' .
        // خط ذهبي رفيع أسفل الشريط
        '<p:sp><p:nvSpPr><p:cNvPr id="8" name="HeaderAccent"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="0" y="1143000"/><a:ext cx="12192000" cy="38100"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="D9A441"/></a:solidFill></p:spPr>' .
        '<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="ar-IQ"/></a:p></p:txBody></p:sp>' .
        // شارة الأيقونة المرتبطة بمحتوى الشريحة
        $iconBlock .
        // عنوان الشريحة (فوق الشريط)
        '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="1295400" y="228600"/><a:ext cx="10210800" cy="685800"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>' .
        '<p:txBody><a:bodyPr anchor="ctr"/><a:lstStyle/><a:p><a:pPr rtl="1" algn="r"/><a:r><a:rPr lang="ar-IQ" sz="2800" b="1" dirty="0"><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill></a:rPr><a:t>' . pptx_escape($title) . '</a:t></a:r></a:p></p:txBody>' .
        '</p:sp>' .
        // الصورة (إن وُجدت)
        $imageBlock .
        // محتوى النقاط
        '<p:sp><p:nvSpPr><p:cNvPr id="3" name="Body"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="' . $bodyX . '" y="1600200"/><a:ext cx="' . $bodyW . '" cy="4648200"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>' .
        '<p:txBody><a:bodyPr/><a:lstStyle/>' . $bulletParagraphs . '</p:txBody>' .
        '</p:sp>' .
        // خط ذهبي رفيع أسفل الشريحة (تناظر مع الشريط العلوي)
        '<p:sp><p:nvSpPr><p:cNvPr id="14" name="FooterAccent"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="838200" y="6629400"/><a:ext cx="1828800" cy="19050"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="D9A441"/></a:solidFill></p:spPr>' .
        '<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="ar-IQ"/></a:p></p:txBody></p:sp>' .
        // رقم الشريحة أسفل اليمين
        '<p:sp><p:nvSpPr><p:cNvPr id="9" name="PageNum"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
        '<p:spPr><a:xfrm><a:off x="10972800" y="6477000"/><a:ext cx="914400" cy="304800"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>' .
        '<p:txBody><a:bodyPr wrap="square" anchor="ctr"/><a:lstStyle/><a:p><a:pPr algn="ctr"/><a:r><a:rPr lang="en-US" sz="1100" dirty="0"><a:solidFill><a:srgbClr val="8A9690"/></a:solidFill></a:rPr><a:t>' . (int)$slideNumber . ' / ' . (int)$totalSlides . '</a:t></a:r></a:p></p:txBody></p:sp>' .
        '</p:spTree></p:cSld>' .
        '<p:clrMapOvr><a:overrideClrMapping bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/></p:clrMapOvr>' .
        pptx_transition_xml($transitionStyle) .
        '</p:sld>';
}

function pptx_slide_rels($imageFilename = '') {
    $imageRel = $imageFilename !== ''
        ? '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $imageFilename . '"/>'
        : '';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>' .
        $imageRel .
        '</Relationships>';
}

/**
 * يبني ملف .pptx كامل ويحفظه بالمسار المحدد. يرجع true/false
 */
function build_pptx_file(array $slides, $destinationPath) {
    if (empty($slides) || !class_exists('ZipArchive')) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $count = count($slides);

    $zip->addFromString('[Content_Types].xml', pptx_content_types($count));
    $zip->addFromString('_rels/.rels', pptx_root_rels());
    $zip->addFromString('ppt/presentation.xml', pptx_presentation_xml($count));
    $zip->addFromString('ppt/_rels/presentation.xml.rels', pptx_presentation_rels($count));
    $zip->addFromString('ppt/slideMasters/slideMaster1.xml', pptx_slide_master());
    $zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', pptx_slide_master_rels());
    $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', pptx_slide_layout());
    $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', pptx_slide_layout_rels());
    $zip->addFromString('ppt/theme/theme1.xml', pptx_theme());

    $transitions = ['fade', 'push', 'wipe'];
    $i = 1;
    foreach ($slides as $slide) {
        $title = $slide['title'] ?? '';
        $bullets = $slide['bullets'] ?? [];
        $icon = $slide['icon'] ?? '';
        $transitionStyle = $transitions[$i % count($transitions)];

        if ($i === 1) {
            $xml = pptx_title_slide_xml($title, $bullets[0] ?? '', $icon);
            $zip->addFromString('ppt/slides/_rels/slide' . $i . '.xml.rels', pptx_slide_rels());
        } else {
            $imageFilename = '';
            if (pexels_is_configured() && !empty($slide['image_query'])) {
                $photoUrl = pexels_search_photo_url($slide['image_query']);
                if ($photoUrl) {
                    $imageBytes = pexels_download_image($photoUrl);
                    if ($imageBytes) {
                        $imageFilename = 'image' . $i . '.jpeg';
                        $zip->addFromString('ppt/media/' . $imageFilename, $imageBytes);
                    }
                }
            }
            $xml = pptx_content_slide_xml($title, $bullets, $i, $count, $icon, $imageFilename !== '', $transitionStyle);
            $zip->addFromString('ppt/slides/_rels/slide' . $i . '.xml.rels', pptx_slide_rels($imageFilename));
        }
        $zip->addFromString('ppt/slides/slide' . $i . '.xml', $xml);
        $i++;
    }

    return $zip->close();
}
