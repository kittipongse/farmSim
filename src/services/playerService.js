import api, { unwrap } from './api'

export function getPlayer(playerId) {
  return api.get(`/players/${playerId}`).then(unwrap)
}

export function selectRegion(playerId, regionId) {
  return api.post(`/players/${playerId}/select-region`, { region_id: regionId }).then(unwrap)
}

export function uploadProfile(playerId, file) {
  const form = new FormData()
  form.append('profile', file)
  return api.post(`/players/${playerId}/upload-profile`, form).then(unwrap)
}
