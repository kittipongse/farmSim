<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getCountries } from '@/services/countryService'
import { createRoom } from '@/services/gameRoomService'
import { useSessionStore } from '@/stores/sessionStore'
import { assetUrl } from '@/utils/paths'

const router = useRouter()
const session = useSessionStore()

const countries = ref([])
const selectedCountry = ref(null)
const loading = ref(false)
const error = ref('')

onMounted(async () => {
  try {
    countries.value = await getCountries()
  } catch (e) {
    error.value = e.message
  }
})

async function handleCreate() {
  if (!selectedCountry.value) {
    error.value = 'กรุณาเลือกประเทศ'
    return
  }
  loading.value = true
  error.value = ''
  try {
    const room = await createRoom(selectedCountry.value)
    session.setRoomOnly(room)
    router.push({ name: 'dashboard-lobby', params: { roomCode: room.room_code } })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <label class="form-label fw-semibold">เลือกประเทศ</label>
    <div class="row g-3 mb-4">
      <div v-for="c in countries" :key="c.id" class="col-6">
        <button
          type="button"
          class="btn w-100 py-3"
          :class="selectedCountry === c.id ? 'btn-farm' : 'btn-outline-secondary'"
          @click="selectedCountry = c.id"
        >
          <img
            :src="c.code === 'TH' ? assetUrl('resource/regions/thailand.svg') : assetUrl('resource/regions/usa.svg')"
            :alt="c.name_th"
            height="48"
            class="mb-2"
          />
          <div>{{ c.name_th }}</div>
          <small class="text-muted">{{ c.name_en }}</small>
        </button>
      </div>
    </div>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <button class="btn btn-farm btn-lg w-100" :disabled="loading" @click="handleCreate">
      {{ loading ? 'กำลังสร้าง...' : 'สร้างห้องเกม' }}
    </button>
  </div>
</template>
