<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import PlayerAvatar from '@/components/PlayerAvatar.vue'
import { useSessionStore } from '@/stores/sessionStore'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { startLobbyPolling } from '@/services/pollingService'
import { getPlayer } from '@/services/playerService'
import { getRoomStatus } from '@/services/gameRoomService'
import { clearMobileSession } from '@/utils/sessionGuard'

const LOBBY_TIMEOUT_SECONDS = 120

const session = useSessionStore()
const gameRoom = useGameRoomStore()
const router = useRouter()

const player = ref(null)
const error = ref('')
let stopPolling = null

const lobbyDisplayTime = computed(() => {
  const s = Math.min(LOBBY_TIMEOUT_SECONDS, Math.max(0, gameRoom.lobbyRemaining))
  if (s >= 60) {
    const m = Math.floor(s / 60)
    const sec = s % 60
    return `${m}:${String(sec).padStart(2, '0')} นาที`
  }
  return `${s} วินาที`
})

function onPoll(data) {
  gameRoom.updateFromPoll(data)
  const status = data.room?.status
  if (status === 'simulating') {
    router.replace({ name: 'mobile-simulation' })
  } else if (status === 'planning') {
    router.replace({ name: 'mobile-plan-cards' })
  } else if (status === 'finished') {
    router.replace({ name: 'mobile-finished' })
  }
}

function leaveRoom() {
  stopPolling?.()
  clearMobileSession()
  router.push({ name: 'join' })
}

async function validateSessionRoom() {
  const status = await getRoomStatus(session.roomCode)
  const inRoom = (status.players || []).some((p) => p.id === session.playerId)
  if (!inRoom) {
    const code = session.roomCode
    clearMobileSession()
    await router.replace({ name: 'join-room', params: { roomCode: code }, query: { fresh: '1' } })
    return false
  }
  gameRoom.updateFromPoll(status)
  return true
}

onMounted(async () => {
  try {
    const data = await getPlayer(session.playerId)
    player.value = data.player
    const ok = await validateSessionRoom()
    if (!ok) return
    if (gameRoom.room?.status === 'planning') {
      await router.replace({ name: 'mobile-plan-cards' })
      return
    }
    if (gameRoom.room?.status === 'simulating') {
      await router.replace({ name: 'mobile-simulation' })
      return
    }
    if (gameRoom.room?.status === 'finished') {
      await router.replace({ name: 'mobile-finished' })
      return
    }
  } catch (e) {
    clearMobileSession()
    error.value = e.message || 'เซสชันหมดอายุ กรุณาเข้าร่วมใหม่'
    await router.replace({ name: 'join' })
    return
  }

  stopPolling = startLobbyPolling(session.roomCode, onPoll, (e) => {
    error.value = e.message
  })
})

onUnmounted(() => {
  stopPolling?.()
})
</script>

<template>
  <MobileLayout>
    <div class="text-center mb-4">
      <PlayerAvatar
        class="mb-2"
        :name="session.playerName || player?.name"
        :image="player?.profile_image"
        :size="80"
      />
      <h2 class="page-title mb-1">{{ session.playerName }}</h2>
      <p class="text-muted mb-0">{{ player?.region_name_th || 'ภูมิภาคของคุณ' }}</p>
      <p class="text-muted small mb-0">ห้อง: <strong>{{ session.roomCode }}</strong></p>
    </div>

    <div class="card card-farm p-4 text-center">
      <div v-if="gameRoom.room?.status === 'countdown'" class="py-3">
        <h4 class="text-warning">เกมกำลังจะเริ่ม!</h4>
        <div class="display-1 fw-bold text-success">{{ gameRoom.countdownRemaining }}</div>
      </div>
      <div v-else-if="gameRoom.room?.status === 'planning'" class="py-3">
        <h4 class="text-success">เกมเริ่มแล้ว!</h4>
        <p class="text-muted mb-2">กำลังไปหน้าวางการ์ด...</p>
        <div class="spinner-border text-success" role="status" />
      </div>
      <div v-else>
        <div class="spinner-border text-success mb-3" role="status" />
        <h5>รอผู้เล่นคนอื่น...</h5>
        <p class="text-muted mb-2">
          {{ gameRoom.players.length }} / 8 คน
        </p>
        <p v-if="gameRoom.room?.status === 'lobby'" class="mb-0">
          เหลือเวลา <strong class="text-danger">{{ lobbyDisplayTime }}</strong>
        </p>
      </div>
    </div>

    <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>

    <div class="alert alert-info mt-3 mb-2 small">
      ดูหน้าจอ Dashboard หลักเพื่อดู QR และรายชื่อผู้เล่นทั้งหมด
      <br />รหัสห้องบน Dashboard ต้องตรงกับ <strong>{{ session.roomCode }}</strong>
    </div>
    <button type="button" class="btn btn-outline-secondary w-100" @click="leaveRoom">
      ออกจากห้อง / เข้าห้องใหม่
    </button>
  </MobileLayout>
</template>
