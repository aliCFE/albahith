<?php
/**
 * معالج التثبيت - يعمل مرة واحدة فقط عند رفع المنصة لأول مرة.
 * يقوم بإنشاء ملف الإعدادات وجداول قاعدة البيانات وحساب المدير الأول.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$configPath = __DIR__ . '/config.php';
$already_installed = file_exists($configPath);

$errors = [];
$success = false;

if (!$already_installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_name = trim($_POST['admin_name'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_password2 = $_POST['admin_password2'] ?? '';
    $openai_api_key = trim($_POST['openai_api_key'] ?? '');
    $ai_model = trim($_POST['ai_model'] ?? '') ?: 'gpt-4o-mini';

    if ($db_name === '' || $db_user === '') {
        $errors[] = 'الرجاء تعبئة بيانات قاعدة البيانات (اسم القاعدة والمستخدم).';
    }
    if ($admin_name === '' || $admin_email === '' || $admin_password === '') {
        $errors[] = 'الرجاء إدخال اسم وبريد وكلمة مرور حساب المدير.';
    }
    if ($admin_email !== '' && !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'صيغة البريد الإلكتروني لحساب المدير غير صحيحة.';
    }
    if ($admin_password !== $admin_password2) {
        $errors[] = 'كلمتا مرور المدير غير متطابقتين.';
    }
    if (strlen($admin_password) > 0 && strlen($admin_password) < 6) {
        $errors[] = 'يجب أن تتكون كلمة مرور المدير من 6 أحرف على الأقل.';
    }

    $pdo = null;
    if (empty($errors)) {
        try {
            $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $errors[] = 'تعذر الاتصال بقاعدة البيانات. الرجاء التأكد من صحة البيانات: ' . $e->getMessage();
        }
    }

    if (empty($errors) && $pdo) {
        try {
            $pdo->exec('SET NAMES utf8mb4');

            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('student','instructor','admin') NOT NULL DEFAULT 'student',
                university VARCHAR(190) DEFAULT NULL,
                college VARCHAR(190) DEFAULT NULL,
                specialization VARCHAR(190) DEFAULT NULL,
                degree_level ENUM('bachelor','master','phd') DEFAULT 'bachelor',
                plan ENUM('free','pro') NOT NULL DEFAULT 'free',
                ai_requests_used INT NOT NULL DEFAULT 0,
                usage_reset_at DATE NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(190) NOT NULL DEFAULT 'محادثة جديدة',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                role ENUM('user','assistant') NOT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_filename VARCHAR(255) NOT NULL,
                file_type ENUM('pdf','docx') NOT NULL,
                ai_summary LONGTEXT DEFAULT NULL,
                status ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
                error_message VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS research_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                topic VARCHAR(255) NOT NULL,
                academic_field VARCHAR(190) NOT NULL,
                degree_level ENUM('bachelor','master','phd') NOT NULL DEFAULT 'bachelor',
                university_notes TEXT DEFAULT NULL,
                generated_content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS sources (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS document_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                document_id INT NOT NULL,
                role ENUM('user','assistant') NOT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS writing_drafts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                section_type VARCHAR(100) NOT NULL,
                topic VARCHAR(255) NOT NULL,
                instructions TEXT DEFAULT NULL,
                generated_content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS presentations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                topic VARCHAR(255) NOT NULL,
                outline_json LONGTEXT DEFAULT NULL,
                stored_filename VARCHAR(255) DEFAULT NULL,
                status ENUM('done','failed') NOT NULL DEFAULT 'done',
                error_message VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS university_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                university_name VARCHAR(190) NOT NULL UNIQUE,
                monthly_limit INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS colleges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL UNIQUE,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS specializations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL UNIQUE,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS payment_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                proof_filename VARCHAR(255) NOT NULL,
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                admin_note VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_at DATETIME DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS report_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type_key VARCHAR(50) NOT NULL UNIQUE,
                label_ar VARCHAR(190) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                default_sections TEXT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS report_documents (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS report_sections (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS report_references (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS report_files (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS ai_requests (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS published_papers (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // إعدادات افتراضية
            $stmtSet = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
                                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $stmtSet->execute(['k' => 'monthly_free_limit', 'v' => '50']);
            $stmtSet->execute(['k' => 'pro_monthly_limit', 'v' => '300']);
            $stmtSet->execute(['k' => 'payment_account_info', 'v' => '']);
            $stmtSet->execute(['k' => 'ai_model', 'v' => $ai_model]);
            $stmtSet->execute(['k' => 'ai_provider', 'v' => 'openai']);

            // أنواع المستندات الافتراضية لميزة منشئ التقارير والأوراق البحثية
            $defaultReportTypes = [
                ['research_paper', 'ورقة بحثية', ['المقدمة', 'مشكلة البحث', 'أسئلة البحث', 'أهمية البحث', 'أهداف البحث', 'منهجية البحث', 'الدراسات السابقة', 'الإطار النظري', 'النتائج', 'المناقشة', 'الاستنتاجات', 'التوصيات']],
                ['academic_report', 'تقرير أكاديمي', ['المقدمة', 'خلفية الموضوع', 'المحتوى الرئيسي', 'التحليل', 'الخاتمة']],
                ['university_report', 'تقرير جامعي', ['المقدمة', 'المحتوى', 'الخاتمة']],
                ['training_report', 'تقرير تدريب', ['المقدمة', 'معلومات جهة التدريب', 'الأنشطة المنجزة', 'المهارات المكتسبة', 'الخاتمة والتوصيات']],
                ['lab_report', 'تقرير مختبر', ['الهدف من التجربة', 'الأدوات والمواد', 'خطوات العمل', 'النتائج', 'التحليل والمناقشة', 'الاستنتاج']],
                ['research_plan', 'خطة بحث', ['المقدمة', 'مشكلة البحث', 'أسئلة وفرضيات البحث', 'أهمية البحث', 'أهداف البحث', 'منهجية البحث المقترحة', 'الهيكل المقترح', 'جدول زمني تقريبي']],
                ['research_proposal', 'مقترح بحث', ['المقدمة', 'مشكلة البحث', 'أهداف البحث', 'الدراسات السابقة', 'المنهجية المقترحة', 'النتائج المتوقعة', 'الجدول الزمني والموارد']],
                ['literature_review', 'مراجعة أدبية', ['المقدمة', 'منهجية المراجعة', 'عرض الدراسات السابقة', 'التحليل المقارن', 'الفجوة البحثية', 'الخاتمة']],
                ['case_study', 'دراسة حالة', ['المقدمة', 'خلفية الحالة', 'وصف المشكلة', 'التحليل', 'الحلول المقترحة', 'النتائج والتوصيات']],
                ['research_summary', 'ملخص بحث', ['المقدمة', 'أهداف البحث', 'المنهجية', 'أبرز النتائج', 'الخاتمة']],
                ['thesis_chapter', 'فصل من رسالة ماجستير أو دكتوراه', ['المقدمة', 'محتوى الفصل الرئيسي', 'التحليل', 'الخلاصة']],
                ['professional_report', 'تقرير إداري أو مهني', ['الملخص التنفيذي', 'المقدمة', 'خلفية الموضوع', 'عرض البيانات', 'التحليل', 'النتائج', 'التوصيات']],
            ];
            $stmtType = $pdo->prepare('INSERT INTO report_types (type_key, label_ar, default_sections, sort_order) VALUES (:k, :l, :s, :o)
                                        ON DUPLICATE KEY UPDATE label_ar = VALUES(label_ar)');
            foreach ($defaultReportTypes as $i => $rt) {
                $stmtType->execute(['k' => $rt[0], 'l' => $rt[1], 's' => json_encode($rt[2], JSON_UNESCAPED_UNICODE), 'o' => $i]);
            }

            // إنشاء حساب المدير (أو تحديثه إذا كان البريد موجودًا مسبقًا، مثلاً بعد استيراد نسخة احتياطية)
            $stmtUser = $pdo->prepare('SELECT id FROM users WHERE email = :e');
            $stmtUser->execute(['e' => $admin_email]);
            $existingAdmin = $stmtUser->fetch();
            $hash = password_hash($admin_password, PASSWORD_DEFAULT);
            if ($existingAdmin) {
                $stmtUpd = $pdo->prepare("UPDATE users SET name = :n, password_hash = :p, role = 'admin', is_active = 1 WHERE id = :id");
                $stmtUpd->execute(['n' => $admin_name, 'p' => $hash, 'id' => $existingAdmin['id']]);
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, usage_reset_at)
                                           VALUES (:n, :e, :p, 'admin', :reset)");
                $stmtIns->execute(['n' => $admin_name, 'e' => $admin_email, 'p' => $hash, 'reset' => date('Y-m-d')]);
            }

            if (empty($errors)) {
                $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                $basePath = rtrim($basePath, '/') . '/';

                $configContent = "<?php\n" .
                    "// تم إنشاء هذا الملف تلقائيًا بواسطة معالج التثبيت\n" .
                    "define('DB_HOST', " . var_export($db_host, true) . ");\n" .
                    "define('DB_NAME', " . var_export($db_name, true) . ");\n" .
                    "define('DB_USER', " . var_export($db_user, true) . ");\n" .
                    "define('DB_PASS', " . var_export($db_pass, true) . ");\n" .
                    "define('BASE_URL', " . var_export($basePath, true) . ");\n" .
                    "define('APP_NAME', 'باحث');\n" .
                    "define('OPENAI_API_KEY', " . var_export($openai_api_key, true) . ");\n" .
                    "define('ANTHROPIC_API_KEY', '');\n" .
                    "define('PEXELS_API_KEY', '');\n" .
                    "define('TELEGRAM_BOT_TOKEN', '');\n" .
                    "define('TELEGRAM_ADMIN_CHAT_ID', '');\n" .
                    "define('AI_MODEL', " . var_export($ai_model, true) . ");\n" .
                    "define('APP_INSTALLED', true);\n";

                if (@file_put_contents($configPath, $configContent) === false) {
                    $errors[] = 'تعذر إنشاء ملف الإعدادات (config.php). الرجاء التأكد من صلاحيات الكتابة على المجلد الرئيسي.';
                } else {
                    $success = true;
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'حدث خطأ أثناء إنشاء الجداول: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تثبيت منصة باحث</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="container page install-page">
    <div class="install-box">
        <h1 class="page-title">📚 تثبيت منصة باحث</h1>

        <?php if ($already_installed): ?>
            <div class="alert alert-info">
                المنصة مثبتة مسبقًا. إذا كنت ترغب بإعادة التثبيت من جديد، احذف ملف <code>config.php</code>
                من مجلد المشروع عبر مدير الملفات في Hostinger ثم أعد تحميل هذه الصفحة.
            </div>
            <a class="btn" href="login.php">الذهاب إلى صفحة تسجيل الدخول</a>
        <?php elseif ($success): ?>
            <div class="alert alert-success">
                تم التثبيت بنجاح! يمكنك الآن تسجيل الدخول بحساب المدير الذي أنشأته.
                <?php if ($openai_api_key === ''): ?>
                    <br>ملاحظة: لم تُدخل مفتاح OpenAI API — أضفه لاحقًا من لوحة تحكم المدير ← الإعدادات، وإلا لن تعمل ميزات الذكاء الاصطناعي.
                <?php endif; ?>
            </div>
            <a class="btn" href="login.php">تسجيل الدخول الآن</a>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <p class="hint">
                قبل المتابعة، أنشئ قاعدة بيانات MySQL من لوحة تحكم Hostinger (hPanel ← قواعد البيانات)، واحصل على
                مفتاح OpenAI API من <strong>platform.openai.com</strong> (يمكن إضافته لاحقًا إذا لم يكن جاهزًا الآن).
            </p>

            <form method="post" class="install-form">
                <h2>بيانات قاعدة البيانات (من Hostinger)</h2>
                <label>خادم قاعدة البيانات (DB Host)
                    <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label>اسم قاعدة البيانات
                    <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'baheth_db', ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label>مستخدم قاعدة البيانات
                    <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label>كلمة مرور قاعدة البيانات
                    <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <h2>حساب المدير الأول</h2>
                <label>الاسم
                    <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label>البريد الإلكتروني
                    <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label>كلمة المرور
                    <input type="password" name="admin_password" required minlength="6">
                </label>
                <label>تأكيد كلمة المرور
                    <input type="password" name="admin_password2" required minlength="6">
                </label>

                <h2>إعدادات الذكاء الاصطناعي (اختياري الآن)</h2>
                <label>مفتاح OpenAI API
                    <input type="text" name="openai_api_key" value="<?= htmlspecialchars($_POST['openai_api_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="sk-...">
                </label>
                <label>اسم الموديل
                    <input type="text" name="ai_model" value="<?= htmlspecialchars($_POST['ai_model'] ?? 'gpt-4o-mini', ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <button type="submit" class="btn">تثبيت المنصة الآن</button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
