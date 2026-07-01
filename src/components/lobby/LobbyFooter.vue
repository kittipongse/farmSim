<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const tips = [
  'วางแผนให้ดี ใช้ทรัพยากรอย่างคุ้มค่า แล้วฟาร์มของคุณจะเติบโตอย่างยั่งยืน!',
  'เลือกพืชให้เหมาะกับภูมิภาคและฤดูกาล',
  'จัดการน้ำและแรงงานให้สมดุลทุกเดือน',
  'ติดตามข่าว Breaking News เพื่อปรับแผนได้ทัน',
]

const clock = ref('')
const tipIndex = ref(0)
const playing = ref(false)
let clockTimer = null
let tipTimer = null

function updateClock() {
  const now = new Date()
  clock.value = now.toLocaleString('th-TH', {
    hour: '2-digit',
    minute: '2-digit',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

onMounted(() => {
  updateClock()
  clockTimer = setInterval(updateClock, 30_000)
  tipTimer = setInterval(() => {
    tipIndex.value = (tipIndex.value + 1) % tips.length
  }, 12_000)
})

onUnmounted(() => {
  clearInterval(clockTimer)
  clearInterval(tipTimer)
})
</script>

<template>
  <footer class="lobby-footer">
    <div class="lobby-music">
      <button type="button" class="lobby-music-btn" @click="playing = !playing">
        {{ playing ? '⏸' : '▶' }}
      </button>
      <span>🎵 Farm Morning</span>
    </div>
    <div class="lobby-tip">
      <strong>TIP</strong> {{ tips[tipIndex] }}
    </div>
    <div class="lobby-clock">☀️ {{ clock }}</div>
  </footer>
</template>
