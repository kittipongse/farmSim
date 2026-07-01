import { useSessionStore } from '@/stores/sessionStore'
import { useGameRoomStore } from '@/stores/gameRoomStore'

export function normalizeRoomCode(code) {
  return code?.toString().trim().toUpperCase() || ''
}

export function clearMobileSession() {
  const session = useSessionStore()
  const gameRoom = useGameRoomStore()
  session.clear()
  gameRoom.reset()
}

export function ensureJoinRoomSession(targetRoomCode, { forceFresh = false } = {}) {
  const session = useSessionStore()
  const target = normalizeRoomCode(targetRoomCode)
  if (!target) return { switched: false }

  const current = normalizeRoomCode(session.roomCode)
  const shouldClear = forceFresh || (current && current !== target)

  if (shouldClear) {
    clearMobileSession()
    return { switched: true, target }
  }

  return { switched: false, target }
}
