<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import QRCode from 'qrcode'
import { resolvePublicBaseUrl } from '@/utils/publicUrl'

const props = defineProps({
  roomCode: { type: String, required: true },
  pin: { type: String, required: true },
})

const canvasRef = ref(null)
const baseUrl = ref(window.location.origin)
const usesLanUrl = computed(() => {
  const host = new URL(baseUrl.value).hostname
  return host !== 'localhost' && host !== '127.0.0.1'
})

const joinUrl = computed(() => `${baseUrl.value}/join/${props.roomCode}?fresh=1`)

async function renderQr() {
  if (!canvasRef.value) return
  await QRCode.toCanvas(canvasRef.value, joinUrl.value, {
    width: 200,
    margin: 2,
    color: { dark: '#2d6a4f', light: '#ffffff' },
  })
}

onMounted(async () => {
  baseUrl.value = await resolvePublicBaseUrl()
  await renderQr()
})

watch([() => props.roomCode, joinUrl], renderQr)
</script>

<template>
  <div class="text-center">
    <canvas ref="canvasRef" class="border rounded bg-white p-2 mb-3" />
    <div class="mb-2">
      <span class="text-muted">รหัสห้อง</span>
      <div class="fs-3 fw-bold text-success">{{ roomCode }}</div>
    </div>
    <div class="mb-2">
      <span class="text-muted">Game PIN</span>
      <div class="fs-2 fw-bold text-warning">{{ pin }}</div>
    </div>
    <small class="text-muted d-block text-break">{{ joinUrl }}</small>
    <small v-if="usesLanUrl" class="text-success d-block mt-2">
      มือถือต้องอยู่ Wi-Fi เดียวกับคอมพิวเตอร์
    </small>
    <small v-else class="text-warning d-block mt-2">
      ไม่พบ IP ในเครือข่าย — ตั้งค่า VITE_PUBLIC_BASE_URL ใน frontend/.env.local
    </small>
  </div>
</template>
