import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useSessionStore = defineStore('session', () => {
  const playerId = ref(null)
  const roomCode = ref(null)
  const countryId = ref(null)
  const sessionToken = ref(null)
  const playerName = ref(null)
  const pin = ref(null)

  const isLoggedIn = computed(() => !!playerId.value && !!roomCode.value)

  function setSession({ player, room, pin: gamePin }) {
    playerId.value = player.id
    roomCode.value = room.room_code
    countryId.value = room.country_id ?? null
    sessionToken.value = player.session_token
    playerName.value = player.name
    pin.value = gamePin || room.pin
  }

  function setRoomOnly(room) {
    roomCode.value = room.room_code
    countryId.value = room.country_id ?? null
    pin.value = room.pin
  }

  function clear() {
    playerId.value = null
    roomCode.value = null
    countryId.value = null
    sessionToken.value = null
    playerName.value = null
    pin.value = null
    try {
      localStorage.removeItem('farmsim_session')
    } catch {
      // ignore storage errors (private mode, etc.)
    }
  }

  return {
    playerId,
    roomCode,
    countryId,
    sessionToken,
    playerName,
    pin,
    isLoggedIn,
    setSession,
    setRoomOnly,
    clear,
  }
})
