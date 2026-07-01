<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { Application, Text, Graphics } from 'pixi.js'

const props = defineProps({
  active: { type: Boolean, default: false },
  remaining: { type: Number, default: 3 },
})

const emit = defineEmits(['complete'])

const containerRef = ref(null)
let app = null
let textObj = null
let completeEmitted = false

const displayNumber = computed(() => {
  const n = Math.ceil(props.remaining)
  if (n <= 0) return 0
  return Math.min(3, n)
})

async function initPixi() {
  if (!containerRef.value || app) return

  app = new Application()
  await app.init({
    width: 320,
    height: 320,
    backgroundAlpha: 0,
    antialias: true,
  })

  containerRef.value.appendChild(app.canvas)

  const bg = new Graphics()
  bg.circle(160, 160, 140)
  bg.fill({ color: 0x2d6a4f, alpha: 0.15 })
  app.stage.addChild(bg)

  textObj = new Text({
    text: '3',
    style: {
      fontFamily: 'Segoe UI, sans-serif',
      fontSize: 120,
      fontWeight: '700',
      fill: 0x2d6a4f,
    },
  })
  textObj.anchor.set(0.5)
  textObj.x = 160
  textObj.y = 160
  app.stage.addChild(textObj)
}

function updateNumber(n) {
  if (!textObj) return
  if (n > 0) {
    textObj.text = String(n)
    completeEmitted = false
  } else if (!completeEmitted) {
    textObj.text = 'GO!'
    completeEmitted = true
    setTimeout(() => emit('complete'), 600)
  }
}

watch(displayNumber, (val) => {
  if (props.active) updateNumber(val)
})

watch(() => props.active, async (val) => {
  if (val) {
    completeEmitted = false
    await initPixi()
    updateNumber(displayNumber.value)
  } else {
    completeEmitted = false
  }
})

onMounted(async () => {
  if (props.active) {
    await initPixi()
    updateNumber(displayNumber.value)
  }
})

onUnmounted(() => {
  app?.destroy(true, { children: true })
  app = null
})
</script>

<template>
  <div v-if="active" class="countdown-overlay d-flex flex-column align-items-center justify-content-center">
    <h4 class="text-white mb-3">เตรียมพร้อม!</h4>
    <div ref="containerRef" />
  </div>
</template>

<style scoped>
.countdown-overlay {
  position: fixed;
  inset: 0;
  background: rgba(27, 67, 50, 0.85);
  z-index: 1050;
}
</style>
