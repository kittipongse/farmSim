# Cursor Development Guide

Project: FarmSim EDU
Stack: Vue 3 + PHP + MySQL
Mode: Online 100% | Local Development | Polling (No WebSocket)

---

## Goal

พัฒนาเกมการเรียนรู้แบบ Online 100% สำหรับผู้เล่น 8 คน โดยใช้มือถือเป็น Controller และ Dashboard เป็นจอแสดงผลกลาง ไม่มีครู ไม่มี Authentication

---

## Tech Stack

### Frontend

* Vue.js 3
* Composition API
* Vue Router
* Pinia (Session Persistence)
* Axios
* Bootstrap 5
* PWA
* PixiJS (Animation ตั้งแต่ Phase 1)

### Backend

* PHP 8+ (Plain PHP)
* REST API
* MySQL (`utf8mb4_unicode_ci`)
* Polling จาก Database (ไม่ใช้ WebSocket)
* ไม่มี JWT / Authentication

### Environment

* รันบน Local ก่อนทั้งหมด
* รองรับภาษาไทย UTF-8

---

## Development Rules

1. ใช้ Vue 3 Composition API เท่านั้น
2. แยก Component ให้ชัดเจน
3. ใช้ Pinia สำหรับ State Management และจำ session กรณีเครื่องหลุด
4. ใช้ Axios สำหรับ API Service
5. ห้ามเขียน API call กระจายใน Component
6. Backend ต้องคืนค่า JSON ทุกครั้ง
7. MySQL ใช้ `utf8mb4_unicode_ci`
8. ทุกตารางควรมี `created_at`, `updated_at`
9. ระบบต้องรองรับผู้เล่น 8 คนต่อ 1 ห้องเกม
10. Dashboard แสดงข้อมูลผ่าน Polling
11. Mobile Web ต้อง Responsive 100%
12. หลีกเลี่ยง Mock Data ในระบบจริง
13. ใช้ Seed Data สำหรับทดสอบได้
14. เขียนโค้ดให้อ่านง่าย ขยายต่อได้
15. ทุก Error ต้องมีข้อความตอบกลับที่เข้าใจง่าย
16. ห้ามเปลี่ยนกติกาเกมเอง — ยึด README.md และ cursor.md เป็นหลัก

---

## Folder Structure

```text
farmsim-edu/
├── frontend/
│   ├── src/
│   │   ├── assets/
│   │   ├── components/
│   │   ├── layouts/
│   │   ├── pages/
│   │   │   ├── dashboard/
│   │   │   ├── mobile/
│   │   │   └── create/
│   │   ├── router/
│   │   ├── stores/
│   │   ├── services/
│   │   ├── utils/
│   │   └── App.vue
│   └── package.json
│
├── backend/
│   ├── config/
│   ├── controllers/
│   ├── models/
│   ├── routes/
│   ├── uploads/
│   └── index.php
│
├── database/
│   ├── schema.sql
│   └── seed.sql
│
├── docs/
├── readme.md
└── cursor.md
```

---

## Frontend Pages

### Create / Lobby

* `/create` — สร้างห้อง (เลือกประเทศ)
* `/dashboard/lobby/:roomCode` — Lobby Dashboard

### Dashboard

* `/dashboard/lobby/:roomCode`
* `/dashboard/game/:roomCode`
* `/dashboard/result/:roomCode`

### Mobile

* `/join/:roomCode` — Join ด้วย PIN + ชื่อ
* `/mobile/profile` — ถ่ายรูปโปรไฟล์
* `/mobile/select-region` — เลือกภูมิภาค
* `/mobile/planning` — วางการ์ด 8 ใบ / 8 เดือน
* `/mobile/resources` — ดูทรัพยากร
* `/mobile/alerts` — แจ้งเตือน Breaking News / ย้ายการ์ด
* `/mobile/result` — ดูผลคะแนน

---

## Main Components

```text
DashboardLayout.vue
MobileLayout.vue
CreateRoomForm.vue
LobbyScreen.vue
QRCodePanel.vue
PlayerJoinList.vue
CountdownAnimation.vue       (PixiJS 3-2-1)
MonthlyTimeline.vue
BreakingNewsPanel.vue
BreakingNewsAnimation.vue    (PixiJS)
WeatherPanel.vue
MarketPanel.vue
RankingBoard.vue
PlayerStatusCard.vue
CardPlanner.vue
ResourcePanel.vue
RegionSelector.vue
DisasterResponsePanel.vue
```

---

## Pinia Stores

```text
useSessionStore        (จำ playerId, roomCode, ชื่อ — กรณีเครื่องหลุด)
useGameRoomStore
usePlayerStore
useDashboardStore
useCardStore
useMarketStore
useEventStore
useResourceStore
```

---

## API Service Rules

ให้สร้าง API Service แยกไฟล์ เช่น

```text
services/api.js
services/gameRoomService.js
services/playerService.js
services/cardService.js
services/dashboardService.js
services/marketService.js
services/eventService.js
services/pollingService.js
```

ห้ามเรียก Axios ตรง ๆ ในหน้า Vue

---

## Polling Strategy

* Frontend Poll จาก Database ผ่าน REST API
* Lobby: Poll ทุก **2 วินาที** (รายชื่อผู้เล่น, สถานะห้อง)
* เกม: Poll ทุก **2–3 วินาที** (เดือนปัจจุบัน, Ranking, เหตุการณ์)
* ช่วง Breaking News: Poll ทุก **1 วินาที** (รอผู้เล่นตอบสนอง)
* ใช้ `updated_at` หรือ `version` ในตาราง `game_rooms` เพื่อลด request ที่ไม่จำเป็น

---

## Backend API

### Game Room

```text
POST /api/rooms/create              (body: country_id → ได้ roomCode, pin)
GET  /api/rooms/{roomCode}          (สถานะห้อง, ผู้เล่น, ประเทศ)
GET  /api/rooms/{roomCode}/status   (สำหรับ Polling)
```

ห้องเริ่มเกมอัตโนมัติเมื่อครบ 8 คน หรือครบ 30 วินาที → นับถอยหลัง 3-2-1 → สถานะ `playing`

### Player

```text
POST /api/rooms/{roomCode}/join     (body: name → ได้ playerId)
POST /api/players/{id}/upload-profile
GET  /api/players/{id}
GET  /api/players/{id}/resources
POST /api/players/{id}/select-region  (body: region_id)
```

### Game Setup

```text
GET /api/countries
GET /api/countries/{id}/regions
GET /api/regions/{id}/suitable-crops   (ชื่อพืช, growth_months, crop_category, ราคาฐาน)
```

### Market

```text
GET  /api/market/{roomCode}/prices           (ราคาซื้อ-ขายปัจจุบัน)
GET  /api/market/{roomCode}/history          (ประวัติราคา)
POST /api/players/{id}/market/sell           (body: crop_id, amount — หรือผ่านการ์ด TRADE)
GET  /api/players/{id}/crop-plans/{year}     (แผนปลูกที่วางไว้)
POST /api/players/{id}/crop-plans/validate   (ตรวจระยะเวลารวม ≤ 12 เดือน)
```

### Cards

```text
GET  /api/players/{id}/cards/{year}
POST /api/players/{id}/cards/assign    (body: card_code, month, crop_name?)
POST /api/players/{id}/cards/move      (body: from_month, to_month — ตอนภัยพิบัติ)
GET  /api/rooms/{roomCode}/cards/status  (ทุกคนวางครบหรือยัง)
```

### Game Simulation

```text
GET  /api/rooms/{roomCode}/simulation   (เดือนปัจจุบัน, สถานะจำลอง)
POST /api/players/{id}/events/respond   (ตอบสนอง Breaking News)
```

Backend จัดการจำลองเดือนอัตโนมัติเมื่อผู้เล่นวางการ์ดครบทุกคน

### Dashboard

```text
GET /api/dashboard/{roomCode}
GET /api/dashboard/{roomCode}/ranking
GET /api/dashboard/{roomCode}/events
GET /api/dashboard/{roomCode}/market
```

---

## Database Tables

```text
game_rooms
players
countries
country_regions
region_crops              (พืชต่อภูมิภาค + growth_months + ราคาฐาน)
player_crop_plans         (แผนปลูกต่อผู้เล่นต่อปี)
cards                     (8 การ์ดหลัก)
player_year_cards         (การ์ดที่ผู้เล่นวางในแต่ละเดือน)
breaking_news_templates   (คลังเหตุการณ์)
room_year_events          (เหตุการณ์ที่สุ่มต่อห้องต่อปี)
player_event_responses    (การตอบสนองของผู้เล่น)
player_resources
player_scores
market_prices             (ราคาตลาดปัจจุบันต่อห้อง)
market_transactions       (ประวัติซื้อ-ขาย)
game_logs
```

### คอลัมน์สำคัญ

**game_rooms**

* `room_code`, `pin`, `country_id`
* `status` (lobby | countdown | planning | simulating | finished)
* `lobby_started_at`, `current_year`, `current_month`
* `player_count`

**players**

* `room_id`, `name`, `region_id`, `profile_image`
* `agricultural_capability` (default 100, 0–100)
* `session_token` (สำหรับ Pinia reconnect)

**player_resources**

* `player_id`, `year`, `month` (snapshot ต่อเดือน หรือแถวเดียวอัปเดตล่าสุด)
* `coins`, `workforce`, `water`
* `soil_quality`, `tech_level`, `stock_amount`
* `sustainability`, `env_impact`, `knowledge_score`

**player_scores**

* `player_id`, `room_id`, `year` (null = สรุปจบเกม)
* `production_score`, `resource_score`, `technology_score`
* `sustainability_score`, `risk_score`, `knowledge_score`
* `env_score`, `capability_score`
* `total_score`, `rank`

**region_crops**

* `region_id`, `name_th`, `name_en`
* `growth_months` (2–8), `crop_category` (short | medium | long)
* `base_buy_price`, `base_sell_price`
* `capability_bonus` (capability ที่ได้เมื่อขายสำเร็จ +1~3)

**player_crop_plans**

* `player_id`, `year`, `crop_id`, `plant_month`, `harvest_month`
* `status` (planned | growing | harvested | failed)
* `yield_amount`, `sold` (boolean)

**market_prices**

* `room_id`, `crop_id`, `buy_price`, `sell_price`, `supply`, `demand`
* `updated_at`

**market_transactions**

* `room_id`, `player_id`, `crop_id`, `type` (buy | sell)
* `amount`, `price`, `year`, `month`

**room_year_events**

* `room_id`, `year`, `month`, `template_id`
* `event_type` (disaster | government_policy)

---

## Game Rules

* ผู้เล่น 8 คนต่อห้อง
* 1 คน = 1 ฟาร์ม
* 1 ห้อง = 1 ประเทศ (ไทย หรือ อเมริกา)
* ผู้เล่นเลือกภูมิภาคภายในประเทศของห้อง
* ไม่มี Authentication — Join ด้วย Game PIN + ชื่อ
* เกมมี 5 ปี, 1 ปี = 12 เดือน
* ผู้เล่นได้รับ 8 Key Decision Cards ต่อปี (เหมือนกันทุกคน)
* วางการ์ดลง **8 เดือน** จาก 12 เดือน
* 4 เดือนที่เหลือ → Auto Run
* วางการ์ดปีละ 1 ครั้ง → ครบทุกคนแล้วระบบจำลองอัตโนมัติ
* Breaking News 2–3 เหตุการณ์ต่อปี (ภัยพิบัติ + นโยบายรัฐ)
* ภัยพิบัติ: ย้ายการ์ดได้โดยเสียทรัพยากร
* รับมือไม่ดี → Agricultural Capability ลดลง
* ไม่มีการแลกการ์ดระหว่างผู้เล่น
* ใช้ตลาดกลางซื้อ-ขายเท่านั้น
* ผลรวม `growth_months` ของพืชทุกชนิดที่ปลูกในปีเดียวกัน ≤ 12
* ห้ามปลูกพืชซ้อนทับเดือนเดียวกัน
* วางแผนปลูก (PLANT) ก่อน แล้ววางการ์ดสนับสนุน (WATER, FERTILIZE, PROTECT, SOIL)
* HARVEST เมื่อพืชโตครบ → ผลผลิตเข้าคลัง
* TRADE หรือขายอัตโนมัติ → +coins +capability จากตลาดกลาง
* Dashboard แสดงผลเท่านั้น ไม่มีปุ่มควบคุม
* Mobile ใช้ควบคุมการเล่น
* Lobby เริ่มเกมอัตโนมัติ: ครบ 8 คน หรือ 30 วินาที → นับถอยหลัง 3-2-1
* แต่ละเดือนจำลอง: แสดงเหตุการณ์ 30 วิ – 1 นาที ตามความสำคัญ

---

## Key Decision Cards

| รหัส | ชื่อ | หมายเหตุ |
|------|------|----------|
| PLANT | ปลูกพืช | พิมพ์ชื่อพืช + เดือนเริ่มปลูก (ตรวจ ≤ 12 เดือน/ปี) |
| WATER | จัดการน้ำ | การ์ดสนับสนุนระหว่างเติบโต |
| FERTILIZE | ใส่ปุ๋ย | การ์ดสนับสนุนระหว่างเติบโต |
| PROTECT | ป้องกันศัตรู | การ์ดสนับสนุนระหว่างเติบโต |
| HARVEST | เก็บเกี่ยว | เดือนที่พืชโตครบ → ผลผลิตเข้าคลัง |
| TECH | ลงทุนเทคโนโลยี | |
| SOIL | ปรับปรุงดิน | การ์ดสนับสนุนระหว่างเติบโต |
| TRADE | ขายผลผลิต | ขายคลังที่ตลาดกลาง → +coins +capability |

### ผลกระทบต่อทรัพยากร (Placeholder)

| การ์ด | ผลต่อทรัพยากร |
|-------|----------------|
| PLANT | -10 coins, -5 water, workforce 2; ผลขึ้นกับพืชถูก/ผิดภูมิภาค |
| WATER | -30 coins → +25 water |
| FERTILIZE | -40 coins, -5 sustainability → +ผลผลิต 20% |
| PROTECT | -25 coins, workforce 3 → ลดความเสียหายภัยพิบัติ |
| HARVEST | workforce 5 → stock เพิ่ม |
| TECH | -80 coins → tech_level +1 |
| SOIL | -35 coins → soil_quality +15, sustainability +5 |
| TRADE | ขาย stock → +coins ตามราคาตลาด + capability +1~3 |

---

## Crop Planning Rules

### ประเภทพืช

| crop_category | growth_months | กลยุทธ์ |
|---------------|---------------|---------|
| short | 2–3 | สะสมเหรียญเร็ว, หลีกเลี่ยงภัยพิบัติ |
| medium | 4–5 | สมดุล |
| long | 6–8 | กำไรสูง, เสี่ยงภัยพิบัติ |

### กฎการวางแผน

1. การ์ด PLANT ระบุ `crop_name` + `plant_month`
2. ระบบค้นหา `region_crops` → ได้ `growth_months`, `harvest_month = plant_month + growth_months - 1`
3. ตรวจผลรวม `growth_months` ทุกพืชในปี ≤ 12
4. ตรวจเดือนไม่ซ้อนทับ
5. บันทึกลง `player_crop_plans` สถานะ `planned`
6. ระหว่างจำลอง: การ์ดสนับสนุนในเดือนระหว่างเติบโตเพิ่ม yield
7. เดือน harvest: การ์ด HARVEST → `yield_amount` เข้า `stock_amount`
8. การ์ด TRADE หรือ auto-sell → `market_transactions` + อัปเดตราคาตลาด (supply/demand)

### สูตรผลผลิต

```text
yield = base_yield × (agricultural_capability / 100) × region_match_bonus × support_bonus
```

### สูตรขายตลาด

```text
coins_ได้ = yield × sell_price × market_modifier
capability_ได้ = capability_bonus (สูงกว่าถ้าพืชตรงภูมิภาค)
```

`market_modifier` ขึ้นกับ supply/demand ในห้อง + เหตุการณ์ Breaking News

---

## Market Rules

* ตลาดกลางเป็นช่องทางซื้อ-ขายเดียว ไม่แลกเปลี่ยนระหว่างผู้เล่น
* **ซื้อ:** เมล็ดพันธุ์ตอน PLANT, ปุ๋ย/อุปกรณ์ตอน FERTILIZE/PROTECT (หัก coins)
* **ขาย:** TRADE หรือ auto-sell หลัง HARVEST → +coins +capability
* ราคาปรับตาม supply/demand ในห้อง (8 คน) + weather + disaster + policy
* Supply สูง (ขายพืชชนิดเดียวกันเยอะ) → ราคาตก
* Demand สูง (นโยบายรัฐ/ภัยแล้ง) → ราคาพุ่ง

---

## Player Resources

ผู้เล่นแต่ละคน = ฟาร์ม 1 แห่ง เก็บใน `player_resources` (อัปเดตทุกเดือน) และ `players.agricultural_capability`

### ทรัพยากรหลัก

| ฟิลด์ | คอลัมน์ DB | เริ่มต้น | หมายเหตุ |
|-------|-----------|---------|----------|
| เหรียญลงทุน | `coins` | 500 (±ตามภูมิภาค) | ใช้การ์ด, ย้ายการ์ดภัยพิบัติ |
| แรงงาน | `workforce` | 10 | สูงสุด 20 |
| น้ำ | `water` | ตามภูมิภาค | 0–100 |
| ความสามารถด้านการเกษตร | `agricultural_capability` | 100 | อยู่ในตาราง `players`, 0–100 |

### สถานะฟาร์ม

| ฟิลด์ | คอลัมน์ DB | เริ่มต้น |
|-------|-----------|---------|
| คุณภาพดิน | `soil_quality` | ตามภูมิภาค (65–85) |
| ระดับเทคโนโลยี | `tech_level` | 0 (สูงสุด 5) |
| ผลผลิตในคลัง | `stock_amount` | 0 |
| ความยั่งยืน | `sustainability` | 50 |
| ผลกระทบสิ่งแวดล้อม | `env_impact` | 30 |
| คะแนนความรู้ | `knowledge_score` | 0 |

### ค่าเริ่มต้นตามภูมิภาค (Seed)

| ภูมิภาค | coins | water | soil_quality |
|---------|-------|-------|--------------|
| ไทย-เหนือ | 480 | 75 | 75 |
| ไทย-กลาง | 520 | 90 | 80 |
| ไทย-ใต้ | 500 | 85 | 70 |
| ไทย-อีสาน | 450 | 60 | 65 |
| US-Midwest | 550 | 70 | 85 |
| US-South | 500 | 80 | 75 |
| US-West | 600 | 50 | 60 |
| US-Great Plains | 480 | 55 | 80 |

### กฎแรงงาน (Placeholder)

* HARVEST เดือนที่มีผลผลิต → ต้องมี `workforce` ≥ 5 ไม่งั้นผลผลิต -30%
* PLANT → ใช้ workforce 2 คนชั่วคราว 1 เดือน
* ภัยพิบัติรุนแรง → อาจลด workforce 1–2 คน

### สูตรผลผลิต

```text
ผลผลิตจริง = ผลผลิตฐาน × (agricultural_capability / 100)
```

---

## Breaking News Rules

* ทุกปีสุ่ม **2–3 เหตุการณ์** กระจายในเดือนต่าง ๆ (ไม่ซ้ำเดือน)
* ประเภท: `disaster` หรือ `government_policy`
* อย่างน้อย 1 เหตุการณ์เป็นภัยพิบัติ
* กรองตาม `country_id` และ `region_id` ที่ได้รับผล
* บันทึกลง `room_year_events` ตอนเริ่มปี
* แสดงบน Dashboard ด้วย PixiJS Animation
* ช่วงแสดง: 30 วิ – 1 นาที

### Agricultural Capability Impact

| สถานการณ์ | ผล |
|-----------|-----|
| ภัยพิบัติรับมือไม่ดี | -10 ถึง -25 |
| นโยบายรัฐใช้โอกาสไม่ได้ | -5 ถึง -10 |
| รับมือดี (ภัยพิบัติ) | ไม่ลด |
| รับมือดี (นโยบายรัฐ) | +5 โบนัส |

### Disaster Card Move Cost (Placeholder)

| การกระทำ | ค่าใช้จ่าย |
|----------|-----------|
| ย้ายการ์ด 1 ใบ | -20 น้ำ, -15 เหรียญ |
| ย้ายการ์ดซ้ำในเดือนเดียวกัน | ค่าใช้จ่าย x2 |

---

## Seed Data (MVP)

### ประเทศ

* Thailand (4 ภูมิภาค: เหนือ, กลาง, ใต้, อีสาน)
* United States (4 ภูมิภาค: Midwest, South, West, Great Plains)

### พืชต่อภูมิภาค

ดูรายละเอียดใน `readme.md` — เก็บใน `region_crops` พร้อม:

* `name_th`, `name_en`
* `growth_months` (2–8)
* `crop_category` (short | medium | long)
* `base_buy_price`, `base_sell_price`
* `capability_bonus`

### ตัวอย่าง Seed พืช

| พืช | ภูมิภาค | growth_months | category |
|-----|---------|---------------|----------|
| ผัก | ทุกภูมิภาคไทย | 2 | short |
| สับปะรด | ใต้ | 3 | short |
| ข้าว | กลาง, อีสาน | 4 | medium |
| ข้าวโพด | เหนือ, Midwest | 4 | medium |
| ทุเรียน | กลาง, ใต้ | 6 | long |
| ยางพารา | ใต้ | 8 | long |
| Corn | Midwest | 4 | medium |
| Almonds | West | 8 | long |

### ค่าเริ่มต้นทรัพยากรต่อภูมิภาค

เก็บใน `country_regions` หรือ seed แยก: `default_coins`, `default_water`, `default_soil_quality` ตามตารางใน Player Resources

### Breaking News Templates

สร้างอย่างน้อย 5–8 template ต่อประเทศ (ภัยพิบัติ + นโยบายรัฐ)

---

## Dashboard Requirements

Dashboard ต้องแสดง:

* QR Code และ Game PIN
* Countdown 30 วินาที / จำนวนผู้เล่น (x/8)
* Animation นับถอยหลัง 3-2-1 (PixiJS)
* รายชื่อผู้เล่น + รูปโปรไฟล์ + ภูมิภาค
* เดือนและปีปัจจุบัน
* Breaking News + PixiJS Animation
* Weather
* Market
* Disaster
* Ranking
* Resource Summary (เหรียญ, แรงงาน, น้ำ, ความสามารถ)
* Player Status (เหรียญ, แรงงาน, Agricultural Capability, อันดับ, คะแนน)

---

## Mobile Requirements

Mobile Web ต้องรองรับ:

* Join Game (PIN + ชื่อ)
* ถ่ายรูปโปรไฟล์
* เลือกภูมิภาค
* วางการ์ด 8 ใบ / 8 เดือน (วางแผนปลูก + การ์ดสนับสนุน)
* พิมพ์ชื่อพืชตอนใช้การ์ด PLANT (ตรวจระยะเวลารวม ≤ 12 เดือน)
* ดูราคาตลาดกลาง
* ขายผลผลิต (TRADE)
* ย้ายการ์ดตอนภัยพิบัติ (เสียทรัพยากร)
* ดูทรัพยากร
* รับแจ้งเตือน Breaking News
* ดูผลคะแนน

---

## Animation Rules (PixiJS)

ใช้ PixiJS ตั้งแต่ Phase 1 แสดงเหตุการณ์ เช่น

* Countdown 3-2-1 (เริ่มเกม)
* Rain / Thunder
* Flood
* Drought
* Wildfire
* Tornado
* Typhoon
* Government Policy Announcement
* Market Boom / Market Crash

Animation ต้องไม่ทำให้ Dashboard อ่านยาก

---

## Scoring Rules

คะแนนรวม **0–1000** จาก 8 มิติ (MVP ใช้ placeholder ก่อน ปรับสูตรทีหลัง)

ห้ามใช้เงินเป็นคะแนนหลักเพียงอย่างเดียว

| มิติ | คอลัมน์ DB | สูตรง่าย (0–125 ต่อมิติ) |
|------|-----------|-------------------------|
| Production | `production_score` | `min(125, total_stock_sold / 10)` |
| Resource Management | `resource_score` | `125 - (500 - coins_สุดท้าย) / 5` ถ้า coins > 0 |
| Technology | `technology_score` | `tech_level × 25` |
| Sustainability | `sustainability_score` | `sustainability × 1.25` |
| Risk Management | `risk_score` | `125 - (จำนวนภัยรับมือแย่ × 20)` |
| Knowledge | `knowledge_score` | ปลูกถูกภูมิภาค +25/ครั้ง สูงสุด 125 |
| Environmental Impact | `env_score` | `(100 - env_impact) × 1.25` |
| Agricultural Capability | `capability_score` | `capability × 1.25` |

```text
total_score = ผลรวม 8 มิติ (สูงสุด 1000)
rank = เรียงตาม total_score
```

บันทึกลง `player_scores` ตอนจบปีและจบเกม

### UI แสดงทรัพยากร

**Mobile — ResourcePanel.vue**

```text
เหรียญ    {coins}
แรงงาน    {workforce} คน
น้ำ       progress bar {water}%
ความสามารถ progress bar {capability}%
ดิน       progress bar {soil_quality}%
เทคโนโลยี  stars {tech_level}/5
```

**Dashboard — PlayerStatusCard.vue**

```text
[รูป] {name} — {region}
Coins {coins} | คน {workforce} | Cap. {capability}
อันดับ #{rank}  คะแนน {total_score}
```

---

## Coding Style

* ใช้ชื่อตัวแปรภาษาอังกฤษ
* ชื่อ Component ใช้ PascalCase
* ชื่อไฟล์ Service ใช้ camelCase
* เขียน Function ให้สั้นและชัดเจน
* แยก Business Logic ออกจาก UI
* Validate ข้อมูลทุกครั้งก่อนบันทึก
* ใช้ Prepared Statement ใน PHP
* ห้ามต่อ SQL String ตรง ๆ จาก input ผู้ใช้
* รองรับชื่อพืชทั้งภาษาไทยและอังกฤษ

---

## MVP Priority

ให้พัฒนาเรียงลำดับดังนี้:

1. โครงสร้างโปรเจกต์ (frontend + backend + database)
2. Database Schema + Seed (ประเทศ, ภูมิภาค, พืช, การ์ด, Breaking News, ทรัพยากรเริ่มต้น)
3. สร้างห้องเกม (เลือกประเทศ) → PIN + QR
4. Lobby + Polling (รายชื่อผู้เล่น, countdown 30 วิ)
5. Join Player (PIN + ชื่อ) + Pinia Session
6. Upload Profile + เลือกภูมิภาค
7. Auto-start (8 คน / 30 วิ) + PixiJS Countdown 3-2-1
8. วางการ์ด 8 ใบ / 8 เดือน + วางแผนปลูก (ตรวจ ≤ 12 เดือน)
9. จำลองเดือนอัตโนมัติ + Breaking News + ตลาดกลาง
10. ย้ายการ์ดตอนภัยพิบัติ
11. Dashboard Ranking + Player Resources + ราคาตลาด
12. PixiJS Animation เหตุการณ์พื้นฐาน
13. Final Result (placeholder score 8 มิติ)

---

## Important Instruction for Cursor.ai

เมื่อสร้างโค้ดใหม่ ให้ยึด `readme.md` และ `cursor.md` เป็นหลัก
ห้ามเปลี่ยนกติกาเกมเอง
ถ้าต้องเพิ่ม Feature ให้แยกเป็น Component หรือ Service ใหม่
อย่าเขียนทุกอย่างรวมในไฟล์เดียว
ระบบต้องพร้อมต่อยอดเพิ่มประเทศ ภูมิภาค พืช และเหตุการณ์ในอนาคต
