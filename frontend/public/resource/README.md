# FarmSim EDU — ภาพตัวอย่าง (Resource Assets)

โฟลเดอร์นี้เก็บภาพตัวอย่างสำหรับพัฒนา UI และ PixiJS

## โครงสร้าง

```text
resource/
├── logo/           โลโก้เกม
├── cards/          การ์ด 8 ใบ (Key Decision Cards)
├── icons/          ไอคอนทรัพยากรผู้เล่น
├── events/         ไอคอน Breaking News / ภัยพิบัติ
├── regions/        ภาพประเทศ
└── ui/             Placeholder UI (avatar, dashboard)
```

## การใช้งาน

* Frontend: คัดลอกไป `frontend/src/assets/` หรืออ้างอิงจาก `/resource/` ตอนพัฒนา
* รูปแบบ: SVG (ขยายได้ไม่แตก เหมาะกับ Responsive)
* ขนาดการ์ดแนะนำ: 200×280 px

## รายการไฟล์

| โฟลเดอร์ | ไฟล์ |
|----------|------|
| cards | plant, water, fertilize, protect, harvest, tech, soil, trade |
| icons | coins, workforce, water, capability, soil-quality, tech-level |
| events | flood, drought, tornado, wildfire, typhoon, government-policy |
| regions | thailand, usa |
| ui | placeholder-avatar, placeholder-dashboard |
