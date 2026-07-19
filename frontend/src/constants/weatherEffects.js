/** ตำแหน่งโซนบนแผนที่ไทย (เปอร์เซ็นต์ของ stage) */
export const MAP_ZONE_RECTS = {
  north: { left: 36, top: 4, width: 28, height: 24 },
  west: { left: 10, top: 26, width: 24, height: 28 },
  central: { left: 36, top: 30, width: 28, height: 22 },
  east: { left: 64, top: 26, width: 26, height: 30 },
  south: { left: 34, top: 56, width: 32, height: 30 },
}

/** region_id ใน DB (ไทย) → โซนบนแผนที่ */
export const TH_REGION_ZONE = {
  1: 'north',
  2: 'central',
  3: 'south',
  4: 'east',
}

export const ALL_MAP_ZONES = ['north', 'west', 'central', 'east', 'south']

/** ประเภท effect ต่อรหัสเหตุการณ์ */
export const EVENT_WEATHER_PROFILES = {
  flood_th: {
    type: 'flood',
    regions: ['central', 'south', 'east'],
    sfx: ['rain', 'thunder'],
  },
  drought_isan: {
    type: 'drought',
    regions: ['east'],
    sfx: ['wind'],
  },
  pest_outbreak: {
    type: 'swarm',
    regions: ALL_MAP_ZONES,
    sfx: ['alert'],
  },
  organic_fertilizer: {
    type: 'clear',
    regions: ALL_MAP_ZONES,
    sfx: ['chime'],
  },
  irrigation_north: {
    type: 'rain_light',
    regions: ['north'],
    sfx: ['rain'],
  },
  tornado: { type: 'storm', regions: ALL_MAP_ZONES, sfx: ['wind', 'thunder'] },
  drought_plains: { type: 'drought', regions: ALL_MAP_ZONES, sfx: ['wind'] },
  wildfire: { type: 'fire', regions: ['west'], sfx: ['alert'] },
  typhoon_us: { type: 'flood', regions: ALL_MAP_ZONES, sfx: ['rain', 'thunder'] },
  farm_bill: { type: 'clear', regions: ALL_MAP_ZONES, sfx: ['chime'] },
  trade_tariff: { type: 'clear', regions: ALL_MAP_ZONES, sfx: ['chime'] },
}

export function resolveWeatherProfile(event) {
  if (!event) {
    return { type: 'ambient', regions: ALL_MAP_ZONES, sfx: [] }
  }

  const profile = EVENT_WEATHER_PROFILES[event.code]
  if (!profile) {
    const fallbackType = event.event_type === 'disaster' ? 'storm' : 'clear'
    return { type: fallbackType, regions: ALL_MAP_ZONES, sfx: [] }
  }

  let regions = [...profile.regions]
  if (event.region_id && TH_REGION_ZONE[event.region_id]) {
    regions = [TH_REGION_ZONE[event.region_id]]
  }

  return { ...profile, regions }
}
