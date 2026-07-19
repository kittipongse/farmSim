<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import RegionSelector from '@/components/RegionSelector.vue'
import { selectRegion } from '@/services/playerService'
import { getRoomStatus } from '@/services/gameRoomService'
import { useSessionStore } from '@/stores/sessionStore'

const router = useRouter()
const session = useSessionStore()

const selectedRegion = ref(null)
const countryId = ref(session.countryId)
const loading = ref(false)
const error = ref('')

onMounted(async () => {
  if (!session.roomCode) {
    error.value = 'ไม่พบรหัสห้อง กรุณาเข้าร่วมเกมใหม่'
    return
  }

  try {
    const data = await getRoomStatus(session.roomCode)
    if (!data?.room?.country_id) {
      error.value = 'ไม่พบข้อมูลห้องเกม กรุณาเข้าร่วมใหม่'
      return
    }
    countryId.value = data.room.country_id
    session.countryId = data.room.country_id
  } catch (e) {
    if (session.countryId) {
      countryId.value = session.countryId
    } else {
      error.value = e.message || 'โหลดข้อมูลห้องไม่สำเร็จ'
    }
  }
})

const canSubmit = computed(() => !!selectedRegion.value && !!countryId.value)

async function handleSubmit() {
  if (!canSubmit.value) {
    error.value = 'กรุณาเลือกภูมิภาค'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await selectRegion(session.playerId, selectedRegion.value)
    router.push({ name: 'mobile-waiting' })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <MobileLayout>
    <h2 class="page-title mb-2">เลือกภูมิภาค</h2>
    <p class="text-muted mb-4">เลือกพื้นที่ปลูกของคุณในประเทศนี้ — เลือกภูมิภาคใดก็ได้ (ซ้ำกับผู้อื่นได้)</p>

    <div class="card card-farm p-3 mb-3">
      <RegionSelector
        v-if="countryId"
        v-model="selectedRegion"
        :country-id="countryId"
      />
      <div v-else-if="!error" class="text-center py-3 text-muted">กำลังโหลด...</div>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <button class="btn btn-farm btn-lg w-100" :disabled="loading || !canSubmit" @click="handleSubmit">
      {{ loading ? 'กำลังบันทึก...' : 'ยืนยันภูมิภาค' }}
    </button>
  </MobileLayout>
</template>
