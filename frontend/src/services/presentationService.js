import api, { unwrap } from './api'

export function submitPresentation(playerId) {
  return api.post(`/players/${playerId}/presentation/submit`).then(unwrap)
}

export function getPresentationStatus(playerId) {
  return api.get(`/players/${playerId}/presentation/status`).then(unwrap)
}

export function completePresentation(roomCode) {
  return api.post(`/rooms/${roomCode}/presentation/complete`).then(unwrap)
}
