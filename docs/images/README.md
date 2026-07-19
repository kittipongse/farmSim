# ภาพประกอบคู่มือวิธีการเล่น

โฟลเดอร์นี้เก็บภาพที่ใช้ใน [`../คู่มือวิธีการเล่น.md`](../คู่มือวิธีการเล่น.md)

## แผนภาพ (สร้างสำหรับคู่มือ)

| ไฟล์ | ใช้ในหัวข้อ |
|------|------------|
| `game-flow.svg` | ขั้นตอนการเล่นทั้งเกม |
| `roles.svg` | บทบาท Dashboard vs มือถือ |
| `month-timeline.svg` | แถบ 12 เดือน |
| `month-simulation.svg` | ลำดับในแต่ละเดือน |
| `card-placement.svg` | ตัวอย่างวางการ์ด |
| `bonus-quiz.svg` | โบนัสทายปัญหา |

## ภาพจากเกม

| โฟลเดอร์/ไฟล์ | ที่มา |
|---------------|-------|
| `logo.png`, `dashboard.png`, `thai-usa.png` | `frontend/public/resource/images/` |
| `cards/*.svg` | การ์ดตัดสินใจ 8 ใบ |
| `icons/*.svg` | ไอคอนทรัพยากร |
| `events/*.svg` | เหตุการณ์ Breaking News |
| `regions/*.svg` | แผนที่ภูมิภาค |
| `mobile-ui.svg` | ตัวอย่าง UI มือถือ |

## การดูคู่มือพร้อมภาพ

เปิดไฟล์ `docs/คู่มือวิธีการเล่น.md` ใน Cursor / VS Code แล้วกด **Preview** (Ctrl+Shift+V)

## สร้าง PDF

```bash
node scripts/build-manual-pdf.js
```

ไฟล์ผลลัพธ์: `docs/คู่มือวิธีการเล่น.pdf`
