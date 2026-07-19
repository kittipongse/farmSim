import { ref, watch, onMounted, onUnmounted } from 'vue'
import {
  resumeAudio,
  startTitleTheme,
  playBreakingNewsAlert,
  playThunder,
  playChime,
  playApplause,
  startRainAmbience,
  stopRainAmbience,
  startWindAmbience,
  stopWindAmbience,
  stopAllAmbience,
  disposeDashboardAudio,
  isMuted,
  setMuted,
} from '@/services/dashboardAudio'
import { resolveWeatherProfile } from '@/constants/weatherEffects'

export function useDashboardScene(options) {
  const audioReady = ref(false)
  const muted = ref(false)
  let lastBreakingEventId = null
  let lastRevealQuizId = null
  let detachAutoAudio = null

  async function enableAudio() {
    const ok = await resumeAudio()
    audioReady.value = ok
    if (ok) {
      startTitleTheme()
      syncAmbience(options.currentEvent?.value, options.breakingNews?.value)
    }
    return ok
  }

  // พยายามเล่นเสียงทันทีที่เข้าหน้า และถ้าเบราว์เซอร์บล็อก autoplay
  // ให้เริ่มเสียงเองทันทีที่ผู้ใช้ทำอะไรก็ได้ครั้งแรก (แตะ/คลิก/กดปุ่ม)
  async function armAutoAudio() {
    const ok = await enableAudio()
    if (ok || typeof window === 'undefined') return

    const kickstart = () => {
      enableAudio().then((ready) => {
        if (ready) removeAutoAudioListeners()
      })
    }
    const removeAutoAudioListeners = () => {
      window.removeEventListener('pointerdown', kickstart)
      window.removeEventListener('keydown', kickstart)
      window.removeEventListener('touchstart', kickstart)
      detachAutoAudio = null
    }

    window.addEventListener('pointerdown', kickstart)
    window.addEventListener('keydown', kickstart)
    window.addEventListener('touchstart', kickstart)
    detachAutoAudio = removeAutoAudioListeners
  }

  function toggleMute() {
    muted.value = !muted.value
    setMuted(muted.value)
  }

  function syncAmbience(event, breaking) {
    const profile = resolveWeatherProfile(event)
    stopAllAmbience()
    if (muted.value || !options.isSimulating?.value) return

    if (breaking && profile.sfx.includes('rain')) {
      startRainAmbience()
    } else if (profile.type === 'flood' || profile.type === 'rain_light' || profile.type === 'storm') {
      startRainAmbience()
    }

    if (breaking && (profile.sfx.includes('wind') || profile.type === 'drought')) {
      startWindAmbience()
    }
  }

  function onBreakingNews(event, breaking) {
    if (!breaking || !event) {
      lastBreakingEventId = null
      return
    }
    if (event.id === lastBreakingEventId) return
    lastBreakingEventId = event.id
    playBreakingNewsAlert()

    const profile = resolveWeatherProfile(event)
    if (profile.sfx.includes('chime')) {
      playChime()
    }
    syncAmbience(event, breaking)
  }

  function onQuizReveal(quiz) {
    if (!quiz?.revealing) {
      lastRevealQuizId = null
      return
    }
    if (quiz.id === lastRevealQuizId) return
    lastRevealQuizId = quiz.id
    playApplause()
  }

  watch(
    () => options.isSimulating?.value,
    (simulating) => {
      if (!audioReady.value) return
      startTitleTheme()
      if (simulating) {
        syncAmbience(options.currentEvent?.value, options.breakingNews?.value)
      } else {
        stopAllAmbience()
      }
    }
  )

  watch(
    () => [
      options.currentEvent?.value?.id,
      options.breakingNews?.value,
      options.isSimulating?.value,
    ],
    ([eventId, breaking, simulating]) => {
      if (!simulating) {
        stopAllAmbience()
        return
      }
      const event = options.currentEvent?.value
      onBreakingNews(event, breaking)
      if (!breaking) {
        syncAmbience(event, false)
      }
      if (!eventId) lastBreakingEventId = null
    }
  )

  watch(
    () => options.bonusQuiz?.value,
    (quiz) => {
      onQuizReveal(quiz)
    },
    { deep: true }
  )

  onMounted(() => {
    armAutoAudio()
  })

  onUnmounted(() => {
    if (detachAutoAudio) detachAutoAudio()
    disposeDashboardAudio()
  })

  return {
    audioReady,
    muted,
    enableAudio,
    toggleMute,
    isMuted,
  }
}
