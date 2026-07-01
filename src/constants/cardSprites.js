/** cardgame.png sprite sheet: 5 columns × 4 rows (1060×1484px) */
import { assetUrl } from '@/utils/paths'

export const CARD_SPRITE = {
  COLS: 5,
  ROWS: 4,
  SRC: assetUrl('resource/images/cardgame.png'),
  ASPECT: 212 / 371,
}

export const DECISION_CARDS = [
  { code: 'PLANT', spriteIndex: 0, nameTh: 'ปลูกพืช', nameEn: 'Plant Crop' },
  { code: 'WATER', spriteIndex: 1, nameTh: 'จัดการน้ำ', nameEn: 'Water Management' },
  { code: 'FERTILIZE', spriteIndex: 2, nameTh: 'ใส่ปุ๋ย', nameEn: 'Fertilize' },
  { code: 'PROTECT', spriteIndex: 3, nameTh: 'ป้องกันศัตรู', nameEn: 'Protect' },
  { code: 'HARVEST', spriteIndex: 4, nameTh: 'เก็บเกี่ยว', nameEn: 'Harvest' },
  { code: 'TECH', spriteIndex: 5, nameTh: 'ลงทุนเทคโนโลยี', nameEn: 'Technology' },
  { code: 'SOIL', spriteIndex: 6, nameTh: 'ปรับปรุงดิน', nameEn: 'Soil Improvement' },
  { code: 'TRADE', spriteIndex: 7, nameTh: 'ขายผลผลิต', nameEn: 'Trade' },
]

export const UI_SPRITES = {
  PLACE: 17,
  SELECTED: 18,
  AUTO: 19,
}

export const THAI_MONTHS = [
  'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
  'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.',
]

export function spriteIndexForCode(code) {
  const card = DECISION_CARDS.find((c) => c.code === code)
  return card?.spriteIndex ?? UI_SPRITES.PLACE
}

export function spritePosition(index) {
  const col = index % CARD_SPRITE.COLS
  const row = Math.floor(index / CARD_SPRITE.COLS)
  const x = CARD_SPRITE.COLS > 1 ? (col / (CARD_SPRITE.COLS - 1)) * 100 : 0
  const y = CARD_SPRITE.ROWS > 1 ? (row / (CARD_SPRITE.ROWS - 1)) * 100 : 0
  return { x, y }
}
