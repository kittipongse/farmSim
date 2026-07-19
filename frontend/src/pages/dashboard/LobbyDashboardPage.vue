<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import LobbyInvitePanel from '@/components/lobby/LobbyInvitePanel.vue'
import LobbyPlayerGrid from '@/components/lobby/LobbyPlayerGrid.vue'
import LobbyGameInfo from '@/components/lobby/LobbyGameInfo.vue'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { startDashboardPolling } from '@/services/pollingService'
import { getDashboard } from '@/services/dashboardService'
import { getCountries } from '@/services/countryService'
import { startGame, cancelRoom } from '@/services/gameRoomService'
import '@/assets/lobby.css'
import { assetUrl } from '@/utils/paths'

const route = useRoute()
const router = useRouter()
const gameRoom = useGameRoomStore()
const { room, players } = storeToRefs(gameRoom)

const countries = ref([])

const roomCode = computed(() => route.params.roomCode?.toString().toUpperCase())
const lobbyPlayers = computed(() => [...players.value])

const playerDisplayCount = computed(() =>
  Math.max(lobbyPlayers.value.length, room.value?.player_count || 0)
)

const canStartGame = computed(() => {
  const status = room.value?.status
  const inLobby = status === 'lobby' || status === 'countdown'
  return inLobby && playerDisplayCount.value >= 1
})

const countryDisplayName = computed(() => {
  const th = room.value?.country_name_th
  if (th && !/^\?+$/.test(th)) return th
  const match = countries.value.find((c) => c.id === room.value?.country_id)
  return match?.name_th || th || ''
})

const error = ref('')
const toast = ref('')
const actionLoading = ref(false)
let stopPolling = null
let toastTimer = null
let redirectingHome = false

function showToast(msg) {
  toast.value = msg
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

function redirectHome() {
  if (redirectingHome) return
  redirectingHome = true
  stopPolling?.()
  gameRoom.reset()
  router.replace('/')
}

function goToGameDashboard() {
  router.push({ name: 'dashboard-game', params: { roomCode: roomCode.value } })
}

function onPoll(data) {
  if (data?.room?.status === 'cancelled') {
    redirectHome()
    return
  }

  gameRoom.updateFromPoll(data)

  const status = data.room?.status
  if (status === 'planning' || status === 'simulating' || status === 'finished') {
    goToGameDashboard()
  }
}

async function loadDashboard() {
  const data = await getDashboard(roomCode.value)
  onPoll(data)
}

async function handleStartGame() {
  if (!canStartGame.value) return
  actionLoading.value = true
  error.value = ''
  try {
    const data = await startGame(roomCode.value)
    if (data.room) {
      gameRoom.updateFromPoll({
        room: data.room,
        players: data.players || players.value,
      })
    }
    showToast('เริ่มเกมแล้ว')
    goToGameDashboard()
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
  clearTimeout(toastTimer)
})
</script>

<template>
  <div class="lobby-screen">
    <header class="lobby-top">
      <div class="lobby-logo">
        <img :src="assetUrl('resource/images/logo.png')" alt="FarmSim EDU" />
      </div>

      <div class="lobby-title-block">
        <h1>ห้องรอผู้เล่น</h1>
        <p>เชิญเพื่อนเข้าร่วมห้องเกม — กดเริ่มเมื่อพร้อม</p>
      </div>

      <div class="lobby-top-actions">
        <button
          type="button"
          class="lobby-btn lobby-btn-start"
          :disabled="actionLoading || !canStartGame"
          @click="handleStartGame"
        >
          ▶ เริ่มเกม
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
        <div class="lobby-stat">
          <span class="lobby-stat-label">สถานะ</span>
          <span class="lobby-stat-value">รอแอดมินเริ่มเกม</span>
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

    <div v-if="toast" class="lobby-toast">{{ toast }}</div>
  </div>
</template>
