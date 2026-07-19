import { watch, onUnmounted } from 'vue'
import { pingBonusQuizActivity } from '@/services/cardService'

const PING_DEBOUNCE_MS = 700

export function useQuizActivity({ active, answered, playerId, onKeyAnswer }) {
  let lastPingAt = 0
  let bound = false

  function sendActivityPing() {
    if (!active.value || answered.value || !playerId) return
    const now = Date.now()
    if (now - lastPingAt < PING_DEBOUNCE_MS) return
    lastPingAt = now
    pingBonusQuizActivity(playerId).catch(() => {})
  }

  function handleActivity() {
    sendActivityPing()
  }

  function handleKeydown(event) {
    if (!active.value || answered.value) return
    if (event.ctrlKey || event.metaKey || event.altKey) return

    sendActivityPing()

    const key = event.key?.toLowerCase()
    if (key === 'a' || key === '1') {
      event.preventDefault()
      onKeyAnswer?.('A')
    } else if (key === 'b' || key === '2') {
      event.preventDefault()
      onKeyAnswer?.('B')
    }
  }

  function bindListeners() {
    if (bound) return
    bound = true
    window.addEventListener('keydown', handleKeydown)
    window.addEventListener('pointerdown', handleActivity)
    window.addEventListener('touchstart', handleActivity, { passive: true })
  }

  function unbindListeners() {
    if (!bound) return
    bound = false
    window.removeEventListener('keydown', handleKeydown)
    window.removeEventListener('pointerdown', handleActivity)
    window.removeEventListener('touchstart', handleActivity)
  }

  watch(
    active,
    (isActive) => {
      unbindListeners()
      lastPingAt = 0
      if (isActive && !answered.value) {
        bindListeners()
      }
    },
    { immediate: true }
  )

  watch(answered, (done) => {
    if (done) unbindListeners()
  })

  onUnmounted(unbindListeners)

  return { sendActivityPing }
}
