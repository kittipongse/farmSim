<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import PlantingSeasonGuide from '@/components/PlantingSeasonGuide.vue'
import { getPlantingGuide } from '@/services/countryService'
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
import '@/assets/card-plan.css'

const session = useSessionStore()
const gameRoom = useGameRoomStore()
const router = useRouter()

const placements = ref([])
const selectedCard = ref(null)
const submitted = ref(false)
const cropWarnings = ref([])
const plantingGuide = ref(null)
const showGuide = ref(true)
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const showPlantModal = ref(false)
const pendingMonth = ref(null)
const cropName = ref('')

let stopPolling = null

const currentYear = computed(() => gameRoom.room?.current_year || 1)

const myRegionId = computed(() => {
  const me = gameRoom.players.find((p) => p.id === session.playerId)
  return me?.region_id || null
})

const placedCount = computed(() => placements.value.length)

const placementByMonth = computed(() => {
  const map = {}
  for (const p of placements.value) {
    map[p.month] = p
  }
  return map
})

const usedCodes = computed(() => new Set(placements.value.map((p) => p.card_code)))

const autoMonthCount = computed(() => Math.max(0, 12 - placedCount.value))

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
  if (data.placed_count >= 8) {
    const me = gameRoom.players.find((p) => p.id === session.playerId)
    if (me?.cards_submitted_year === currentYear.value) {
      submitted.value = true
    }
  }
}

async function loadPlantingGuide() {
  if (!myRegionId.value) return
  try {
    plantingGuide.value = await getPlantingGuide(myRegionId.value)
  } catch {
    plantingGuide.value = null
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
    await loadPlantingGuide()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function selectHandCard(code) {
  if (usedCodes.value.has(code)) return
  selectedCard.value = selectedCard.value === code ? null : code
}

function monthSprite(month) {
  const placed = placementByMonth.value[month]
  if (placed) return spriteIndexForCode(placed.card_code)
  if (placedCount.value >= 8) return UI_SPRITES.AUTO
  return UI_SPRITES.PLACE
}

function isAutoMonth(month) {
  return placedCount.value >= 8 && !placementByMonth.value[month]
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
      if (selectedCard.value === existing.card_code) {
        selectedCard.value = existing.card_code
      }
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
    cropName.value = ''
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
    selectedCard.value = null
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
  showPlantModal.value = false
  await doAssign(pendingMonth.value, 'PLANT', name)
  pendingMonth.value = null
}

function cancelPlant() {
  showPlantModal.value = false
  pendingMonth.value = null
}

async function handleSubmit() {
  if (placedCount.value < 8) {
    error.value = 'วางการ์ดให้ครบ 8 ใบก่อนยืนยัน'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const result = await submitCards(session.playerId, currentYear.value)
    submitted.value = true
    cropWarnings.value = result?.crop_plan?.warnings?.length
      ? result.crop_plan.warnings
      : (result?.crop_plan?.warning ? [result.crop_plan.warning] : [])
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
          วางแล้ว {{ placedCount }} / 8 การ์ด
          <span v-if="placedCount < 8">
            · เหลือ {{ autoMonthCount }} เดือนว่าง
          </span>
        </p>
      </div>

      <div v-if="loading" class="text-center py-4">
        <div class="spinner-border text-success" role="status" />
      </div>

      <template v-else-if="submitted">
        <div class="card card-farm card-plan-done p-4">
          <h3 class="text-success mb-2">ยืนยันแผนแล้ว!</h3>
          <div v-if="cropWarnings.length" class="mb-2">
            <div
              v-for="(w, i) in cropWarnings"
              :key="i"
              class="alert alert-warning small py-2 mb-1"
            >
              {{ w }}
            </div>
          </div>
          <p class="text-muted small mb-0">
            รอผู้เล่นคนอื่นวางการ์ดครบ — ดูความคืบหน้าที่ Dashboard
          </p>
        </div>
      </template>

      <template v-else>
        <div v-if="plantingGuide" class="card card-farm p-3 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">คู่มือฤดูปลูก</h6>
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary"
              @click="showGuide = !showGuide"
            >
              {{ showGuide ? 'ย่อ' : 'ขยาย' }}
            </button>
          </div>
          <PlantingSeasonGuide v-if="showGuide" :guide="plantingGuide" />
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
              :class="{ 'is-auto': isAutoMonth(month) }"
              :disabled="saving || (isAutoMonth(month) && !placementByMonth[month])"
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
            {{ selectedCard ? 'แตะเดือนเพื่อวางการ์ด' : 'เลือกการ์ดจากมือ' }}
          </p>
          <div class="card-plan-hand-scroll">
            <button
              v-for="card in DECISION_CARDS"
              :key="card.code"
              type="button"
              class="card-plan-hand-btn"
              :class="{
                'is-used': usedCodes.has(card.code),
              }"
              @click="selectHandCard(card.code)"
            >
              <CardSprite
                :index="card.spriteIndex"
                size="hand"
                :glow="selectedCard === card.code"
                :dim="usedCodes.has(card.code)"
                :label="card.nameTh"
              />
            </button>
          </div>
        </div>

        <div class="card-plan-actions">
          <p class="card-plan-hint">
            แตะการ์ดในมือ → แตะช่องเดือนเพื่อวาง · แตะการ์ดที่วางแล้วเพื่อยกเลิก
          </p>
          <button
            type="button"
            class="btn btn-success btn-lg card-plan-submit"
            :disabled="saving || placedCount < 8"
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
        <input
          v-model="cropName"
          type="text"
          placeholder="เช่น ข้าว, ข้าวโพด"
          maxlength="100"
          @keyup.enter="confirmPlant"
        />
        <div class="card-plan-modal-actions">
          <button type="button" class="btn btn-outline-light flex-fill" @click="cancelPlant">
            ยกเลิก
          </button>
          <button type="button" class="btn btn-success flex-fill" @click="confirmPlant">
            วางการ์ด
          </button>
        </div>
      </div>
    </div>
  </MobileLayout>
</template>
