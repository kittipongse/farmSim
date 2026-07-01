<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import CountdownAnimation from '@/components/CountdownAnimation.vue'
import LobbyInvitePanel from '@/components/lobby/LobbyInvitePanel.vue'
import LobbyPlayerGrid from '@/components/lobby/LobbyPlayerGrid.vue'
import LobbyGameInfo from '@/components/lobby/LobbyGameInfo.vue'
import LobbyFooter from '@/components/lobby/LobbyFooter.vue'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { startDashboardPolling } from '@/services/pollingService'
import { getDashboard } from '@/services/dashboardService'
import { getCountries } from '@/services/countryService'
import { extendLobby, cancelRoom } from '@/services/gameRoomService'
import '@/assets/lobby.css'
import { assetUrl } from '@/utils/paths'

const route = useRoute()
const router = useRouter()
const gameRoom = useGameRoomStore()
const { room, players, countdownRemaining, lobbyRemaining } = storeToRefs(gameRoom)

const countries = ref([])
const LOBBY_TIMEOUT_SECONDS = 120

const roomCode = computed(() => route.params.roomCode?.toString().toUpperCase())
const lobbyPlayers = computed(() => [...players.value])

const playerDisplayCount = computed(() =>
  Math.max(lobbyPlayers.value.length, room.value?.player_count || 0)
)

const timerDisplay = computed(() => {
  const s = Math.min(LOBBY_TIMEOUT_SECONDS, Math.max(0, lobbyRemaining.value))
  const m = Math.floor(s / 60)
  const sec = s % 60
  return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
})

const countryDisplayName = computed(() => {
  const th = room.value?.country_name_th
  if (th && !/^\?+$/.test(th)) return th
  const match = countries.value.find((c) => c.id === room.value?.country_id)
  return match?.name_th || th || ''
})

const error = ref('')
const toast = ref('')
const showCountdown = ref(false)
const actionLoading = ref(false)
let stopPolling = null
let lobbyTickTimer = null
let toastTimer = null
let redirectingHome = false

function showToast(msg) {
  toast.value = msg
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

function isEmptyLobby(data) {
  const playerCount = Math.max(
    (data?.players || []).length,
    data?.room?.player_count ?? 0
  )
  return playerCount === 0
}

function redirectHome() {
  if (redirectingHome) return
  redirectingHome = true
  stopPolling?.()
  clearInterval(lobbyTickTimer)
  gameRoom.reset()
  router.replace('/')
}

function onPoll(data) {
  if (data?.room?.status === 'cancelled') {
    redirectHome()
    return
  }

  gameRoom.updateFromPoll(data)

  if (
    data?.room?.status === 'lobby' &&
    (data.lobby_remaining_seconds ?? 0) <= 0 &&
    isEmptyLobby(data)
  ) {
    redirectHome()
    return
  }

  if (data.room.status === 'countdown') {
    if (isEmptyLobby(data)) {
      redirectHome()
      return
    }
    showCountdown.value = true
  } else {
    showCountdown.value = false
  }

  if (data.room.status === 'planning') {
    showCountdown.value = false
    router.push({ name: 'dashboard-game', params: { roomCode: roomCode.value } })
  }

  if (data.room.status === 'simulating' || data.room.status === 'finished') {
    showCountdown.value = false
    router.push({ name: 'dashboard-game', params: { roomCode: roomCode.value } })
  }
}

async function loadDashboard() {
  const data = await getDashboard(roomCode.value)
  onPoll(data)
}

async function handleExtendTime() {
  if (!room.value || room.value.status !== 'lobby') return
  actionLoading.value = true
  try {
    const data = await extendLobby(roomCode.value)
    if (data.room) {
      gameRoom.updateFromPoll({
        room: data.room,
        players: players.value,
        lobby_remaining_seconds: data.lobby_remaining_seconds,
        countdown_remaining_seconds: countdownRemaining.value,
      })
    }
    showToast('ขยายเวลา +30 วินาที')
  } catch (e) {
    error.value = e.message
  } finally {
    actionLoading.value = false
  }
}

async function handleCancelRoom() {
  if (!confirm('ยกเลิกห้องนี้และกลับหน้าหลัก?')) return
  actionLoading.value = true
  try {
    await cancelRoom(roomCode.value)
    gameRoom.reset()
    router.push({ name: 'home' })
  } catch (e) {
    error.value = e.message
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  gameRoom.reset()
  try {
    countries.value = await getCountries()
    await loadDashboard()
  } catch (e) {
    error.value = e.message
  }

  stopPolling = startDashboardPolling(roomCode.value, onPoll, (e) => {
    error.value = e.message
  })

  lobbyTickTimer = setInterval(() => {
    if (redirectingHome || room.value?.status !== 'lobby') return
    if (lobbyRemaining.value > 0) {
      lobbyRemaining.value -= 1
    }
    if (lobbyRemaining.value <= 0 && playerDisplayCount.value === 0) {
      loadDashboard().finally(() => redirectHome())
    }
  }, 1000)
})

watch(roomCode, async (code, prev) => {
  if (!code || code === prev) return
  try {
    await loadDashboard()
  } catch (e) {
    error.value = e.message
  }
})

onUnmounted(() => {
  stopPolling?.()
  clearInterval(lobbyTickTimer)
  clearTimeout(toastTimer)
})
</script>

<template>
  <div class="lobby-screen">
    <CountdownAnimation
      :active="showCountdown"
      :remaining="countdownRemaining"
    />

    <header class="lobby-top">
      <div class="lobby-logo">
        <img :src="assetUrl('resource/images/logo.png')" alt="FarmSim EDU" />
      </div>

      <div class="lobby-title-block">
        <h1>ห้องรอผู้เล่น</h1>
        <p>เชิญเพื่อนเข้าร่วมห้องเกม</p>
      </div>

      <div class="lobby-top-actions">
        <button
          type="button"
          class="lobby-btn lobby-btn-extend"
          :disabled="actionLoading || room?.status !== 'lobby'"
          @click="handleExtendTime"
        >
          ⏱ ขยายเวลา +30 วินาที
        </button>
        <button
          type="button"
          class="lobby-btn lobby-btn-cancel"
          :disabled="actionLoading"
          @click="handleCancelRoom"
        >
          ✕ ยกเลิกห้อง
        </button>
        <button type="button" class="lobby-btn lobby-btn-icon" title="ตั้งค่า" @click="router.push({ name: 'home' })">
          ⚙
        </button>
      </div>
    </header>

    <div v-if="room" class="lobby-status-wrap">
      <div class="lobby-status-bar">
        <div class="lobby-stat">
          <span class="lobby-stat-label">ชื่อห้อง</span>
          <span class="lobby-stat-value">FS-{{ room.room_code }}</span>
        </div>
        <div v-if="room.status === 'lobby'" class="lobby-stat">
          <span class="lobby-stat-label">เกมจะเริ่มใน</span>
          <span class="lobby-stat-value timer">{{ timerDisplay }}</span>
        </div>
        <div v-else class="lobby-stat">
          <span class="lobby-stat-label">สถานะ</span>
          <span class="lobby-stat-value">{{ room.status === 'countdown' ? 'กำลังเริ่ม...' : room.status }}</span>
        </div>
        <div class="lobby-stat">
          <span class="lobby-stat-label">ผู้เล่น</span>
          <span class="lobby-stat-value">{{ playerDisplayCount }} / 8 คน</span>
        </div>
      </div>
    </div>

    <div v-if="error" class="alert alert-danger mx-3">{{ error }}</div>

    <div v-if="room" class="lobby-main">
      <LobbyInvitePanel
        :room-code="room.room_code"
        :pin="room.pin"
        @copied="showToast"
      />
      <LobbyPlayerGrid :players="lobbyPlayers" />
      <LobbyGameInfo
        :country-name-th="countryDisplayName"
        :country-name-en="room.country_name_en"
        :country-code="room.country_code"
        :players="lobbyPlayers"
      />
    </div>

    <div v-else class="lobby-loading">
      <div class="spinner-border text-light" role="status" />
      <p class="mt-3">กำลังโหลด Lobby...</p>
    </div>

    <LobbyFooter v-if="room" />

    <div v-if="toast" class="lobby-toast">{{ toast }}</div>
  </div>
</template>
