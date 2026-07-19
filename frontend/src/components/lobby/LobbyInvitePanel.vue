<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import QRCode from 'qrcode'
import { resolvePublicBaseUrl } from '@/utils/publicUrl'

const props = defineProps({
  roomCode: { type: String, required: true },
  pin: { type: String, required: true },
})

const emit = defineEmits(['copied'])

const canvasRef = ref(null)
const baseUrl = ref(window.location.origin)

const joinUrl = computed(() => `${baseUrl.value}/join/${props.roomCode}?fresh=1`)
const displayCode = computed(() => props.roomCode.toUpperCase())

async function renderQr() {
  if (!canvasRef.value) return
  await QRCode.toCanvas(canvasRef.value, joinUrl.value, {
    width: 168,
    margin: 1,
    color: { dark: '#1a2e44', light: '#ffffff' },
  })
}

async function copyText(text, label) {
  try {
    await navigator.clipboard.writeText(text)
    emit('copied', label)
  } catch {
    emit('copied', 'คัดลอกไม่สำเร็จ')
  }
}

async function shareLink() {
  const shareData = {
    title: 'FarmSim EDU',
    text: `เข้าร่วมห้อง ${displayCode.value} — PIN ${props.pin}`,
    url: joinUrl.value,
  }
  if (navigator.share) {
    try {
      await navigator.share(shareData)
      return
    } catch {
      // fall through to copy
    }
  }
  await copyText(joinUrl.value, 'คัดลอกลิงก์แล้ว')
}

onMounted(async () => {
  baseUrl.value = await resolvePublicBaseUrl()
  await renderQr()
})

watch([() => props.roomCode, joinUrl], renderQr)
</script>

<template>
  <div class="lobby-panel lobby-invite">
    <h3>เชิญเพื่อนเข้าห้อง</h3>
    <div class="lobby-invite-qr">
      <canvas ref="canvasRef" />
    </div>
    <p class="lobby-invite-hint">สแกน QR Code เพื่อเข้าร่วมห้อง</p>

    <div class="lobby-pin-box">
      <div class="lobby-pin-label">หรือ รหัสห้อง (PIN)</div>
      <div class="lobby-pin-row">
        <span class="lobby-pin-code">{{ displayCode }}</span>
        <button type="button" class="lobby-copy-btn" title="คัดลอก" @click="copyText(displayCode, 'คัดลอกรหัสห้องแล้ว')">
          📋
        </button>
      </div>
      <div class="lobby-pin-game">Game PIN: <strong>{{ pin }}</strong></div>
    </div>

    <div class="lobby-share-row">
      <button type="button" class="lobby-share-btn" title="คัดลอกลิงก์" @click="copyText(joinUrl, 'คัดลอกลิงก์แล้ว')">🔗</button>
      <button type="button" class="lobby-share-btn" title="แชร์" @click="shareLink">↗</button>
    </div>
  </div>
</template>
