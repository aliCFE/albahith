-- ترقية آمنة لقاعدة بيانات باحث — إضافة ميزة "منشئ التقارير والأوراق البحثية الذكي" ونشر الأبحاث
-- لا تمسح أو تعدّل أي بيانات موجودة، فقط تضيف الجداول الجديدة الناقصة
-- الصق هذا كامل بتبويب SQL في phpMyAdmin واضغط Go (بعد تطبيق baheth-migration.sql إن لم يكن مطبّقًا مسبقًا)

CREATE TABLE IF NOT EXISTS report_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_key VARCHAR(50) NOT NULL UNIQUE,
    label_ar VARCHAR(190) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    default_sections TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS report_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    topic VARCHAR(255) DEFAULT NULL,
    language ENUM('ar','en') NOT NULL DEFAULT 'ar',
    academic_level VARCHAR(50) DEFAULT NULL,
    university VARCHAR(190) DEFAULT NULL,
    college VARCHAR(190) DEFAULT NULL,
    department VARCHAR(190) DEFAULT NULL,
    academic_field VARCHAR(190) DEFAULT NULL,
    citation_style VARCHAR(20) NOT NULL DEFAULT 'apa7',
    target_pages INT DEFAULT NULL,
    target_words INT DEFAULT NULL,
    writing_level VARCHAR(50) DEFAULT NULL,
    audience VARCHAR(190) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('draft','outline_ready','writing','completed') NOT NULL DEFAULT 'draft',
    total_words INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS report_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    section_title VARCHAR(255) NOT NULL,
    content LONGTEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    target_word_count INT DEFAULT NULL,
    status ENUM('pending','generating','generated','edited','failed','disabled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES report_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS report_references (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    authors VARCHAR(500) DEFAULT NULL,
    pub_year VARCHAR(10) DEFAULT NULL,
    container_title VARCHAR(300) DEFAULT NULL,
    doi VARCHAR(255) DEFAULT NULL,
    url VARCHAR(500) DEFAULT NULL,
    verification_status ENUM('verified','needs_check') NOT NULL DEFAULT 'needs_check',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES report_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS report_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(20) NOT NULL,
    file_size INT NOT NULL,
    processing_status ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
    extracted_text LONGTEXT DEFAULT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES report_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT DEFAULT NULL,
    section_id INT DEFAULT NULL,
    provider VARCHAR(20) NOT NULL,
    model VARCHAR(100) NOT NULL,
    prompt_type VARCHAR(50) NOT NULL,
    input_tokens INT NOT NULL DEFAULT 0,
    output_tokens INT NOT NULL DEFAULT 0,
    estimated_cost_usd DECIMAL(10,5) NOT NULL DEFAULT 0,
    response_status ENUM('success','failed') NOT NULL DEFAULT 'success',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS published_papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- أنواع المستندات الافتراضية لمنشئ التقارير (تُدرج فقط إن لم تكن موجودة)
INSERT INTO report_types (type_key, label_ar, default_sections, sort_order) VALUES
('research_paper', 'ورقة بحثية', '["المقدمة","مشكلة البحث","أسئلة البحث","أهمية البحث","أهداف البحث","منهجية البحث","الدراسات السابقة","الإطار النظري","النتائج","المناقشة","الاستنتاجات","التوصيات"]', 0),
('academic_report', 'تقرير أكاديمي', '["المقدمة","خلفية الموضوع","المحتوى الرئيسي","التحليل","الخاتمة"]', 1),
('university_report', 'تقرير جامعي', '["المقدمة","المحتوى","الخاتمة"]', 2),
('training_report', 'تقرير تدريب', '["المقدمة","معلومات جهة التدريب","الأنشطة المنجزة","المهارات المكتسبة","الخاتمة والتوصيات"]', 3),
('lab_report', 'تقرير مختبر', '["الهدف من التجربة","الأدوات والمواد","خطوات العمل","النتائج","التحليل والمناقشة","الاستنتاج"]', 4),
('research_plan', 'خطة بحث', '["المقدمة","مشكلة البحث","أسئلة وفرضيات البحث","أهمية البحث","أهداف البحث","منهجية البحث المقترحة","الهيكل المقترح","جدول زمني تقريبي"]', 5),
('research_proposal', 'مقترح بحث', '["المقدمة","مشكلة البحث","أهداف البحث","الدراسات السابقة","المنهجية المقترحة","النتائج المتوقعة","الجدول الزمني والموارد"]', 6),
('literature_review', 'مراجعة أدبية', '["المقدمة","منهجية المراجعة","عرض الدراسات السابقة","التحليل المقارن","الفجوة البحثية","الخاتمة"]', 7),
('case_study', 'دراسة حالة', '["المقدمة","خلفية الحالة","وصف المشكلة","التحليل","الحلول المقترحة","النتائج والتوصيات"]', 8),
('research_summary', 'ملخص بحث', '["المقدمة","أهداف البحث","المنهجية","أبرز النتائج","الخاتمة"]', 9),
('thesis_chapter', 'فصل من رسالة ماجستير أو دكتوراه', '["المقدمة","محتوى الفصل الرئيسي","التحليل","الخلاصة"]', 10),
('professional_report', 'تقرير إداري أو مهني', '["الملخص التنفيذي","المقدمة","خلفية الموضوع","عرض البيانات","التحليل","النتائج","التوصيات"]', 11)
ON DUPLICATE KEY UPDATE label_ar = VALUES(label_ar);
