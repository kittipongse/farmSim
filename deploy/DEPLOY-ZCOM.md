# Deploy FarmSim EDU บน Z.com

## 1. Build แพ็กเกจบนเครื่องพัฒนา

```powershell
cd C:\FarmSimEDU
powershell -ExecutionPolicy Bypass -File scripts\build-zcom.ps1
```

ได้ไฟล์:
- `deploy/zcom-upload/` — โฟลเดอร์สำหรับอัปโหลด
- `deploy/farmsim-edu-zcom.zip` — zip สำหรับอัปโหลดทีเดียว

## 2. สร้างฐานข้อมูลบน Z.com

1. เข้า **Z.com Control Panel** → **MySQL**
2. สร้าง Database + User (จดชื่อ DB, user, password)
3. เปิด **phpMyAdmin**
4. Import ไฟล์ `database/farmsim_edu.sql` เข้า DB **`cp393722_farmsim`**
   - เลือก charset **utf8mb4**
   - หรือ Import ผ่านแท็บ SQL แล้ววางไฟล์

## 3. ตั้งค่า PHP บนเซิร์ฟเวอร์

ในโฟลเดอร์ `public_html/config/` บน Z.com:

```bash
cp database.local.example.php database.local.php
```

แก้ `database.local.php`:

```php
return [
    'host' => 'localhost',        // ตามที่ Z.com แจ้ง (มักเป็น localhost)
    'port' => '3306',
    'database' => 'cp393722_farmsim',
    'username' => 'cp393722_farmsim',
    'password' => 'รหัสผ่าน_db',
    'charset' => 'utf8mb4',
];
```

## 4. อัปโหลดไฟล์

### วิธี A: File Manager (Z.com)

1. เข้า File Manager → `public_html`
2. ลบไฟล์เดิมใน `public_html` (ถ้าเป็นเว็บใหม่)
3. อัปโหลด **เนื้อหาใน** `deploy/zcom-upload/` ทั้งหมดเข้า `public_html`
   - ต้องมี `index.html`, `index.php`, `.htaccess`, `assets/`, `resource/`, `config/`, ...

### วิธี B: FTP (FileZilla / WinSCP)

| รายการ | ค่า |
|--------|-----|
| Host | `ftp.yourdomain.com` หรือที่ Z.com แจ้ง |
| User / Password | จาก Z.com |
| Remote path | `public_html` |
| Local path | `deploy/zcom-upload/` |

อัปโหลดทุกไฟล์และโฟลเดอร์เข้า `public_html`

## 5. สิทธิ์โฟลเดอร์

ตั้ง permission โฟลเดอร์ `uploads/` เป็น **755** หรือ **775** (ให้ PHP เขียนรูปโปรไฟล์ได้)

## 6. ทดสอบ

1. เปิด `https://yourdomain.com/` — หน้าแรก FarmSim EDU
2. เปิด `https://yourdomain.com/api/health` — ต้องได้ JSON `{"success":true,...}`
3. สร้างห้องเกม → Join จากมือถือ (ใช้โดเมนเดียวกัน QR จะถูกอัตโนมัติ)

## โครงสร้างบน public_html

```
public_html/
  index.html      ← Vue frontend
  index.php       ← PHP API (/api/...)
  .htaccess
  assets/
  resource/
  uploads/        ← writable
  config/
    database.local.php
  controllers/
  models/
  helpers/
```

## แก้ปัญหาที่พบบ่อย

| อาการ | แก้ |
|--------|-----|
| ภาษาไทยเป็น `??????` | Import DB ใหม่ด้วย utf8mb4 ใน phpMyAdmin |
| 404 หน้า /join/xxx | ตรวจว่ามี `.htaccess` และ mod_rewrite เปิด |
| API ไม่ทำงาน | ตรวจ `database.local.php` และ PHP 8+ |
| รูปการ์ดไม่ขึ้น | ตรวจโฟลเดอร์ `resource/images/` อัปโหลดครบ |
| QR ผิด URL | ใช้โดเมนจริง (ไม่ใช่ localhost) — ระบบ detect อัตโนมัติ |

## อัปเดตเวอร์ชันใหม่

1. รัน `scripts\build-zcom.ps1` ใหม่
2. Backup `public_html/config/database.local.php` และ `uploads/` บนเซิร์ฟเวอร์
3. อัปโหลดทับไฟล์ใหม่ (ยกเว้น `database.local.php` และ `uploads/`)
