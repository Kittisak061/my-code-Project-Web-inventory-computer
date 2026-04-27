<?php
// ============================================================
// DeviceHub — ตัวอย่างไฟล์ credentials
// คัดลอกไฟล์นี้เป็น db.config.php แล้วแก้ค่าให้ตรงกับระบบของคุณ
//   copy db.config.example.php db.config.php
// ============================================================

define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    'YOUR_MYSQL_PASSWORD_HERE');
define('DB_NAME',    'devicehub');
define('DB_CHARSET', 'utf8mb4');

// สร้าง secret แบบสุ่ม เช่น ใช้คำสั่ง:
//   php -r "echo bin2hex(random_bytes(32));"
define('APP_AUTH_SECRET', 'REPLACE_WITH_RANDOM_64_CHAR_STRING');
