# FarmSim EDU — ChatGPT Prompts สำหรับสร้างภาพการ์ด (20 ภาพ)

ใช้กับ ChatGPT (DALL·E / Image Generation) หรือเครื่องมือสร้างภาพอื่น  
ขนาดแนะนำ: **200×280 px** (อัตราส่วน 5:7 แนวตั้ง)  
โทนสีหลัก: `#2d6a4f` `#f4a261` `#9fd4f2`

---

## Prompt หลัก (วางก่อนทุกภาพ)

```
คุณเป็นนักออกแบบเกมการศึกษา ช่วยสร้างภาพการ์ดเกม "FarmSim EDU"
เกมจำลองการบริหารฟาร์มออนไลน์ สำหรับนักเรียน ม.3 ประเทศไทยและสหรัฐอเมริกา

สไตล์ชุดเดียวกันทุกภาพ:
- Flat illustration + soft gradient, สดใส อ่านง่าย ไม่สมจริงเกินไป
- โทนสีหลัก: เขียวฟาร์ม #2d6a4f, ทอง #f4a261, ฟ้าอ่อน #9fd4f2
- การ์ดแนวตั้ง ขอบมน radius 16px, เงาอ่อน
- มุมซ้ายบน: badge รหัส (ตัวพิมพ์ใหญ่)
- กลางการ์ด: ไอคอน/ภาพประกอบชัดเจน
- ล่างการ์ด: ชื่อไทยตัวหนา + ชื่ออังกฤษเล็กกว่า
- ไม่มี watermark, ไม่มีขอบขาวรอบภาพ
- เหมาะแสดงบนมือถือและ Dashboard โปรเจกเตอร์
- พื้นหลัง gradient ในกรอบการ์ดเท่านั้น

สร้างภาพตามรายละเอียดด้านล่าง 1 ภาพ
```

---

## สรุป 20 ภาพ

| # | ไฟล์ | โฟลเดอร์ | ประเภท |
|---|------|----------|--------|
| 1 | `plant.png` | `cards/` | Key Decision |
| 2 | `water.png` | `cards/` | Key Decision |
| 3 | `fertilize.png` | `cards/` | Key Decision |
| 4 | `protect.png` | `cards/` | Key Decision |
| 5 | `harvest.png` | `cards/` | Key Decision |
| 6 | `tech.png` | `cards/` | Key Decision |
| 7 | `soil.png` | `cards/` | Key Decision |
| 8 | `trade.png` | `cards/` | Key Decision |
| 9 | `flood.png` | `events/` | Breaking News |
| 10 | `drought.png` | `events/` | Breaking News |
| 11 | `tornado.png` | `events/` | Breaking News |
| 12 | `wildfire.png` | `events/` | Breaking News |
| 13 | `typhoon.png` | `events/` | Breaking News |
| 14 | `crop-disease.png` | `events/` | Breaking News |
| 15 | `government-policy.png` | `events/` | Breaking News |
| 16 | `farm-bill.png` | `events/` | Breaking News |
| 17 | `card-back.png` | `cards/ui/` | UI |
| 18 | `card-empty.png` | `cards/ui/` | UI |
| 19 | `card-selected.png` | `cards/ui/` | UI |
| 20 | `card-auto-month.png` | `cards/ui/` | UI |

---

## กลุ่มที่ 1 — Key Decision Cards (8 ภาพ)

### 01 — `plant.png` | PLANT

```
สร้างการ์ดเกม FarmSim EDU 200×280px
รหัส: PLANT
ชื่อ: ปลูกพืช / Plant Crop
คำอธิบาย: ระบุชื่อพืชและเดือนเริ่มปลูก
ภาพกลาง: ต้นกล้าสีเขียวในแปลงดิน มือถือเมล็ดพืช ท้องฟ้าสดใส
สีเน้น: เขียว #43a047, น้ำตาลดิน #8d6e63
```

### 02 — `water.png` | WATER

```
การ์ด 200×280px | WATER | จัดการน้ำ / Water Management
ภาพกลาง: หัวสปริงเกอร์รดน้ำ แม่น้ำเล็ก หยดน้ำ ระบบชลประทาน
สีเน้น: น้ำเงิน #2196f3, ฟ้า #64b5f6
```

### 03 — `fertilize.png` | FERTILIZE

```
การ์ด 200×280px | FERTILIZE | ใส่ปุ๋ย / Fertilize
ภาพกลาง: ถุงปุ๋ยเกษตร ต้นข้าวโตสูง ดินสีเข้มสด
สีเน้น: เขียว #2e7d32, น้ำตาล #6d4c41
```

### 04 — `protect.png` | PROTECT

```
การ์ด 200×280px | PROTECT | ป้องกันศัตรู / Protect
ภาพกลาง: โล่ป้องกันสีเขียว แมลงศัตรูพืชเล็กๆ (สไตล์การ์ตูน ไม่น่ากลัว) สเปรย์พ่นยา
สีเน้น: เขียว #66bb6a, ส้มอ่อน #ffb74d
```

### 05 — `harvest.png` | HARVEST

```
การ์ด 200×280px | HARVEST | เก็บเกี่ยว / Harvest
ภาพกลาง: เกี่ยวข้าว ตะกร้าผลผลิตเต็ม รถเกี่ยวเล็กในทุ่งนา
สีเน้น: ทอง #ffd54f, เขียว #81c784
```

### 06 — `tech.png` | TECH

```
การ์ด 200×280px | TECH | ลงทุนเทคโนโลยี / Technology
ภาพกลาง: โดรนเกษตรบินเหนือแปลง หน้าจอเซ็นเซอร์ IoT กราฟข้อมูลฟาร์ม
สีเน้น: น้ำเงินเทค #1976d2, ฟ้าเรืองแสง #42a5f5
```

### 07 — `soil.png` | SOIL

```
การ์ด 200×280px | SOIL | ปรับปรุงดิน / Soil Improvement
ภาพกลาง: ชั้นดินตัดขวางมีรากพืช ปุ๋ยหมัก จอบขุดดิน ดินสีเข้มสุขภาพดี
สีเน้น: น้ำตาล #795548, เขียวอ่อน #a5d6a7
```

### 08 — `trade.png` | TRADE

```
การ์ด 200×280px | TRADE | ขายผลผลิต / Trade
ภาพกลาง: ตลาดเกษตร รถบรรทุกสินค้า กราฟราคาขึ้น เหรียญทอง
สีเน้น: ส้ม #ff9800, ทอง #f4a261
```

---

## กลุ่มที่ 2 — Breaking News (8 ภาพ)

ใช้สไตล์เดียวกับการ์ดหลัก + แถบ **BREAKING NEWS** ด้านบน

### 09 — `flood.png` | น้ำท่วม

```
การ์ดเหตุการณ์ 200×280px | FLOOD | น้ำท่วม / Flood
แถบบน: BREAKING NEWS สีแดง
ภาพกลาง: ทุ่งน้ำท่วม บ้านชานเมือง ฝนตกหนัก
โทนสี: น้ำเงินเข้ม #1565c0, เทา #607d8b
```

### 10 — `drought.png` | ภัยแล้ง

```
การ์ดเหตุการณ์ | DROUGHT | ภัยแล้ง / Drought
แถบ BREAKING NEWS
ภาพกลาง: ดินแตกร้าว ต้นไม้เหี่ยว ดวงอาทิตย์แรง
โทนสี: ส้มแห้งแล้ง #ff8f00, น้ำตาล #a1887f
```

### 11 — `tornado.png` | พายุทอร์นาโด

```
การ์ดเหตุการณ์ | TORNADO | พายุทอร์นาโด / Tornado
ภาพกลาง: ทอร์นาโดสีเทา ฟาร์มอเมริกา รถเกี่ยวข้าว
โทนสี: เทาเข้ม #455a64, เขียวมืด
```

### 12 — `wildfire.png` | ไฟป่า

```
การ์ดเหตุการณ์ | WILDFIRE | ไฟป่า / Wildfire
ภาพกลาง: เปลวไฟป่า ควัน ต้นไม้ไหม้ (สไตล์การศึกษา ไม่รุนแรงเกินไป)
โทนสี: แดง #e53935, ส้ม #ff6f00
```

### 13 — `typhoon.png` | พายุไต้ฝุ่น

```
การ์ดเหตุการณ์ | TYPHOON | พายุไต้ฝุ่น / Typhoon
ภาพกลาง: พายุหมุนเหนือทะเล ฝนตกหนัก ต้นปาล์มโค้ง
โทนสี: น้ำเงินเข้ม #0d47a1, ฟ้า #29b6f6
เหมาะกับประเทศไทย
```

### 14 — `crop-disease.png` | โรคระบาด

```
การ์ดเหตุการณ์ | DISEASE | โรคระบาดพืช / Crop Disease
ภาพกลาง: ใบพืชมีจุดโรค แมลงศัตรู นักเกษตรกังวล
โทนสี: เขียวมัว #689f38, เหลืองเตือน #fbc02d
```

### 15 — `government-policy.png` | นโยบายรัฐสนับสนุน

```
การ์ดเหตุการณ์ | POLICY | นโยบายรัฐสนับสนุน / Government Support
ภาพกลาง: อาคารรัฐสภา ถุงปุ๋ยฟรี โดรนเกษตร มือจับมือเกษตรกร
โทนสี: น้ำเงินรัฐ #1565c0, เขียว #43a047 (โทนบวก)
```

### 16 — `farm-bill.png` | นโยบายการค้า

```
การ์ดเหตุการณ์ | FARM BILL | นโยบายการค้า / Farm Bill
ภาพกลาง: เอกสารกฎหมาย กราฟราคา เรือส่งออกสินค้าเกษตร ธงชาติ
โทนสี: น้ำเงิน #1976d2, ทอง #ffc107
```

---

## กลุ่มที่ 3 — UI / สถานะการ์ด (4 ภาพ)

### 17 — `card-back.png` | หลังการ์ด

```
การ์ดหลัง (Card Back) 200×280px ไม่มีข้อความชื่อการ์ด
ภาพกลาง: โลโก้ FarmSim EDU ใบไม้+ข้าวสาลี ลายเรขาคณิตฟาร์ม
พื้นหลัง gradient เขียว-ทอง
ใช้ซ่อนการ์ดก่อนเปิดเผย
```

### 18 — `card-empty.png` | ช่องว่าง

```
การ์ดช่องว่าง 200×280px | EMPTY SLOT
กรอบเส้นประสีขาวโปร่งใส
ไอคอนกลาง: เครื่องหมาย + ขนาดใหญ่
ข้อความ: "วางการ์ด" / Place Card
ใช้บนมือถือตอนวางแผนเดือน
```

### 19 — `card-selected.png` | การ์ดที่เลือกแล้ว

```
กรอบการ์ดไฮไลต์ 200×280px
กรอบนีออนสีทอง #ff9800 เรืองแสง 3 ชั้น
ไอคอน ✓ เล็กมุมขวาบน
พื้นกึ่งโปร่งใส
ใช้ทับการ์ดเมื่อผู้เล่นเลือกแล้ว
```

### 20 — `card-auto-month.png` | เดือนอัตโนมัติ

```
การ์ด 200×280px | AUTO | เดือนอัตโนมัติ / Auto Month
ภาพกลาง: นาฬิกาเฟือง ต้นข้าวโตเอง ลูกศรหมุนวน
ข้อความ: "ระบบดำเนินการอัตโนมัติ" / Auto Run
โทนสี: ฟ้า #90caf9, เทาอ่อน #eceff1
ใช้แสดง 4 เดือนที่เหลือในแผนปี (ไม่ต้องวางการ์ด)
```

---

## Prompt สร้างครบ 20 ภาพ (ทีละรอบ)

```
สร้างชุดภาพเกมการศึกษา FarmSim EDU จำนวน 20 ภาพ
สไตล์เดียวกันทั้งชุด: flat illustration สดใส ม.3 ขนาด 200×280px

กลุ่ม 1 — การ์ดตัดสินใจ 8 ใบ:
plant, water, fertilize, protect, harvest, tech, soil, trade

กลุ่ม 2 — Breaking News 8 ใบ (มีแถบ BREAKING NEWS):
flood, drought, tornado, wildfire, typhoon, crop-disease, government-policy, farm-bill

กลุ่ม 3 — UI 4 ใบ:
card-back, card-empty, card-selected (กรอบทอง), card-auto-month

แต่ละใบมี: badge รหัสมุมซ้ายบน, ภาพกลาง, ชื่อไทย+อังกฤษด้านล่าง
โทนสีหลัก #2d6a4f #f4a261 #9fd4f2

สร้างทีละ 4 ภาพ (5 รอบ) โดยรอบแรกเริ่มจาก plant, water, fertilize, protect
หลังรอบแรกให้ฉันยืนยันสไตล์ก่อนทำรอบถัดไป
```

### แผน 5 รอบ

| รอบ | ภาพ |
|-----|-----|
| 1 | plant, water, fertilize, protect |
| 2 | harvest, tech, soil, trade |
| 3 | flood, drought, tornado, wildfire |
| 4 | typhoon, crop-disease, government-policy, farm-bill |
| 5 | card-back, card-empty, card-selected, card-auto-month |

---

## โครงสร้างไฟล์หลัง export

```text
resource/
├── cards/
│   ├── plant.png
│   ├── water.png
│   ├── fertilize.png
│   ├── protect.png
│   ├── harvest.png
│   ├── tech.png
│   ├── soil.png
│   ├── trade.png
│   ├── PROMPTS.md          ← ไฟล์นี้
│   └── ui/
│       ├── card-back.png
│       ├── card-empty.png
│       ├── card-selected.png
│       └── card-auto-month.png
└── events/
    ├── flood.png
    ├── drought.png
    ├── tornado.png
    ├── wildfire.png
    ├── typhoon.png
    ├── crop-disease.png
    ├── government-policy.png
    └── farm-bill.png
```

หลังได้ PNG แล้ว คัดลอกไป `frontend/public/resource/` ใน path เดียวกันเพื่อใช้ในเกม

---

## หมายเหตุ

- โปรเจกต์มี SVG placeholder อยู่แล้วใน `resource/cards/` และ `resource/events/` — PNG ชุดนี้ใช้แทนเมื่อพร้อม Phase 2
- การ์ด 8 ใบหลักตรงกับตาราง `cards` ใน `database/seed.sql`
- Breaking News ในเกมสุ่ม 2–3 เหตุการณ์ต่อปี จากชุด 8 แบบนี้
