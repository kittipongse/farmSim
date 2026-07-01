<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getCountries } from '@/services/countryService'
import { createRoom } from '@/services/gameRoomService'
import { useSessionStore } from '@/stores/sessionStore'
import '@/assets/create-country.css'
import { assetUrl } from '@/utils/paths'

const COUNTRY_META = {
  TH: {
    description: 'เกษตรกรรมเขตร้อน อุดมสมบูรณ์ด้วยพืชหลากหลาย',
    flag: '🇹🇭',
    artClass: 'th',
  },
  US: {
    description: 'เกษตรกรรมสมัยใหม่ ก้าวไกลด้วยเทคโนโลยี',
    flag: '🇺🇸',
    artClass: 'us',
  },
}

const FEATURES = [
  { icon: '🌱', label: 'เลือกพืชที่เหมาะสม' },
  { icon: '🌧️', label: 'สภาพอากาศสมจริง' },
  { icon: '🏪', label: 'ตลาดกลาง ราคาขึ้นลงตามจริง' },
  { icon: '🌪️', label: 'ภัยพิบัติ ท้าทายทุกสถานการณ์' },
  { icon: '🚁', label: 'เทคโนโลยี พัฒนาฟาร์มให้ก้าวหน้า' },
  { icon: '🏆', label: 'แข่งขัน & เรียนรู้ เป็นสุดยอดเกษตรกร' },
]

const router = useRouter()
const session = useSessionStore()

const countries = ref([])
const selectedCountry = ref(null)
const loading = ref(false)
const loadingList = ref(true)
const error = ref('')

const countryCards = computed(() =>
  countries.value.map((c) => ({
    ...c,
    meta: COUNTRY_META[c.code] || { description: '', flag: '🌍', artClass: 'th' },
  }))
)

function metaFor(code) {
  return COUNTRY_META[code] || { description: '', flag: '🌍', artClass: 'th' }
}

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

onMounted(async () => {
  try {
    const list = await getCountries()
    countries.value = list.map((c) => ({ ...c, id: Number(c.id) }))
    if (countries.value.length > 0) {
      selectedCountry.value = countries.value[0].id
    }
  } catch (e) {
    error.value = e.message
  } finally {
    loadingList.value = false
  }
})
</script>

<template>
  <div class="create-screen">
    <header class="create-topbar">
      <button type="button" class="create-top-btn" @click="router.push({ name: 'home' })">
        ⚙ ตั้งค่า
      </button>
      <div class="create-top-right">
        <div class="create-online">
          <span>👥 ผู้เล่นออนไลน์</span>
          <strong>— คน</strong>
        </div>
        <div class="create-lang">🌐 ไทย ▾</div>
      </div>
    </header>

    <section class="create-hero">
      <div class="create-logo">
        <img :src="assetUrl('resource/images/logo.png')" alt="FarmSim EDU" />
      </div>
      <h1>เลือกประเทศ</h1>
      <p>เลือกประเทศที่ต้องการเล่นเพื่อเริ่มต้นการสร้างฟาร์มของคุณ</p>
    </section>

    <main class="create-main">
      <div v-if="loadingList" class="create-loading">
        <div class="spinner-border text-light" role="status" />
      </div>

      <template v-else>
        <div v-if="error" class="create-error">{{ error }}</div>

        <div class="create-countries">
          <button
            v-for="c in countryCards"
            :key="c.id"
            type="button"
            class="create-country-pick"
            :class="[metaFor(c.code).artClass, { selected: selectedCountry === c.id }]"
            :aria-label="`${c.name_th} (${c.name_en})`"
            @click="selectedCountry = c.id"
          >
            <img
              class="create-country-img"
              :src="assetUrl('resource/images/thai-usa.png')"
              alt=""
              draggable="false"
            />
            <span class="create-country-check">{{ selectedCountry === c.id ? '✓' : '' }}</span>
          </button>
        </div>

        <div class="create-start-wrap">
          <button
            type="button"
            class="create-start-btn"
            :disabled="loading || !selectedCountry"
            @click="handleCreate"
          >
            <span class="th">{{ loading ? 'กำลังสร้างห้อง...' : 'เริ่มเล่น' }}</span>
            <span class="en">START GAME</span>
          </button>
          <div class="create-start-base" />
        </div>
      </template>
    </main>

    <footer class="create-features">
      <div v-for="(f, i) in FEATURES" :key="i" class="create-feature">
        <span class="create-feature-icon">{{ f.icon }}</span>
        {{ f.label }}
      </div>
    </footer>
  </div>
</template>
