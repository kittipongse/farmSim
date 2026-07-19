import { withBase, uploadsBaseUrl } from '@/utils/paths'

export function profileImageUrl(image) {
  const value = typeof image === 'string' ? image.trim() : image
  if (!value || value === 'null') return null
  if (value.startsWith('http')) return value
  if (value.startsWith('/')) {
    if (value.startsWith('/api/')) return value
    if (value.includes('/uploads/')) {
      const name = value.split('/').pop()
      return `${uploadsBaseUrl().replace(/\/$/, '')}/${name}`
    }
    return value
  }
  const base = uploadsBaseUrl().replace(/\/$/, '')
  return `${base}/${value}`
}
