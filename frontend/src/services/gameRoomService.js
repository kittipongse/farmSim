import api, { unwrap } from './api'

export function createRoom(countryId) {
  return api.post('/rooms/create', { country_id: Number(countryId) }).then(unwrap)
}

export function getRoom(roomCode) {
  return api.get(`/rooms/${roomCode}`).then(unwrap)
}

export function getRoomStatus(roomCode) {
  return api.get(`/rooms/${roomCode}/status`).then(unwrap)
}

export function joinRoom(roomCode, name, pin) {
  return api.post(`/rooms/${roomCode}/join`, { name, pin }).then(unwrap)
}

export function extendLobby(roomCode) {
  return api.post(`/rooms/${roomCode}/extend-lobby`).then(unwrap)
}

export function startGame(roomCode) {
  return api.post(`/rooms/${roomCode}/start`).then(unwrap)
}

export function cancelRoom(roomCode) {
  return api.post(`/rooms/${roomCode}/cancel`).then(unwrap)
}
