/** โซนบนแผนที่ไทย — ตรงกับ country_regions (id 1–4) */
export const THAILAND_REGION_KEYS = ['north', 'central', 'south', 'isan']

export const THAILAND_REGION_BY_ID = {
  1: 'north',
  2: 'central',
  3: 'south',
  4: 'isan',
}

export const THAILAND_REGION_BY_CODE = {
  north: 'north',
  central: 'central',
  south: 'south',
  isan: 'isan',
}

export const THAILAND_REGION_POSITIONS = {
  north: { x: 47, y: 17, label: 'ภาคเหนือ' },
  central: { x: 43, y: 45, label: 'ภาคกลาง' },
  south: { x: 47, y: 71, label: 'ภาคใต้' },
  isan: { x: 67, y: 38, label: 'ภาคอีสาน' },
}

/** กระจาย marker เป็นวงกลมรอบจุดกลางภูมิภาค — ไม่กระจุกตรงกลาง */
export function spreadMarkerOffset(indexInRegion, totalInRegion) {
  if (totalInRegion <= 1) {
    return { x: 0, y: 0 }
  }

  const radius = Math.min(16, 7 + totalInRegion * 1.15)
  const angle = (-Math.PI / 2) + ((2 * Math.PI * indexInRegion) / totalInRegion)
  return {
    x: Math.round(Math.cos(angle) * radius * 10) / 10,
    y: Math.round(Math.sin(angle) * radius * 10) / 10,
  }
}

export function clampMapPosition(x, y) {
  return {
    x: Math.min(91, Math.max(9, x)),
    y: Math.min(83, Math.max(13, y)),
  }
}

export function regionKeyForPlayer(player, fallbackIndex = 0) {
  const code = (player?.region_code || '').toString().toLowerCase().trim()
  if (THAILAND_REGION_BY_CODE[code]) {
    return THAILAND_REGION_BY_CODE[code]
  }

  const regionId = Number(player?.region_id)
  if (THAILAND_REGION_BY_ID[regionId]) {
    return THAILAND_REGION_BY_ID[regionId]
  }

  const regionText = [
    player?.region_name_th,
    player?.region_name_en,
    player?.region,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()

  if (regionText.includes('อีสาน') || regionText.includes('isan')) return 'isan'
  if (regionText.includes('เหนือ') || regionText.includes('north')) return 'north'
  if (regionText.includes('กลาง') || regionText.includes('central')) return 'central'
  if (regionText.includes('ใต้') || regionText.includes('south')) return 'south'

  return THAILAND_REGION_KEYS[fallbackIndex % THAILAND_REGION_KEYS.length]
}
