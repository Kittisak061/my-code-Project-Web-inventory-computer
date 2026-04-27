<?php
// ============================================================
// DeviceHub — Local credentials
// !! อย่า commit ไฟล์นี้เข้า git !!
// ไฟล์นี้ถูก gitignore แล้ว — แก้ค่าให้ตรงกับ AppServ ของคุณ
// ============================================================

define('DB_HOST',         'localhost');
define('DB_USER',         'root');
define('DB_PASS',         '12345678');          // <-- เปลี่ยนตาม MySQL ของคุณ
define('DB_NAME',         'devicehub');
define('DB_CHARSET',      'utf8mb4');
define('APP_AUTH_SECRET', 'devicehub_local_2026_CHANGE_THIS_TO_SOMETHING_LONG_AND_RANDOM');
