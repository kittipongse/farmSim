import { getRoomStatus } from './gameRoomService'
import { getDashboard } from './dashboardService'

const LOBBY_INTERVAL = 2000

export function startLobbyPolling(roomCode, onUpdate, onError) {
  let active = true

  const poll = async () => {
    if (!active) return
    try {
      const data = await getRoomStatus(roomCode)
      onUpdate(data)
    } catch (err) {
      onError?.(err)
    }
    if (active) {
      setTimeout(poll, LOBBY_INTERVAL)
    }
  }

  poll()

  return () => {
    active = false
  }
}

export function startDashboardPolling(roomCode, onUpdate, onError) {
  let active = true

  const poll = async () => {
    if (!active) return
    try {
      const data = await getDashboard(roomCode)
      onUpdate(data)
    } catch (err) {
      onError?.(err)
    }
    if (active) {
      setTimeout(poll, LOBBY_INTERVAL)
    }
  }

  poll()

  return () => {
    active = false
  }
}
