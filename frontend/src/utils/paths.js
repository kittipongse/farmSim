/** Cloud API (znix.online) */
const DEFAULT_API_BASE_URL = 'https://znix.online/farmsim/api'

/** Base path for subdirectory deploy (e.g. /farmsim/) */
export function withBase(path = '') {
  const base = import.meta.env.BASE_URL || '/'
  const clean = String(path).replace(/^\//, '')
  return `${base}${clean}`
}

/**
 * API base URL
 * - dev: /api (Vite proxy → https://znix.online/farmsim/api)
 * - production: cloud URL โดยตรง
 */
export function apiBaseUrl() {
  if (import.meta.env.DEV) {
    return '/api'
  }
  const remote = import.meta.env.VITE_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL
  return remote.replace(/\/$/, '')
}

/** รูปโปรไฟล์ที่อัปโหลดบน server */
export function uploadsBaseUrl() {
  if (import.meta.env.DEV) {
    return '/api/uploads'
  }
  return `${apiBaseUrl()}/uploads`
}

export function assetUrl(path) {
  return withBase(path)
}
