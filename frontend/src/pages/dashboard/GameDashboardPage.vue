<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PlayerJoinList from '@/components/PlayerJoinList.vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import BreakingNewsOverlay from '@/components/simulation/BreakingNewsOverlay.vue'
import BonusQuizOverlay from '@/components/simulation/BonusQuizOverlay.vue'
import BonusQuizRevealOverlay from '@/components/simulation/BonusQuizRevealOverlay.vue'
import ThailandGameMap from '@/components/dashboard/ThailandGameMap.vue'
import MonthProgressBar from '@/components/dashboard/MonthProgressBar.vue'
import DashboardAudioControl from '@/components/dashboard/DashboardAudioControl.vue'
import GameResultPresentation from '@/components/simulation/GameResultPresentation.vue'
import { useDashboardScene } from '@/composables/useDashboardScene'
import { playThunder } from '@/services/dashboardAudio'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { startDashboardPolling } from '@/services/pollingService'
import { getDashboard } from '@/services/dashboardService'
import { getPlayerCards, getRoomCardsStatus } from '@/services/cardService'
import { DECISION_CARDS, THAI_MONTHS } from '@/constants/cardSprites'
import { GAME_YEARS } from '@/constants/simulation'
import '@/assets/card-plan.css'

const route = useRoute()
const gameRoom = useGameRoomStore()
const { room, players, simulationRemaining, breakingNewsRemaining, simulation, ranking, bonusQuiz, presentation } = storeToRefs(gameRoom)

const roomCode = computed(() => route.params.roomCode?.toString().toUpperCase())
const cardsStatus = ref(null)
const currentPlayerCards = ref({})
const currentCardsFetchKey = ref('')
let stopPolling = null

const isSimulating = computed(() => room.value?.status === 'simulating')
const isPlanning = computed(() => room.value?.status === 'planning')
const isFinished = computed(() => room.value?.status === 'finished')
const currentEvent = computed(() => simulation.value?.current_event)
const breakingNewsActive = computed(() => {
  if (bonusQuizActive.value || bonusQuizRevealing.value) return false
  const remaining = breakingNewsRemaining.value
  const active = simulation.value?.breaking_news_active
  return remaining > 0 && active !== false
})
const bonusQuizActive = computed(() => Boolean(bonusQuiz.value?.active))
const bonusQuizRevealing = computed(() => Boolean(bonusQuiz.value?.revealing))
const showBreakingNewsOverlay = computed(() =>
  isSimulating.value && breakingNewsActive.value && !bonusQuizActive.value && !bonusQuizRevealing.value
)
const showBonusQuizOverlay = computed(() =>
  isSimulating.value && bonusQuizActive.value && !breakingNewsActive.value
)
const showBonusQuizRevealOverlay = computed(() =>
  isSimulating.value && bonusQuizRevealing.value
)
const simulationPaused = computed(() =>
  breakingNewsActive.value || bonusQuizActive.value || bonusQuizRevealing.value
)

const {
  audioReady,
  muted,
  enableAudio,
  toggleMute,
} = useDashboardScene({
  isSimulating,
  currentEvent,
  breakingNews: breakingNewsActive,
  bonusQuiz,
})

const readyCount = computed(() =>
  (cardsStatus.value?.players || []).filter((p) => p.ready).length
)

const monthLabel = computed(() =>
  THAI_MONTHS[(room.value?.current_month || 1) - 1] || ''
)

const monthsWithCards = computed(() => cardsStatus.value?.months_with_cards || {})

const showThailandMap = computed(() => {
  if (!room.value || isFinished.value) return false
  const countryCode = room.value.country_code?.toString().toUpperCase()
  const countryName = `${room.value.country_name_th || ''} ${room.value.country_name_en || ''}`.toLowerCase()
  return countryCode === 'TH' || countryName.includes('ไทย') || countryName.includes('thailand')
})
const useFullscreenMap = computed(() => showThailandMap.value && isSimulating.value)
const presentationActive = computed(() => Boolean(presentation.value?.active && presentation.value?.current))
const showFinishedSummary = computed(() => isFinished.value && !presentationActive.value)

function onWeatherThunder() {
  if (!muted.value) playThunder()
}

function cardForMonth(cards, month) {
  return (cards || []).find((card) => Number(card.month) === Number(month)) || null
}

function applyDashboard(data) {
  gameRoom.updateFromPoll(data)
}

function syncFullscreenScrollLock(enabled) {
  if (typeof document === 'undefined') return
  document.documentElement.classList.toggle('dashboard-game-no-scroll', enabled)
  document.body.classList.toggle('dashboard-game-no-scroll', enabled)
}

async function loadCurrentPlayerCards() {
  if (!room.value || !players.value.length || !isSimulating.value) {
    currentPlayerCards.value = {}
    currentCardsFetchKey.value = ''
    return
  }

  const playerIds = players.value.map((player) => player.id).join(',')
  const key = `${room.value.current_year || 1}:${room.value.current_month || 1}:${playerIds}`
  if (key === currentCardsFetchKey.value) return

  currentCardsFetchKey.value = key
  const year = room.value.current_year || 1
  const month = room.value.current_month || 1
  const entries = await Promise.all(
    players.value.map(async (player) => {
      try {
        const data = await getPlayerCards(player.id, year)
        return [player.id, cardForMonth(data.cards, month)]
      } catch {
        return [player.id, null]
      }
    })
  )

  currentPlayerCards.value = Object.fromEntries(entries)
}

async function loadCardsStatus() {
  if (!isPlanning.value && !isSimulating.value) return
  try {
    cardsStatus.value = await getRoomCardsStatus(roomCode.value)
  } catch {
    // retry via polling
  }
}

async function loadDashboard() {
  const data = await getDashboard(roomCode.value)
  applyDashboard(data)
  await loadCardsStatus()
  await loadCurrentPlayerCards()
}

onMounted(async () => {
  try {
    await loadDashboard()
  } catch {
    // polling will retry
  }

  stopPolling = startDashboardPolling(roomCode.value, async (data) => {
    applyDashboard(data)
    if (data.room?.status === 'planning' || data.room?.status === 'simulating') {
      await loadCardsStatus()
    }
    await loadCurrentPlayerCards()
  })
})

watch(roomCode, async (code, prev) => {
  if (!code || code === prev) return
  try {
    await loadDashboard()
  } catch {
    // polling will retry
  }
})

watch(useFullscreenMap, syncFullscreenScrollLock, { immediate: true })

onUnmounted(() => {
  stopPolling?.()
  syncFullscreenScrollLock(false)
})
</script>

<template>
  <DashboardLayout :fullscreen="useFullscreenMap">
    <DashboardAudioControl
      v-if="showThailandMap"
      :audio-ready="audioReady"
      :muted="muted"
      @enable="enableAudio"
      @toggle-mute="toggleMute"
    />

    <BreakingNewsOverlay
      :active="showBreakingNewsOverlay"
      :event="currentEvent"
      :remaining="breakingNewsRemaining"
    />

    <BonusQuizOverlay
      :active="showBonusQuizOverlay"
      :quiz="bonusQuiz"
    />

    <BonusQuizRevealOverlay
      :active="showBonusQuizRevealOverlay"
      :quiz="bonusQuiz"
    />

    <GameResultPresentation
      :active="presentationActive"
      :presentation="presentation"
      :room-code="roomCode"
    />

    <div v-if="useFullscreenMap" class="dashboard-game-fullscreen">
      <MonthProgressBar
        class="dashboard-month-progress dashboard-month-progress--fullscreen"
        :active="isSimulating"
        :planning="isPlanning"
        :month="room?.current_month || 1"
        :remaining="simulationRemaining"
        :finished="isFinished"
        :paused="simulationPaused"
        :months-with-cards="monthsWithCards"
      />

      <ThailandGameMap
        fullscreen
        :room="room"
        :players="players"
        :ranking="ranking"
        :current-cards="currentPlayerCards"
        :remaining="simulationRemaining"
        :active="isSimulating"
        :paused="simulationPaused"
        :weather-event="currentEvent"
        :breaking-news="breakingNewsActive"
        :max-players="8"
        @weather-thunder="onWeatherThunder"
      />
    </div>

    <div v-else class="container py-4">
      <MonthProgressBar
        v-if="room"
        class="dashboard-month-progress mb-4"
        :active="isSimulating"
        :planning="isPlanning"
        :month="room?.current_month || 1"
        :remaining="simulationRemaining"
        :finished="isFinished"
        :paused="simulationPaused"
        :months-with-cards="monthsWithCards"
      />

      <div v-if="showFinishedSummary" class="card card-farm p-4 mb-4 text-center">
        <h2 class="page-title text-success">เกมจบแล้ว — {{ GAME_YEARS }} ปีครบ!</h2>
        <p class="text-muted mb-0">รอผู้เล่นส่งผลจากมือถือเพื่อแสดงบนจอใหญ่</p>
      </div>

      <div v-else class="card card-farm p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h2 class="page-title mb-1">
              <template v-if="isSimulating">
                ปี {{ room?.current_year }} · เดือน {{ monthLabel }}
              </template>
              <template v-else-if="isPlanning">
                ปี {{ room?.current_year }} — วางแผนการ์ด
              </template>
              <template v-else>เกม FarmSim EDU</template>
            </h2>
            <p class="text-muted mb-0">{{ room?.country_name_th }}</p>
          </div>
          <span
            class="badge fs-6"
            :class="{
              'bg-success': isSimulating,
              'bg-primary': isPlanning,
              'bg-secondary': !isSimulating && !isPlanning,
            }"
          >
            {{ room?.status }}
          </span>
        </div>

        <div v-if="isPlanning" class="row g-2 mb-3 align-items-end">
          <div
            v-for="card in DECISION_CARDS"
            :key="card.code"
            class="col-3 col-md-auto text-center"
          >
            <CardSprite :index="card.spriteIndex" size="hand" :label="card.nameTh" />
            <div class="small text-muted mt-1">{{ card.nameTh }}</div>
          </div>
        </div>

        <div v-if="isPlanning && cardsStatus" class="alert alert-info mb-0">
          ยืนยันแผนแล้ว {{ readyCount }} / {{ cardsStatus.player_count }} คน
          <span v-if="cardsStatus.all_ready"> — เริ่มจำลองอัตโนมัติ!</span>
        </div>

        <div v-if="isSimulating" class="alert alert-success mb-0">
          กำลังจำลองเดือน · เหลือ {{ Math.floor(simulationRemaining / 60) }}:{{
            String(simulationRemaining % 60).padStart(2, '0')
          }}
          <span v-if="breakingNewsActive && currentEvent"> · {{ currentEvent.name_th }}</span>
        </div>
      </div>

      <ThailandGameMap
        v-if="showThailandMap"
        class="mb-4"
        :fullscreen="false"
        :room="room"
        :players="players"
        :ranking="ranking"
        :current-cards="currentPlayerCards"
        :remaining="simulationRemaining"
        :active="isSimulating"
        :paused="simulationPaused"
        :weather-event="currentEvent"
        :breaking-news="breakingNewsActive"
        :max-players="8"
        @weather-thunder="onWeatherThunder"
      />

      <div v-if="ranking?.length && !presentationActive" class="card card-farm p-3 mb-4">
        <h5 class="mb-3">{{ isFinished ? 'อันดับสุดท้าย' : `อันดับปี ${room?.current_year}` }}</h5>
        <div class="table-responsive">
          <table class="table table-sm mb-0 align-middle">
            <thead>
              <tr>
                <th style="width: 4rem">อันดับ</th>
                <th>ผู้เล่น</th>
                <th class="text-end">คะแนน</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in ranking" :key="r.player_id">
                <td>
                  <strong v-if="r.rank === 1">🥇 1</strong>
                  <strong v-else-if="r.rank === 2">🥈 2</strong>
                  <strong v-else-if="r.rank === 3">🥉 3</strong>
                  <span v-else>{{ r.rank }}</span>
                </td>
                <td>{{ r.player_name }}</td>
                <td class="text-end"><strong>{{ r.total_score }}</strong></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="isFinished && !presentationActive" class="text-muted small mt-3 mb-0">
          กด「ส่งผลการแข่งขัน」บนมือถือเพื่อแสดงสรุปบนจอใหญ่
        </p>
      </div>

      <div v-if="isPlanning && cardsStatus?.players?.length" class="card card-farm p-3 mb-4">
        <h5 class="mb-3">ความคืบหน้าวางการ์ด</h5>
        <ul class="list-group list-group-flush">
          <li
            v-for="p in cardsStatus.players"
            :key="p.player_id"
            class="list-group-item bg-transparent d-flex justify-content-between px-0"
          >
            <span>{{ p.name }}</span>
            <span class="badge" :class="p.ready ? 'bg-success' : 'bg-secondary'">
              {{ p.submitted ? 'ยืนยันแล้ว' : `${p.placed_count}/12` }}
            </span>
          </li>
        </ul>
      </div>

      <div v-if="simulation?.market?.length" class="card card-farm p-3 mb-4">
        <h5 class="mb-3">ตลาดกลาง</h5>
        <div class="row g-2 small">
          <div v-for="m in simulation.market.slice(0, 8)" :key="m.crop_id" class="col-6 col-md-3">
            <div class="border rounded p-2">
              <div class="fw-bold">{{ m.name_th }}</div>
              <div>ขาย {{ m.sell_price }}</div>
            </div>
          </div>
        </div>
      </div>

      <PlayerJoinList
        v-if="!showThailandMap"
        :players="players"
        :room-player-count="room?.player_count || 0"
      />
    </div>
  </DashboardLayout>
</template>

<style scoped>
.dashboard-game-fullscreen {
  position: relative;
  width: 100vw;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
}

.dashboard-month-progress--fullscreen {
  position: absolute;
  top: clamp(0.2rem, 0.5vw, 0.4rem);
  left: clamp(0.65rem, 1.8vw, 1.5rem);
  right: clamp(0.65rem, 1.8vw, 1.5rem);
  z-index: 5;
}

:global(html.dashboard-game-no-scroll),
:global(body.dashboard-game-no-scroll) {
  width: 100%;
  height: 100%;
  overflow: hidden;
}
</style>
