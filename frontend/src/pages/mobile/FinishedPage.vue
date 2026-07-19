<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import PlayerGameReview from '@/components/PlayerGameReview.vue'
import { useSessionStore } from '@/stores/sessionStore'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { getGameReview } from '@/services/playerService'
import { getDashboard } from '@/services/dashboardService'
import { submitPresentation, getPresentationStatus } from '@/services/presentationService'
import { GAME_YEARS } from '@/constants/simulation'

const session = useSessionStore()
const gameRoom = useGameRoomStore()
const router = useRouter()

const review = ref(null)
const presentationStatus = ref(null)
const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const submitMessage = ref('')
let statusPollTimer = null

async function loadPresentationStatus() {
  try {
    presentationStatus.value = await getPresentationStatus(session.playerId)
  } catch {
    presentationStatus.value = null
  }
}

onMounted(async () => {
  loading.value = true
  error.value = ''
  try {
    try {
      const dash = await getDashboard(session.roomCode)
      gameRoom.updateFromPoll(dash)
    } catch {
      // ignore dashboard poll errors — still try review
    }
    review.value = await getGameReview(session.playerId)
    await loadPresentationStatus()
    if (presentationStatus.value?.status === 'queued') {
      statusPollTimer = setInterval(loadPresentationStatus, 3000)
    }
  } catch (e) {
    error.value = e.message || 'โหลดสรุปผลไม่สำเร็จ'
  } finally {
    loading.value = false
  }
})

async function handleSubmitPresentation() {
  submitting.value = true
  submitMessage.value = ''
  error.value = ''
  try {
    const data = await submitPresentation(session.playerId)
    presentationStatus.value = data
    if (data.is_presenting) {
      submitMessage.value = 'กำลังแสดงผลบนจอใหญ่!'
      clearInterval(statusPollTimer)
      statusPollTimer = null
    } else if (data.status === 'queued') {
      const ahead = Math.max(0, (data.queue_position || 1) - 1)
      submitMessage.value = ahead > 0
        ? `อยู่ในคิวลำดับที่ ${data.queue_position} (รอ ${ahead} คนก่อนหน้า)`
        : `อยู่ในคิวลำดับที่ ${data.queue_position}`
      if (!statusPollTimer) {
        statusPollTimer = setInterval(loadPresentationStatus, 3000)
      }
    } else if (data.is_done) {
      submitMessage.value = 'แสดงผลบนจอใหญ่เสร็จแล้ว'
      clearInterval(statusPollTimer)
      statusPollTimer = null
    } else {
      submitMessage.value = 'ส่งผลเข้าคิวแล้ว'
    }
  } catch (e) {
    error.value = e.message || 'ส่งผลไม่สำเร็จ'
  } finally {
    submitting.value = false
  }
}

function goHome() {
  clearInterval(statusPollTimer)
  gameRoom.reset()
  session.clear()
  router.push({ name: 'home' })
}

onUnmounted(() => {
  clearInterval(statusPollTimer)
})
</script>

<template>
  <MobileLayout>
    <div class="py-3">
      <div class="text-center mb-3">
        <h2 class="page-title text-success mb-1">จบเกม {{ GAME_YEARS }} ปี!</h2>
        <p class="text-muted small mb-0">สรุปคะแนนและวิเคราะห์การวางแผนของคุณ</p>
      </div>

      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-success" role="status" />
        <p class="text-muted mt-2 mb-0">กำลังวิเคราะห์แผน...</p>
      </div>

      <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

      <template v-else>
        <div class="card card-farm p-3 mb-3 border-primary">
          <h6 class="mb-2">แสดงผลบนจอใหญ่</h6>
          <p class="small text-muted mb-3">
            ส่งสรุปผลของคุณไปแสดงบน Dashboard — ระบบจัดคิวทีละคน
          </p>

          <div v-if="presentationStatus?.is_presenting" class="alert alert-success py-2 small mb-3">
            กำลังแสดงผลบนจอใหญ่!
          </div>
          <div v-else-if="presentationStatus?.status === 'queued'" class="alert alert-info py-2 small mb-3">
            อยู่ในคิวลำดับที่ {{ presentationStatus.queue_position }}
            <span v-if="presentationStatus.queue_position > 1">
              (รอ {{ presentationStatus.queue_position - 1 }} คนก่อนหน้า)
            </span>
          </div>
          <div v-else-if="presentationStatus?.is_done" class="alert alert-secondary py-2 small mb-3">
            แสดงผลบนจอใหญ่เสร็จแล้ว
          </div>

          <button
            type="button"
            class="btn btn-primary w-100"
            :disabled="submitting || presentationStatus?.submitted"
            @click="handleSubmitPresentation"
          >
            {{ presentationStatus?.submitted ? 'ส่งผลแล้ว' : 'ส่งผลการแข่งขัน' }}
          </button>
          <p v-if="submitMessage" class="small text-success mt-2 mb-0">{{ submitMessage }}</p>
        </div>

        <PlayerGameReview v-if="review" :review="review" />
      </template>

      <button type="button" class="btn btn-success w-100 mt-2" @click="goHome">
        กลับหน้าแรก
      </button>
    </div>
  </MobileLayout>
</template>
