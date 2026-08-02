-- ترقية آمنة لجدول العروض التقديمية — تضيف حفظ سبب الفشل بدل ما تختفي المعلومة
-- لا تمسح أو تعدّل أي بيانات موجودة، فقط تضيف الأعمدة الناقصة
-- الصق هذا كامل بتبويب SQL في phpMyAdmin واضغط Go

ALTER TABLE presentations MODIFY outline_json LONGTEXT DEFAULT NULL;
ALTER TABLE presentations MODIFY stored_filename VARCHAR(255) DEFAULT NULL;
ALTER TABLE presentations ADD COLUMN IF NOT EXISTS status ENUM('done','failed') NOT NULL DEFAULT 'done' AFTER stored_filename;
ALTER TABLE presentations ADD COLUMN IF NOT EXISTS error_message VARCHAR(500) DEFAULT NULL AFTER status;
