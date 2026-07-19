<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import { useSessionStore } from '@/stores/sessionStore'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { startLobbyPolling } from '@/services/pollingService'
import { getPlayer } from '@/services/playerService'
import {
  respondEvent,
  answerBonusQuiz,
  moveCard,
  getPlayerCards,
  assignCard,
  unassignCard,
  startPlanAdjustment,
  finishPlanAdjustment,
  cancelPlanAdjustment,
} from '@/services/cardService'
import {
  DECISION_CARDS,
  UI_SPRITES,
  THAI_MONTHS,
  spriteIndexForCode,
} from '@/constants/cardSprites'
import { MAX_PLAN_ADJUSTMENTS, BONUS_QUIZ_FIRST_CORRECT, BONUS_QUIZ_LATER_CORRECT, BONUS_QUIZ_WRONG } from '@/constants/simulation'
import { useCropPlantInput } from '@/composables/useCropPlantInput'
import { useQuizActivity } from '@/composables/useQuizActivity'
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
const respondedEventId = ref(null)
const bonusQuizAnswer = ref(null)
const quizAnswerInput = ref('')
const quizSubmitting = ref(false)
const selectedQuizChoice = ref(null)
const planAdjusting = ref(false)
const selectedCard = ref(null)
const saving = ref(false)

const showMoveModal = ref(false)
const showPlantModal = ref(false)
const pendingMonth = ref(null)
const fromMonth = ref(null)
const toMonth = ref(null)

const {
  cropName,
  cropCheck,
  cropChecking,
  cropValid,
  cropFeedbackClass,
  cropFeedbackText,
  cropSuggestions,
  resetCropInput,
  scheduleCropValidate,
  runCropValidate,
  pickCropSuggestion,
} = useCropPlantInput(() => session.playerId)

let stopPolling = null

const room = computed(() => gameRoom.room)
const sim = computed(() => gameRoom.simulation)
const currentEvent = computed(() => sim.value?.current_event)
const year = computed(() => room.value?.current_year || 1)
const month = computed(() => room.value?.current_month || 1)
const monthLabel = computed(() => THAI_MONTHS[month.value - 1] || '')
const bonusQuiz = computed(() => gameRoom.bonusQuiz)
const bonusQuizActive = computed(() => Boolean(bonusQuiz.value?.active))
const bonusQuizRevealing = computed(() => Boolean(bonusQuiz.value?.revealing))
const breakingNewsActive = computed(() => {
  if (bonusQuizActive.value || bonusQuizRevealing.value) return false
  const remaining = gameRoom.breakingNewsRemaining
  const active = sim.value?.breaking_news_active
  return remaining > 0 && active !== false
})
const hasAnsweredQuiz = computed(() => Boolean(bonusQuizAnswer.value))
const quizRemaining = computed(() => Math.max(0, Number(bonusQuiz.value?.remaining_seconds) || 0))
const quizIdleCloseActive = computed(() => Boolean(bonusQuiz.value?.idle_close_active))
const quizIdleCloseRemaining = computed(() =>
  Math.max(0, Number(bonusQuiz.value?.idle_close_remaining_seconds) || 0)
)
const quizDisplayTimer = computed(() => {
  if (quizIdleCloseActive.value && quizIdleCloseRemaining.value > 0) {
    return quizIdleCloseRemaining.value
  }
  return quizRemaining.value
})
const showEventCard = computed(() => {
  if (!currentEvent.value) return false
  if (bonusQuizActive.value || bonusQuizRevealing.value) return false
  if (breakingNewsActive.value) return true
  return !hasResponded.value
})
const quizChoices = computed(() => bonusQuiz.value?.choices || [
  { key: 'A', label: 'ใช่' },
  { key: 'B', label: 'ไม่ใช่' },
])
const quizScoring = computed(() => bonusQuiz.value?.scoring || {
  first_correct: BONUS_QUIZ_FIRST_CORRECT,
  later_correct: BONUS_QUIZ_LATER_CORRECT,
  wrong: BONUS_QUIZ_WRONG,
})

const isDisaster = computed(() => currentEvent.value?.event_type === 'disaster')
const isPolicy = computed(() => currentEvent.value?.event_type === 'government_policy')

const placementByMonth = computed(() => {
  const map = {}
  for (const card of cards.value) {
    map[card.month] = card
  }
  return map
})

const planAdjustmentsRemaining = computed(() =>
  player.value?.plan_adjustments_remaining ?? MAX_PLAN_ADJUSTMENTS
)
const canStartPlanAdjustment = computed(() =>
  isDisaster.value
  && !planAdjusting.value
  && !hasResponded.value
  && planAdjustmentsRemaining.value > 0
  && currentEvent.value
)

const hasResponded = computed(() =>
  Boolean(currentEvent.value && respondedEventId.value === currentEvent.value.id)
)

function syncEventResponse(data) {
  if (data?.event_response?.event_id) {
    respondedEventId.value = data.event_response.event_id
  }
}

function syncBonusQuizAnswer(data) {
  bonusQuizAnswer.value = data?.bonus_quiz_answer || null
}

function isLockedMonth(m) {
  return planAdjusting.value && m < month.value
}

function monthSprite(m) {
  const placed = placementByMonth.value[m]
  if (placed) return spriteIndexForCode(placed.card_code)
  if (isLockedMonth(m)) return UI_SPRITES.AUTO
  return UI_SPRITES.PLACE
}

function onPoll(data) {
  const prevMonth = room.value?.current_month
  gameRoom.updateFromPoll(data)
  const nextMonth = data.room?.current_month
  if (nextMonth && prevMonth && nextMonth !== prevMonth) {
    loadCards().catch(() => {})
    if (!planAdjusting.value) {
      respondedEventId.value = null
    }
  }
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
  planAdjusting.value = Boolean(data.player?.in_plan_adjustment)
  syncEventResponse(data)
  syncBonusQuizAnswer(data)
}

async function loadCards() {
  const data = await getPlayerCards(session.playerId, year.value)
  cards.value = data.cards || []
}

function choiceLabel(key) {
  const found = quizChoices.value.find((c) => c.key === key)
  return found ? `${found.key}. ${found.label}` : key
}

async function handleSubmitQuizAnswer(choiceKey = null) {
  if (!bonusQuizActive.value || hasAnsweredQuiz.value) return
  const answer = (choiceKey || selectedQuizChoice.value || quizAnswerInput.value || '').toString().trim()
  if (!answer) {
    error.value = 'กรุณาเลือกคำตอบ'
    return
  }
  quizSubmitting.value = true
  error.value = ''
  try {
    const data = await answerBonusQuiz(session.playerId, answer)
    bonusQuizAnswer.value = {
      answer,
      is_correct: data.is_correct,
      coins_delta: data.coins_delta,
      correct_order: data.correct_order,
    }
    if (data.resources) {
      resources.value = { ...resources.value, ...data.resources }
    }
    quizAnswerInput.value = ''
    selectedQuizChoice.value = null
    await loadPlayer()
  } catch (e) {
    error.value = e.message
  } finally {
    quizSubmitting.value = false
  }
}

useQuizActivity({
  active: bonusQuizActive,
  answered: hasAnsweredQuiz,
  playerId: session.playerId,
  onKeyAnswer: (choiceKey) => {
    if (!quizSubmitting.value) {
      handleSubmitQuizAnswer(choiceKey)
    }
  },
})

async function handleRespond(action) {
  const event = currentEvent.value
  const eventId = event?.id
  if (!eventId || hasResponded.value) return
  responding.value = true
  error.value = ''
  try {
    await respondEvent(session.playerId, {
      event_id: eventId,
      action,
    })
    respondedEventId.value = eventId
    await loadPlayer()
  } catch (e) {
    error.value = e.message
  } finally {
    responding.value = false
  }
}

async function handleStartPlanAdjustment() {
  responding.value = true
  error.value = ''
  try {
    const data = await startPlanAdjustment(session.playerId)
    player.value = data.player
    planAdjusting.value = true
    selectedCard.value = null
  } catch (e) {
    error.value = e.message
  } finally {
    responding.value = false
  }
}

async function handleFinishPlanAdjustment() {
  saving.value = true
  error.value = ''
  try {
    const data = await finishPlanAdjustment(session.playerId)
    player.value = data.player
    cards.value = data.cards || []
    planAdjusting.value = false
    if (currentEvent.value?.id) {
      respondedEventId.value = currentEvent.value.id
    }
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function handleCancelPlanAdjustment() {
  try {
    const data = await cancelPlanAdjustment(session.playerId)
    player.value = data.player
    planAdjusting.value = false
    selectedCard.value = null
  } catch (e) {
    error.value = e.message
  }
}

function selectHandCard(code) {
  if (!planAdjusting.value) return
  selectedCard.value = selectedCard.value === code ? null : code
}

async function placeOnMonth(m) {
  if (!planAdjusting.value || saving.value || isLockedMonth(m)) return

  const existing = placementByMonth.value[m]
  if (existing) {
    saving.value = true
    error.value = ''
    try {
      const data = await unassignCard(session.playerId, { year: year.value, month: m })
      cards.value = data.cards || []
    } catch (e) {
      error.value = e.message
    } finally {
      saving.value = false
    }
    return
  }

  if (!selectedCard.value) return

  if (selectedCard.value === 'PLANT') {
    pendingMonth.value = m
    resetCropInput()
    cropName.value = placementByMonth.value[m]?.crop_name || ''
    if (cropName.value) scheduleCropValidate()
    showPlantModal.value = true
    return
  }

  saving.value = true
  error.value = ''
  try {
    const data = await assignCard(session.playerId, {
      year: year.value,
      card_code: selectedCard.value,
      month: m,
    })
    cards.value = data.cards || []
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function confirmPlant() {
  const name = cropName.value.trim()
  if (!name) {
    error.value = 'กรุณาระบุชื่อพืช'
    return
  }
  const check = cropCheck.value?.valid ? cropCheck.value : await runCropValidate()
  if (!check?.valid) {
    error.value = check?.message || 'ไม่พบพืชในระบบ — พิมพ์ชื่อให้ถูกต้อง'
    return
  }
  showPlantModal.value = false
  saving.value = true
  error.value = ''
  try {
    const data = await assignCard(session.playerId, {
      year: year.value,
      card_code: 'PLANT',
      month: pendingMonth.value,
      crop_name: check.display_name || name,
    })
    cards.value = data.cards || []
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
    pendingMonth.value = null
    resetCropInput()
  }
}

function cancelPlantModal() {
  showPlantModal.value = false
  pendingMonth.value = null
  resetCropInput()
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
  const eventId = currentEvent.value?.id
  responding.value = true
  error.value = ''
  try {
    const data = await moveCard(session.playerId, {
      year: year.value,
      from_month: fromMonth.value,
      to_month: toMonth.value,
    })
    cards.value = data.cards || []
    if (eventId) respondedEventId.value = eventId
    showMoveModal.value = false
    await loadPlayer()
  } catch (e) {
    error.value = e.message
  } finally {
    responding.value = false
  }
}

watch(
  () => currentEvent.value?.id,
  (eventId, prevId) => {
    if (eventId && prevId && eventId !== prevId) {
      respondedEventId.value = null
    }
  }
)

onMounted(async () => {
  try {
    await loadPlayer()
    await loadCards()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }

  stopPolling = startLobbyPolling(session.roomCode, onPoll, () => {
    // ไม่แสดง error จาก poll ทับข้อความ action ของผู้เล่น
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
        <h2 class="card-plan-year">ปี {{ year }} · เดือน{{ monthLabel }}</h2>
        <p class="card-plan-progress">
          กำลังดำเนินการเดือน {{ monthLabel }}
          <span v-if="breakingNewsActive"> · Breaking News</span>
          <span v-else> · เหลือ {{ gameRoom.simulationRemaining }} วินาที</span>
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

        <div class="card card-farm p-3 mb-3">
          <h5 class="mb-2">การ์ดที่วาง — ปี {{ year }}</h5>
          <div class="card-plan-grid card-plan-grid--compact">
            <div v-for="m in 12" :key="m" class="card-plan-month">
              <span
                class="card-plan-month-label"
                :class="{ 'text-muted': m < month, 'text-warning fw-bold': m === month }"
              >
                {{ THAI_MONTHS[m - 1] }}
              </span>
              <button
                type="button"
                class="card-plan-month-btn"
                :class="{
                  'is-locked': isLockedMonth(m),
                  'is-current': m === month,
                }"
                :disabled="planAdjusting ? (isLockedMonth(m) || saving) : true"
                @click="placeOnMonth(m)"
              >
                <CardSprite
                  :index="monthSprite(m)"
                  size="slot"
                  :glow="!!placementByMonth[m] || m === month"
                  :dim="isLockedMonth(m)"
                  :label="placementByMonth[m]?.card_code || (m < month ? 'ล็อก' : '—')"
                />
              </button>
            </div>
          </div>
          <p v-if="planAdjusting" class="small text-warning mb-0 mt-2">
            แก้ไขได้ตั้งแต่เดือน{{ monthLabel }}เป็นต้นไป · เดือนก่อนหน้าถูกล็อก
          </p>
        </div>

        <div v-if="bonusQuizActive" class="card card-farm p-3 mb-3 border-warning">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <strong class="text-warning">โบนัสทายปัญหา!</strong>
            <span class="small text-muted">
              {{ bonusQuiz?.answers_count || 0 }}/{{ bonusQuiz?.total_players || 0 }} คนตอบ
            </span>
          </div>
          <p v-if="quizDisplayTimer > 0" class="small text-danger mb-1">
            <template v-if="quizIdleCloseActive">
              ไม่มีใครตอบ · ปิดอัตโนมัติใน {{ quizDisplayTimer }} วินาที
            </template>
            <template v-else>
              ปิดอัตโนมัติใน {{ quizDisplayTimer }} วินาที
            </template>
          </p>
          <p class="fw-bold mb-2">{{ bonusQuiz?.question }}</p>

          <div class="quiz-score-board mb-3">
            <div class="quiz-score-item is-first">
              <span class="quiz-score-label">ถูกคนแรก</span>
              <strong>+{{ quizScoring.first_correct }}</strong>
            </div>
            <div class="quiz-score-item is-later">
              <span class="quiz-score-label">ถูกถัดไป</span>
              <strong>+{{ quizScoring.later_correct }}</strong>
            </div>
            <div class="quiz-score-item is-wrong">
              <span class="quiz-score-label">ตอบผิด</span>
              <strong>{{ quizScoring.wrong }}</strong>
            </div>
          </div>

          <template v-if="!hasAnsweredQuiz">
            <div class="d-grid gap-2 mb-2">
              <button
                v-for="choice in quizChoices"
                :key="choice.key"
                type="button"
                class="btn btn-lg text-start"
                :class="selectedQuizChoice === choice.key ? 'btn-warning' : 'btn-outline-warning'"
                :disabled="quizSubmitting"
                @click="handleSubmitQuizAnswer(choice.key)"
              >
                <strong>{{ choice.key }}.</strong> {{ choice.label }}
              </button>
            </div>
            <p class="small text-muted mb-0">แตะตัวเลือกหรือกด A/B เพื่อส่งคำตอบ · พิมพ์/แตะจอจะหยุดนับถอยหลังปิดอัตโนมัติ</p>
          </template>
          <div v-else class="quiz-result-box" :class="bonusQuizAnswer.is_correct ? 'is-correct' : 'is-wrong'">
            <div class="fw-bold mb-1">
              {{ bonusQuizAnswer.is_correct ? 'ตอบถูก!' : 'ตอบผิด' }}
              <span v-if="bonusQuizAnswer.correct_order"> · อันดับที่ {{ bonusQuizAnswer.correct_order }}</span>
            </div>
            <div class="mb-1">คำตอบของคุณ: {{ choiceLabel(bonusQuizAnswer.answer) }}</div>
            <div class="fs-5 fw-bold">
              {{ bonusQuizAnswer.coins_delta > 0 ? `+${bonusQuizAnswer.coins_delta}` : bonusQuizAnswer.coins_delta }}
              เหรียญ
            </div>
          </div>
        </div>

        <div v-if="showEventCard" class="card card-farm p-3 mb-3">
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
              <small v-if="breakingNewsActive" class="text-muted">
                แสดงอีก {{ gameRoom.breakingNewsRemaining }} วินาที
              </small>
            </div>
          </div>

          <div v-if="planAdjusting" class="border rounded p-2 mb-2 bg-dark bg-opacity-10">
            <p class="small mb-2">
              โหมดปรับแผนกิจกรรม — เหลือสิทธิ์ {{ planAdjustmentsRemaining }} / {{ MAX_PLAN_ADJUSTMENTS }} ครั้ง
            </p>
            <p class="small text-muted mb-2">เลือกการ์ดจากมือด้านล่าง แล้วแตะเดือนที่แก้ไขได้</p>
            <div class="card-plan-hand-scroll mb-2">
              <button
                v-for="card in DECISION_CARDS"
                :key="card.code"
                type="button"
                class="card-plan-hand-btn"
                @click="selectHandCard(card.code)"
              >
                <CardSprite
                  :index="card.spriteIndex"
                  size="hand"
                  :glow="selectedCard === card.code"
                  :label="card.nameTh"
                />
              </button>
            </div>
            <div class="d-grid gap-2">
              <button
                type="button"
                class="btn btn-success"
                :disabled="saving"
                @click="handleFinishPlanAdjustment"
              >
                ยืนยันการปรับแผน
              </button>
              <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                :disabled="saving"
                @click="handleCancelPlanAdjustment"
              >
                ยกเลิก
              </button>
            </div>
          </div>

          <template v-else-if="!hasResponded">
            <p class="small text-muted mb-2">
              {{ isDisaster ? 'ภัยพิบัติร้ายแรง — เลือกวิธีรับมือ' : 'เลือกวิธีรับมือก่อนหมดเวลา' }}
            </p>
            <div class="d-grid gap-2">
              <button
                v-if="canStartPlanAdjustment"
                type="button"
                class="btn btn-danger"
                :disabled="responding"
                @click="handleStartPlanAdjustment"
              >
                ต้องการปรับแผนกิจกรรมใหม่
                (เหลือ {{ planAdjustmentsRemaining }} ครั้ง)
              </button>
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

        <div v-else-if="!bonusQuizActive" class="alert alert-info">
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

    <div v-if="showPlantModal" class="card-plan-modal-backdrop" @click.self="cancelPlantModal">
      <div class="card-plan-modal">
        <h4>ปลูกพืช — ระบุชื่อพืช</h4>
        <p class="small text-muted mb-2">พิมพ์ชื่อพืชที่มีในระบบเท่านั้น</p>
        <input
          v-model="cropName"
          type="text"
          placeholder="ชื่อพืช"
          maxlength="100"
          autocomplete="off"
          @input="scheduleCropValidate"
          @keyup.enter="confirmPlant"
        />
        <div
          v-if="cropFeedbackText"
          class="alert py-2 small mb-2"
          :class="cropFeedbackClass"
        >
          {{ cropFeedbackText }}
        </div>
        <div v-if="cropSuggestions.length && !cropValid" class="mb-2">
          <p class="small mb-1">ตัวเลือก (สุ่ม 3 ชนิด):</p>
          <div class="d-flex flex-wrap gap-1">
            <button
              v-for="s in cropSuggestions"
              :key="s"
              type="button"
              class="btn btn-sm btn-outline-light"
              @click="pickCropSuggestion(s)"
            >
              {{ s }}
            </button>
          </div>
        </div>
        <div class="card-plan-modal-actions">
          <button type="button" class="btn btn-outline-light flex-fill" @click="cancelPlantModal">
            ยกเลิก
          </button>
          <button
            type="button"
            class="btn btn-success flex-fill"
            :disabled="cropChecking || !cropValid"
            @click="confirmPlant"
          >
            {{ cropChecking ? 'กำลังตรวจ...' : 'วางการ์ด' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showMoveModal" class="card-plan-modal-backdrop" @click.self="showMoveModal = false">
      <div class="card-plan-modal">
        <h4>ย้ายการ์ด</h4>
        <label class="small">จากเดือน</label>
        <select v-model.number="fromMonth" class="form-select mb-2">
          <option :value="null">เลือก</option>
          <option
            v-for="c in cards.filter((card) => card.month >= month)"
            :key="c.month"
            :value="c.month"
          >
            {{ THAI_MONTHS[c.month - 1] }} ({{ c.card_code }})
          </option>
        </select>
        <label class="small">ไปเดือน</label>
        <select v-model.number="toMonth" class="form-select mb-3">
          <option :value="null">เลือก</option>
          <option
            v-for="m in 12"
            :key="m"
            :value="m"
            :disabled="m < month"
          >
            {{ THAI_MONTHS[m - 1] }}
          </option>
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

<style scoped>
.card-plan-grid--compact {
  gap: 0.35rem;
}

.card-plan-grid--compact .card-plan-month-btn.is-locked {
  opacity: 0.55;
}

.card-plan-grid--compact .card-plan-month-btn.is-current {
  outline: 2px solid #ff9800;
  outline-offset: 2px;
  border-radius: 10px;
}

.quiz-score-board {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.4rem;
}

.quiz-score-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.45rem 0.25rem;
  border-radius: 8px;
  font-size: 0.85rem;
  background: rgba(255, 193, 7, 0.12);
}

.quiz-score-item strong {
  font-size: 1.05rem;
}

.quiz-score-item.is-first {
  background: rgba(46, 125, 50, 0.15);
  color: #1b5e20;
}

.quiz-score-item.is-later {
  background: rgba(2, 136, 209, 0.12);
  color: #01579b;
}

.quiz-score-item.is-wrong {
  background: rgba(198, 40, 40, 0.12);
  color: #b71c1c;
}

.quiz-score-label {
  font-size: 0.7rem;
  opacity: 0.85;
}

.quiz-result-box {
  border-radius: 10px;
  padding: 0.85rem 1rem;
  text-align: center;
}

.quiz-result-box.is-correct {
  background: rgba(46, 125, 50, 0.12);
  color: #1b5e20;
}

.quiz-result-box.is-wrong {
  background: rgba(198, 40, 40, 0.12);
  color: #b71c1c;
}
</style>
