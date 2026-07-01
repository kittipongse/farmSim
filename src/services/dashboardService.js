import api, { unwrap } from './api'

export function getDashboard(roomCode) {
  return api.get(`/dashboard/${roomCode}`).then(unwrap)
}
