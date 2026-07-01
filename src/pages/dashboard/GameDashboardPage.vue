<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PlayerJoinList from '@/components/PlayerJoinList.vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import MonthSimulationOverlay from '@/components/simulation/MonthSimulationOverlay.vue'
import GameSummaryPanel from '@/components/GameSummaryPanel.vue'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { startDashboardPolling } from '@/services/pollingService'
import { getDashboard } from '@/services/dashboardService'
import { getRoomCardsStatus } from '@/services/cardService'
import { DECISION_CARDS, THAI_MONTHS } from '@/constants/cardSprites'
import '@/assets/card-plan.css'

const route = useRoute()
const gameRoom = useGameRoomStore()
const { room, players, simulationRemaining, simulation, ranking, gameSummary } = storeToRefs(gameRoom)

const roomCode = computed(() => route.params.roomCode?.toString().toUpperCase())
const cardsStatus = ref(null)
let stopPolling = null

const isSimulating = computed(() => room.value?.status === 'simulating')
const isPlanning = computed(() => room.value?.status === 'planning')
const isFinished = computed(() => room.value?.status === 'finished')
const currentEvent = computed(() => simulation.value?.current_event)

const readyCount = computed(() =>
  (cardsStatus.value?.players || []).filter((p) => p.ready).length
)

const monthLabel = computed(() =>
  THAI_MONTHS[(room.value?.current_month || 1) - 1] || ''
)

function applyDashboard(data) {
  gameRoom.updateFromPoll(data)
}

async function loadCardsStatus() {
  if (!isPlanning.value) return
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
}

onMounted(async () => {
  try {
    await loadDashboard()
  } catch {
    // polling will retry
  }

  stopPolling = startDashboardPolling(roomCode.value, async (data) => {
    applyDashboard(data)
    if (data.room?.status === 'planning') {
      await loadCardsStatus()
    }
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

onUnmounted(() => {
  stopPolling?.()
})
</script>

<template>
  <DashboardLayout>
    <MonthSimulationOverlay
      :active="isSimulating"
      :year="room?.current_year || 1"
      :month="room?.current_month || 1"
      :remaining="simulationRemaining"
      :event="currentEvent"
    />

    <div class="container py-4">
      <div v-if="isFinished" class="card card-farm p-4 mb-4 text-center">
        <h2 class="page-title text-success">เกมจบแล้ว — 5 ปีครบ!</h2>
        <p class="text-muted">สรุปคะแนนสุดท้าย</p>
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
          กำลังจำลองเดือน · เหลือ {{ simulationRemaining }} วินาที
          <span v-if="currentEvent"> · {{ currentEvent.name_th }}</span>
        </div>
      </div>

      <div v-if="ranking?.length" class="card card-farm p-3 mb-4">
        <h5 class="mb-3">อันดับ {{ isFinished ? 'สุดท้าย' : `ปี ${room?.current_year}` }}</h5>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>ผู้เล่น</th>
                <th>คะแนน</th>
                <th>ทรัพยากร</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in ranking" :key="r.player_id">
                <td>{{ r.rank }}</td>
                <td>{{ r.player_name }}</td>
                <td><strong>{{ r.total_score }}</strong></td>
                <td class="small text-muted">{{ r.resource_score }} / {{ r.capability_score }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="isFinished && gameSummary" class="card card-farm p-4 mb-4">
        <h5 class="mb-3">สรุปปัญหาและผลการดำเนินการ</h5>
        <GameSummaryPanel :summary="gameSummary" />
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
              {{ p.submitted ? 'ยืนยันแล้ว' : `${p.placed_count}/8` }}
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
        :players="players"
        :room-player-count="room?.player_count || 0"
      />
    </div>
  </DashboardLayout>
</template>
