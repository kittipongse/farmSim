const STORAGE_KEY = 'farmsim_session'

export default function piniaPersistPlugin({ store }) {
  if (store.$id !== 'session') return

  const saved = localStorage.getItem(STORAGE_KEY)
  if (saved) {
    try {
      store.$patch(JSON.parse(saved))
    } catch {
      localStorage.removeItem(STORAGE_KEY)
    }
  }

  store.$subscribe((_mutation, state) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      playerId: state.playerId,
      roomCode: state.roomCode,
      countryId: state.countryId,
      sessionToken: state.sessionToken,
      playerName: state.playerName,
      pin: state.pin,
    }))
  })
}
