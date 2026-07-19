<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import CardActionAnimation from '@/components/sprites/CardActionAnimation.vue'
import { useSessionStore } from '@/stores/sessionStore'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { startLobbyPolling } from '@/services/pollingService'
import { getRoomStatus } from '@/services/gameRoomService'
import {
  getPlayerCards,
  assignCard,
  unassignCard,
  submitCards,
} from '@/services/cardService'
import {
  DECISION_CARDS,
  UI_SPRITES,
  THAI_MONTHS,
  spriteIndexForCode,
} from '@/constants/cardSprites'
import { actionSpriteForCode, hasExactActionSprite } from '@/constants/actionSprites'
import { useCropPlantInput } from '@/composables/useCropPlantInput'
import '@/assets/card-plan.css'

const session = useSessionStore()
const gameRoom = useGameRoomStore()
const router = useRouter()

const placements = ref([])
const selectedCard = ref(null)
const submitted = ref(false)
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const showPlantModal = ref(false)
const pendingMonth = ref(null)

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

const currentYear = computed(() => gameRoom.room?.current_year || 1)

const placedCount = computed(() => placements.value.length)

const placementByMonth = computed(() => {
  const map = {}
  for (const p of placements.value) {
    map[p.month] = p
  }
  return map
})

const emptyMonthCount = computed(() => Math.max(0, 12 - placedCount.value))

const canSubmit = computed(() => placedCount.value === 12)

const selectedCardMeta = computed(() =>
  DECISION_CARDS.find((card) => card.code === selectedCard.value)
)

const selectedAction = computed(() => actionSpriteForCode(selectedCard.value))

const actionPreviewTitle = computed(() =>
  selectedCardMeta.value?.nameTh || 'เลือกการ์ดเพื่อดูแอนิเมชั่น'
)

const actionPreviewHint = computed(() => {
  if (!selectedCard.value) {
    return 'แตะการ์ดด้านล่าง แล้วตัวละครจะเปลี่ยนท่าตามการ์ดที่เลือก'
  }
  if (hasExactActionSprite(selectedCard.value)) {
    return 'แอนิเมชั่นตรงกับการ์ดนี้ พร้อมวางลงเดือนที่ต้องการ'
  }
  return 'ยังไม่มี sprite เฉพาะสำหรับการ์ดนี้ จึงใช้แอนิเมชั่นสำรองไปก่อน'
})

function onPoll(data) {
  gameRoom.updateFromPoll(data)
  const status = data.room?.status
  if (status === 'simulating') {
    router.replace({ name: 'mobile-simulation' })
  } else if (status !== 'planning') {
    router.replace({ name: 'mobile-waiting' })
  }
}

async function loadCards() {
  const data = await getPlayerCards(session.playerId, currentYear.value)
  placements.value = data.cards || []
  const me = gameRoom.players.find((p) => p.id === session.playerId)
  if (me?.cards_submitted_year === currentYear.value) {
    submitted.value = true
  }
}

async function refresh() {
  loading.value = true
  error.value = ''
  try {
    const status = await getRoomStatus(session.roomCode)
    gameRoom.updateFromPoll(status)
    if (status.room?.status === 'simulating') {
      await router.replace({ name: 'mobile-simulation' })
      return
    }
    if (status.room?.status !== 'planning') {
      await router.replace({ name: 'mobile-waiting' })
      return
    }
    await loadCards()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function selectHandCard(code) {
  selectedCard.value = selectedCard.value === code ? null : code
}

function monthSprite(month) {
  const placed = placementByMonth.value[month]
  if (placed) return spriteIndexForCode(placed.card_code)
  return UI_SPRITES.PLACE
}

async function placeOnMonth(month) {
  if (submitted.value || saving.value) return

  const existing = placementByMonth.value[month]
  if (existing) {
    saving.value = true
    error.value = ''
    try {
      const data = await unassignCard(session.playerId, {
        year: currentYear.value,
        month,
      })
      placements.value = data.cards || []
    } catch (e) {
      error.value = e.message
    } finally {
      saving.value = false
    }
    return
  }

  if (!selectedCard.value) return

  if (selectedCard.value === 'PLANT') {
    pendingMonth.value = month
    resetCropInput()
    showPlantModal.value = true
    return
  }

  await doAssign(month, selectedCard.value, null)
}

async function doAssign(month, cardCode, crop) {
  saving.value = true
  error.value = ''
  try {
    const data = await assignCard(session.playerId, {
      year: currentYear.value,
      card_code: cardCode,
      month,
      crop_name: crop,
    })
    placements.value = data.cards || []
    // คงการ์ดที่เลือกไว้ เพื่อวางซ้ำเดือนถัดไปได้ทันที
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
  await doAssign(pendingMonth.value, 'PLANT', check.display_name || name)
  pendingMonth.value = null
  resetCropInput()
}

function cancelPlant() {
  showPlantModal.value = false
  pendingMonth.value = null
  resetCropInput()
}

async function handleSubmit() {
  if (!canSubmit.value) {
    error.value = 'วางการ์ดให้ครบ 12 เดือนก่อนยืนยัน'
    return
  }
  saving.value = true
  error.value = ''
  try {
    await submitCards(session.playerId, currentYear.value)
    submitted.value = true
    selectedCard.value = null
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

watch(currentYear, () => {
  loadCards().catch(() => {})
})

onMounted(async () => {
  await refresh()
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
        <h2 class="card-plan-year">ปีที่ {{ currentYear }} — วางแผนการ์ด</h2>
        <p class="card-plan-progress">
          วางแล้ว {{ placedCount }} / 12 เดือน
          <span v-if="emptyMonthCount > 0">
            · เหลือ {{ emptyMonthCount }} เดือนว่าง
          </span>
        </p>
      </div>

      <div v-if="loading" class="text-center py-4">
        <div class="spinner-border text-success" role="status" />
      </div>

      <template v-else-if="submitted">
        <div class="card card-farm card-plan-done p-4">
          <h3 class="text-success mb-2">ยืนยันแผนแล้ว!</h3>
          <p class="text-muted small mb-0">
            รอผู้เล่นคนอื่นวางการ์ดครบ — ดูความคืบหน้าที่ Dashboard
          </p>
        </div>
      </template>

      <template v-else>
        <div class="card card-farm card-action-preview p-3 mb-3">
          <CardActionAnimation
            :code="selectedCard"
            :playing="!!selectedCard"
            :size="150"
            :fps="7"
          />
          <div class="card-action-preview-copy">
            <p class="card-action-preview-label mb-1">ตัวอย่างท่าทาง</p>
            <h3 class="card-action-preview-title">{{ actionPreviewTitle }}</h3>
            <p class="card-action-preview-hint">{{ actionPreviewHint }}</p>
            <p v-if="selectedCard" class="card-action-preview-code mb-0">
              Sprite: {{ selectedAction.nameTh }}
            </p>
          </div>
        </div>

        <div class="card-plan-grid">
          <div
            v-for="month in 12"
            :key="month"
            class="card-plan-month"
          >
            <span class="card-plan-month-label">{{ THAI_MONTHS[month - 1] }}</span>
            <button
              type="button"
              class="card-plan-month-btn"
              :disabled="saving"
              @click="placeOnMonth(month)"
            >
              <CardSprite
                :index="monthSprite(month)"
                size="slot-lg"
                :glow="!!placementByMonth[month]"
                :label="placementByMonth[month]?.card_code || `เดือน ${month}`"
              />
            </button>
          </div>
        </div>

        <div class="card-plan-hand">
          <p class="card-plan-hand-title">
            {{ selectedCard ? 'แตะเดือนเพื่อวาง (วางซ้ำได้)' : 'เลือกการ์ดจากมือ — ใช้ซ้ำได้' }}
          </p>
          <div class="card-plan-hand-scroll">
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
        </div>

        <div class="card-plan-actions">
          <button
            type="button"
            class="btn btn-success btn-lg card-plan-submit"
            :disabled="saving || !canSubmit"
            @click="handleSubmit"
          >
            {{ saving ? 'กำลังบันทึก...' : 'ยืนยันแผนปีนี้' }}
          </button>
        </div>
      </template>

      <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
    </div>

    <div v-if="showPlantModal" class="card-plan-modal-backdrop" @click.self="cancelPlant">
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
          <button type="button" class="btn btn-outline-light flex-fill" @click="cancelPlant">
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
  </MobileLayout>
</template>
