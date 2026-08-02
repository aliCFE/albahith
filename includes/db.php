<?php
/**
 * اتصال قاعدة البيانات (PDO)
 */

if (!file_exists(__DIR__ . '/../config.php')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    header('Location: ' . rtrim($scriptDir, '/') . '/install.php');
    exit;
}

require_once __DIR__ . '/../config.php';

function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('تعذر الاتصال بقاعدة البيانات. الرجاء التحقق من إعدادات الاستضافة أو إعادة التثبيت.');
        }
    }
    return $pdo;
}
