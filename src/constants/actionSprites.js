import plantSprite from '@/assets/sprites/Plant.png'
import waterSprite from '@/assets/sprites/Water.png'
import fertilizeSprite from '@/assets/sprites/Fertilize.png'
import spraySprite from '@/assets/sprites/Spray.png'
import walkSprite from '@/assets/sprites/walk.png'
import characterSprite from '@/assets/sprites/character.png'

const GRID_4X4 = {
  cols: 4,
  rows: 4,
  frames: 16,
  aspectRatio: '3 / 2',
}

export const ACTION_SPRITES = {
  IDLE: {
    ...GRID_4X4,
    src: characterSprite,
    frames: 1,
    nameTh: 'ตัวละครเกษตรกร',
    exactMatch: true,
  },
  WALK: {
    ...GRID_4X4,
    src: walkSprite,
    nameTh: 'เดิน',
    exactMatch: true,
  },
  PLANT: {
    ...GRID_4X4,
    src: plantSprite,
    nameTh: 'ปลูกพืช',
    exactMatch: true,
  },
  WATER: {
    ...GRID_4X4,
    src: waterSprite,
    nameTh: 'จัดการน้ำ',
    exactMatch: true,
  },
  FERTILIZE: {
    ...GRID_4X4,
    src: fertilizeSprite,
    nameTh: 'ใส่ปุ๋ย',
    exactMatch: true,
  },
  PROTECT: {
    ...GRID_4X4,
    src: spraySprite,
    nameTh: 'ป้องกันศัตรูพืช',
    exactMatch: true,
  },
  SOIL: {
    ...GRID_4X4,
    src: fertilizeSprite,
    nameTh: 'ปรับปรุงดิน',
    exactMatch: false,
  },
  HARVEST: {
    ...GRID_4X4,
    src: walkSprite,
    nameTh: 'เก็บเกี่ยว',
    exactMatch: false,
  },
  TECH: {
    ...GRID_4X4,
    src: walkSprite,
    nameTh: 'ลงทุนเทคโนโลยี',
    exactMatch: false,
  },
  TRADE: {
    ...GRID_4X4,
    src: walkSprite,
    nameTh: 'ขายผลผลิต',
    exactMatch: false,
  },
}

export function normalizeActionCode(code) {
  return code?.toString().trim().toUpperCase() || 'IDLE'
}

export function actionSpriteForCode(code) {
  const actionCode = normalizeActionCode(code)
  return ACTION_SPRITES[actionCode] || ACTION_SPRITES.IDLE
}

export function hasExactActionSprite(code) {
  const actionCode = normalizeActionCode(code)
  return !!ACTION_SPRITES[actionCode]?.exactMatch
}
