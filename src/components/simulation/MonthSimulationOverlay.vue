<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { Application, Text, Graphics } from 'pixi.js'
import CardSprite from '@/components/cards/CardSprite.vue'
import { THAI_MONTHS } from '@/constants/cardSprites'

const props = defineProps({
  active: { type: Boolean, default: false },
  year: { type: Number, default: 1 },
  month: { type: Number, default: 1 },
  remaining: { type: Number, default: 15 },
  event: { type: Object, default: null },
})

const containerRef = ref(null)
let app = null
let monthText = null
let timerText = null

const monthLabel = computed(() => THAI_MONTHS[Math.max(0, props.month - 1)] || '')

async function initPixi() {
  if (!containerRef.value || app) return

  app = new Application()
  await app.init({
    width: 360,
    height: 120,
    backgroundAlpha: 0,
    antialias: true,
  })
  containerRef.value.appendChild(app.canvas)

  const bg = new Graphics()
  bg.roundRect(0, 0, 360, 120, 16)
  bg.fill({ color: 0x0c1c34, alpha: 0.75 })
  app.stage.addChild(bg)

  monthText = new Text({
    text: '',
    style: {
      fontFamily: 'Segoe UI, sans-serif',
      fontSize: 28,
      fontWeight: '700',
      fill: 0xffffff,
    },
  })
  monthText.x = 20
  monthText.y = 20
  app.stage.addChild(monthText)

  timerText = new Text({
    text: '',
    style: {
      fontFamily: 'Segoe UI, sans-serif',
      fontSize: 18,
      fontWeight: '600',
      fill: 0xffd54f,
    },
  })
  timerText.x = 20
  timerText.y = 70
  app.stage.addChild(timerText)

  refreshTexts()
}

function refreshTexts() {
  if (!monthText || !timerText) return
  monthText.text = `ปี ${props.year} · ${monthLabel.value}`
  timerText.text = `เหลือ ${props.remaining} วินาที`
}

watch(() => [props.year, props.month, props.remaining], refreshTexts)

watch(() => props.active, async (val) => {
  if (val) {
    await initPixi()
    refreshTexts()
  }
})

onMounted(async () => {
  if (props.active) {
    await initPixi()
  }
})

onUnmounted(() => {
  app?.destroy(true, { children: true })
  app = null
})
</script>

<template>
  <div v-if="active" class="sim-overlay">
    <div class="sim-overlay-inner">
      <div ref="containerRef" class="sim-pixi" />
      <div v-if="event" class="sim-event">
        <CardSprite
          :index="event.sprite_index ?? 8"
          size="hand"
          :glow="true"
          :label="event.name_th"
        />
        <div class="sim-event-text">
          <strong>Breaking News</strong>
          <div>{{ event.name_th }}</div>
          <small class="text-muted">{{ event.name_en }}</small>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sim-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  z-index: 1040;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 1.5rem 1rem;
  pointer-events: none;
}

.sim-overlay-inner {
  width: min(100%, 480px);
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-items: center;
}

.sim-pixi {
  width: 100%;
  display: flex;
  justify-content: center;
}

.sim-event {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  background: rgba(12, 28, 52, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  padding: 0.75rem 1rem;
  color: #fff;
  font-weight: 700;
  text-shadow: var(--fs-text-shadow);
}

.sim-event-text strong {
  color: #ff9800;
  display: block;
  margin-bottom: 0.25rem;
}
</style>
