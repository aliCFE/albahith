-- ترقية آمنة لقاعدة بيانات باحث — لا تمسح أو تعدّل أي بيانات موجودة، فقط تضيف الناقص
-- الصق هذا كامل بتبويب SQL في phpMyAdmin واضغط Go

CREATE TABLE IF NOT EXISTS university_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    university_name VARCHAR(190) NOT NULL UNIQUE,
    monthly_limit INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS colleges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    proof_filename VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    authors VARCHAR(500) DEFAULT NULL,
    pub_year VARCHAR(10) DEFAULT NULL,
    container_title VARCHAR(300) DEFAULT NULL,
    doi VARCHAR(255) DEFAULT NULL,
    url VARCHAR(500) DEFAULT NULL,
    source_type ENUM('journal','book','conference','website','other') NOT NULL DEFAULT 'journal',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    content LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS writing_drafts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    section_type VARCHAR(100) NOT NULL,
    topic VARCHAR(255) NOT NULL,
    instructions TEXT DEFAULT NULL,
    generated_content LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS presentations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    topic VARCHAR(255) NOT NULL,
    outline_json LONGTEXT NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- يوسّع خانة الدور لتشمل "تدريسي" (لا يؤثر على الحسابات الموجودة)
ALTER TABLE users MODIFY role ENUM('student','instructor','admin') NOT NULL DEFAULT 'student';

-- يضيف خانة الخطة (مجانية/مدفوعة) لو غير موجودة
ALTER TABLE users ADD COLUMN IF NOT EXISTS plan ENUM('free','pro') NOT NULL DEFAULT 'free' AFTER degree_level;

-- يضيف الإعدادات الجديدة فقط لو غير موجودة أصلًا (ما يلمس أي إعداد ضبطته أنت مسبقًا)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('pro_monthly_limit', '300'),
    ('payment_account_info', ''),
    ('zaincash_number', ''),
    ('superkey_number', ''),
    ('ai_provider', 'openai'),
    ('ai_model_openai', 'gpt-4o-mini'),
    ('ai_model_anthropic', 'claude-sonnet-5');
