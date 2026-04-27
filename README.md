# DeviceHub

DeviceHub คือระบบภายในสำหรับงาน IT Asset Management, Email Mapping, User Administration, Backup Log และ Dashboard สำหรับทีม IT

## โครงสร้างหลัง Phase 2

- `api/` เก็บ PHP API ที่หน้าเว็บเรียกใช้งานจริง
- `assets/css/theme.css` เก็บ design system และ style หลักของเว็บ
- `assets/js/config.js` เก็บค่า `APP_ROOT`, `API_BASE`, endpoint map และ helper fetch
- `assets/js/shared.js` เก็บ session guard, sidebar, toast, modal และ helper กลาง
- `assets/js/dashboard.js` เก็บ logic dashboard realtime
- `assets/js/device-import.js` เก็บ logic import Excel ของ Device List
- `assets/js/device-power.js` เก็บ logic ตรวจสถานะเครื่อง
- `assets/vendor/xlsx.full.min.js` เก็บ SheetJS สำหรับ import/export Excel
- `docs/` เก็บเอกสารระบบ, diagram, handover guide และ manifest แต่ละ phase
- `_old/` เก็บไฟล์ซ้ำ ไฟล์ legacy และไฟล์ debug ที่ย้ายออกแบบไม่ลบถาวร

## หน้าเว็บหลัก

- `login.html` หน้าเข้าสู่ระบบ
- `dashboard.html` หน้า Dashboard
- `device-list.html` หน้า Device List
- `email.html` หน้า Email & Computer
- `email-list.html` หน้า KF Email List
- `users.html` หน้าจัดการผู้ใช้งาน
- `backup.html` หน้าประวัติ Backup / Audit
- `settings.html` หน้า Setting
- `export.html` หน้า Export

## Backend Routes

- `api/auth.php`
- `api/devices.php`
- `api/emails.php`
- `api/users.php`
- `api/backups.php`
- `api/dashboard.php`
- `api/repair.php`

## การติดตั้งแบบ local

1. วางโปรเจกต์ไว้ที่ `C:\AppServ\www\devicehub`
2. เปิด Apache และ MySQL ใน AppServ
3. สร้างฐานข้อมูลชื่อ `devicehub`
4. Import `devicehub.sql`
5. ตั้งค่าฐานข้อมูลที่ `api/db.config.php`
6. เปิด `http://localhost/devicehub/login.html`

## หมายเหตุสำหรับคนรับงานต่อ

- ห้ามลบ `_old` จนกว่าจะทดสอบครบทุก flow
- หน้า HTML ยังอยู่ที่ root เพื่อไม่ให้ URL เดิมพัง
- Phase ถัดไปควรค่อย ๆ แยก inline script/style จากแต่ละหน้าไปเป็น module ใน `assets/js` และ `assets/css`
- เอกสารรายการย้ายไฟล์อยู่ที่ `docs/PHASE1_CLEANUP_MANIFEST.md` และ `docs/PHASE2_STRUCTURE_MANIFEST.md`
