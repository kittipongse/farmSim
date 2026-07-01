import api, { unwrap } from './api'

export function getSimulation(roomCode) {
  return api.get(`/rooms/${roomCode}/simulation`).then(unwrap)
}

export function getRanking(roomCode, year) {
  const q = year ? `?year=${year}` : ''
  return api.get(`/dashboard/${roomCode}/ranking${q}`).then(unwrap)
}

export function getEvents(roomCode, year) {
  const q = year ? `?year=${year}` : ''
  return api.get(`/dashboard/${roomCode}/events${q}`).then(unwrap)
}

export function getMarket(roomCode) {
  return api.get(`/dashboard/${roomCode}/market`).then(unwrap)
}
