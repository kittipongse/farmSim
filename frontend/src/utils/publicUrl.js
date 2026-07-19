let cachedBaseUrl = null

function normalizeBaseUrl(url) {
  return url.replace(/\/$/, '')
}

function isLocalHost(hostname) {
  return hostname === 'localhost' || hostname === '127.0.0.1'
}

export async function resolvePublicBaseUrl() {
  if (cachedBaseUrl) {
    return cachedBaseUrl
  }

  const envUrl = import.meta.env.VITE_PUBLIC_BASE_URL
  if (envUrl) {
    cachedBaseUrl = normalizeBaseUrl(envUrl)
    return cachedBaseUrl
  }

  const { protocol, hostname, port } = window.location
  if (!isLocalHost(hostname)) {
    cachedBaseUrl = `${protocol}//${hostname}${port ? `:${port}` : ''}`
    return cachedBaseUrl
  }

  try {
    const { apiBaseUrl } = await import('@/utils/paths')
    const response = await fetch(`${apiBaseUrl()}/health`)
    const json = await response.json()
    const suggested = json?.data?.suggested_frontend_url
    if (suggested) {
      cachedBaseUrl = normalizeBaseUrl(suggested)
      return cachedBaseUrl
    }
  } catch {
    // fall through to window.location.origin
  }

  cachedBaseUrl = window.location.origin
  return cachedBaseUrl
}
