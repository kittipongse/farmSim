import api, { unwrap } from './api'

export function getPlayerCards(playerId, year) {
  return api.get(`/players/${playerId}/cards/${year}`).then(unwrap)
}

export function assignCard(playerId, { year, card_code, month, crop_name }) {
  return api.post(`/players/${playerId}/cards/assign`, {
    year,
    card_code,
    month,
    crop_name,
  }).then(unwrap)
}

export function unassignCard(playerId, { year, month }) {
  return api.post(`/players/${playerId}/cards/unassign`, { year, month }).then(unwrap)
}

export function submitCards(playerId, year) {
  return api.post(`/players/${playerId}/cards/submit`, { year }).then(unwrap)
}

export function getRoomCardsStatus(roomCode) {
  return api.get(`/rooms/${roomCode}/cards/status`).then(unwrap)
}

export function moveCard(playerId, { year, from_month, to_month }) {
  return api.post(`/players/${playerId}/cards/move`, { year, from_month, to_month }).then(unwrap)
}

export function respondEvent(playerId, { event_id, action }) {
  return api.post(`/players/${playerId}/events/respond`, { event_id, action }).then(unwrap)
}

export function getCropPlans(playerId, year) {
  return api.get(`/players/${playerId}/crop-plans/${year}`).then(unwrap)
}
