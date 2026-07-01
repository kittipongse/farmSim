import { withBase, uploadsBaseUrl } from '@/utils/paths'

export function profileImageUrl(image) {
  const value = typeof image === 'string' ? image.trim() : image
  if (!value || value === 'null') return null
  if (value.startsWith('http')) return value
  const base = uploadsBaseUrl().replace(/\/$/, '')
  return `${base}/${value}`
}
