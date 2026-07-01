<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import { useSessionStore } from '@/stores/sessionStore'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { startLobbyPolling } from '@/services/pollingService'
import { getPlayer } from '@/services/playerService'
import { respondEvent, moveCard, getPlayerCards } from '@/services/cardService'
import { THAI_MONTHS } from '@/constants/cardSprites'
import '@/assets/card-plan.css'

const session = useSessionStore()
const gameRoom = useGameRoomStore()
const router = useRouter()

const player = ref(null)
const resources = ref(null)
const cards = ref([])
const error = ref('')
const loading = ref(true)
const responding = ref(false)
const responded = ref(false)

const showMoveModal = ref(false)
const fromMonth = ref(null)
const toMonth = ref(null)

let stopPolling = null

const room = computed(() => gameRoom.room)
const sim = computed(() => gameRoom.simulation)
const currentEvent = computed(() => sim.value?.current_event)
const year = computed(() => room.value?.current_year || 1)
const month = computed(() => room.value?.current_month || 1)
const monthLabel = computed(() => THAI_MONTHS[month.value - 1] || '')

const isDisaster = computed(() => currentEvent.value?.event_type === 'disaster')
const isPolicy = computed(() => currentEvent.value?.event_type === 'government_policy')

function onPoll(data) {
  gameRoom.updateFromPoll(data)
  const status = data.room?.status
  if (status === 'planning') {
    router.replace({ name: 'mobile-plan-cards' })
  } else if (status === 'finished') {
    router.replace({ name: 'mobile-finished' })
  } else if (status === 'lobby' || status === 'countdown') {
    router.replace({ name: 'mobile-waiting' })
  }
}

async function loadPlayer() {
  const data = await getPlayer(session.playerId)
  player.value = data.player
  resources.value = data.resources
}

async function loadCards() {
  const data = await getPlayerCards(session.playerId, year.value)
  cards.value = data.cards || []
}

async function handleRespond(action) {
  if (!currentEvent.value || responded.value) return
  responding.value = true
  error.value = ''
  try {
    await respondEvent(session.playerId, {
      event_id: currentEvent.value.id,
      action,
    })
    responded.value = true
    await loadPlayer()
  } catch (e) {
    error.value = e.message
  } finally {
    responding.value = false
  }
}

function openMove() {
  fromMonth.value = null
  toMonth.value = null
  showMoveModal.value = true
}

async function confirmMove() {
  if (!fromMonth.value || !toMonth.value) {
    error.value = 'เลือกเดือนต้นทางและปลายทาง'
    return
  }
  responding.value = true
  error.value = ''
  try {
    const data = await moveCard(session.playerId, {
      year: year.value,
      from_month: fromMonth.value,
      to_month: toMonth.value,
    })
    cards.value = data.cards || []
    responded.value = true
    showMoveModal.value = false
    await loadPlayer()
  } catch (e) {
    error.value = e.message
  } finally {
    responding.value = false
  }
}

onMounted(async () => {
  try {
    await loadPlayer()
    await loadCards()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
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
    <div class="card-plan-screen">
      <div class="card-plan-header">
        <h2 class="card-plan-year">ปี {{ year }} · เดือน {{ monthLabel }}</h2>
        <p class="card-plan-progress">
          สถานะ: จำลองเกม · เหลือ {{ gameRoom.simulationRemaining }} วินาที
        </p>
      </div>

      <div v-if="loading" class="text-center py-4">
        <div class="spinner-border text-success" role="status" />
      </div>

      <template v-else>
        <div class="card card-farm p-3 mb-3">
          <h5 class="mb-2">ทรัพยากรของคุณ</h5>
          <div class="row g-2 small">
            <div class="col-4">💰 {{ resources?.coins ?? '—' }}</div>
            <div class="col-4">💧 {{ resources?.water ?? '—' }}</div>
            <div class="col-4">🌱 {{ player?.agricultural_capability ?? '—' }}%</div>
          </div>
        </div>

        <div v-if="currentEvent" class="card card-farm p-3 mb-3">
          <div class="d-flex gap-2 align-items-center mb-2">
            <CardSprite
              :index="currentEvent.sprite_index ?? 8"
              size="hand"
              :glow="true"
              :label="currentEvent.name_th"
            />
            <div>
              <strong class="text-warning">Breaking News</strong>
              <div>{{ currentEvent.name_th }}</div>
            </div>
          </div>

          <template v-if="!responded">
            <p class="small text-muted mb-2">เลือกวิธีรับมือก่อนหมดเวลา</p>
            <div class="d-grid gap-2">
              <button
                v-if="isDisaster"
                type="button"
                class="btn btn-warning"
                :disabled="responding"
                @click="handleRespond('protect')"
              >
                ใช้การ์ดป้องกัน / เตรียมพร้อม
              </button>
              <button
                v-if="isDisaster"
                type="button"
                class="btn btn-outline-warning"
                :disabled="responding"
                @click="openMove"
              >
                ย้ายการ์ด (-15 เหรียญ, -20 น้ำ)
              </button>
              <button
                v-if="isPolicy"
                type="button"
                class="btn btn-info"
                :disabled="responding"
                @click="handleRespond('invest')"
              >
                ใช้โอกาสจากนโยบายรัฐ
              </button>
              <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                :disabled="responding"
                @click="handleRespond('ignore')"
              >
                ไม่ทำอะไร (เสี่ยงลด Capability)
              </button>
            </div>
          </template>
          <p v-else class="text-success small mb-0">ตอบสนองเหตุการณ์แล้ว — รอเดือนถัดไป</p>
        </div>

        <div v-else class="alert alert-info">
          ดูแอนิเมชันและอันดับบน Dashboard หลัก
        </div>

        <div v-if="gameRoom.ranking?.length" class="card card-farm p-3">
          <h6 class="mb-2">อันดับปีนี้</h6>
          <ol class="mb-0 small ps-3">
            <li v-for="r in gameRoom.ranking.slice(0, 5)" :key="r.player_id">
              {{ r.player_name }} — {{ r.total_score }} คะแนน
            </li>
          </ol>
        </div>
      </template>

      <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>
    </div>

    <div v-if="showMoveModal" class="card-plan-modal-backdrop" @click.self="showMoveModal = false">
      <div class="card-plan-modal">
        <h4>ย้ายการ์ด</h4>
        <label class="small">จากเดือน</label>
        <select v-model.number="fromMonth" class="form-select mb-2">
          <option :value="null">เลือก</option>
          <option v-for="c in cards" :key="c.month" :value="c.month">
            {{ THAI_MONTHS[c.month - 1] }} ({{ c.card_code }})
          </option>
        </select>
        <label class="small">ไปเดือน</label>
        <select v-model.number="toMonth" class="form-select mb-3">
          <option :value="null">เลือก</option>
          <option v-for="m in 12" :key="m" :value="m">{{ THAI_MONTHS[m - 1] }}</option>
        </select>
        <div class="card-plan-modal-actions">
          <button type="button" class="btn btn-outline-light flex-fill" @click="showMoveModal = false">
            ยกเลิก
          </button>
          <button type="button" class="btn btn-warning flex-fill" @click="confirmMove">
            ย้าย
          </button>
        </div>
      </div>
    </div>
  </MobileLayout>
</template>
