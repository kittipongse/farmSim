<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import MobileLayout from '@/layouts/MobileLayout.vue'
import GameSummaryPanel from '@/components/GameSummaryPanel.vue'
import { useSessionStore } from '@/stores/sessionStore'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { getDashboard } from '@/services/dashboardService'

const session = useSessionStore()
const gameRoom = useGameRoomStore()
const { ranking, gameSummary } = storeToRefs(gameRoom)
const router = useRouter()

onMounted(async () => {
  try {
    const data = await getDashboard(session.roomCode)
    gameRoom.updateFromPoll(data)
  } catch {
    // ignore
  }
})

function goHome() {
  gameRoom.reset()
  session.clear()
  router.push({ name: 'home' })
}
</script>

<template>
  <MobileLayout>
    <div class="text-center py-4">
      <h2 class="page-title text-success mb-3">จบเกม 5 ปี!</h2>
      <p class="text-muted mb-4">สรุปปัญหาและผลการดำเนินการ</p>

      <div v-if="ranking?.length" class="card card-farm p-3 mb-4 text-start">
        <h5 class="mb-3">อันดับสุดท้าย</h5>
        <ol class="mb-0">
          <li v-for="r in ranking" :key="r.player_id" class="mb-1">
            <strong v-if="r.rank === 1">🥇</strong>
            <strong v-else-if="r.rank === 2">🥈</strong>
            <strong v-else-if="r.rank === 3">🥉</strong>
            {{ r.player_name }} — {{ r.total_score }} คะแนน
          </li>
        </ol>
      </div>

      <div v-if="gameSummary" class="card card-farm p-3 mb-4 text-start">
        <h5 class="mb-3">บทเรียนจากเกม</h5>
        <GameSummaryPanel :summary="gameSummary" compact />
      </div>

      <button type="button" class="btn btn-success w-100" @click="goHome">
        กลับหน้าแรก
      </button>
    </div>
  </MobileLayout>
</template>
