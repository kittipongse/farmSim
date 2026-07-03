<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { actionSpriteForCode } from '@/constants/actionSprites'

const props = defineProps({
  code: { type: String, default: '' },
  playing: { type: Boolean, default: true },
  fps: { type: Number, default: 8 },
  size: { type: Number, default: 180 },
  loop: { type: Boolean, default: true },
})

const frame = ref(0)
let timer = null

const sprite = computed(() => actionSpriteForCode(props.code))

const frameStyle = computed(() => {
  const current = Math.min(frame.value, sprite.value.frames - 1)
  const col = current % sprite.value.cols
  const row = Math.floor(current / sprite.value.cols)
  const x = sprite.value.cols > 1 ? (col / (sprite.value.cols - 1)) * 100 : 0
  const y = sprite.value.rows > 1 ? (row / (sprite.value.rows - 1)) * 100 : 0

  return {
    width: `${props.size}px`,
    aspectRatio: sprite.value.aspectRatio,
    backgroundImage: `url("${sprite.value.src}")`,
    backgroundSize: `${sprite.value.cols * 100}% ${sprite.value.rows * 100}%`,
    backgroundPosition: `${x}% ${y}%`,
  }
})

function stop() {
  if (!timer) return
  clearInterval(timer)
  timer = null
}

function start() {
  stop()
  if (!props.playing || sprite.value.frames <= 1) return

  const delay = Math.max(80, Math.round(1000 / props.fps))
  timer = setInterval(() => {
    if (props.loop) {
      frame.value = (frame.value + 1) % sprite.value.frames
      return
    }
    frame.value = Math.min(frame.value + 1, sprite.value.frames - 1)
  }, delay)
}

watch(
  () => [props.code, props.playing, props.fps, props.loop],
  () => {
    frame.value = 0
    start()
  },
  { immediate: true }
)

onUnmounted(stop)
</script>

<template>
  <span
    class="card-action-animation"
    :style="frameStyle"
    :aria-label="sprite.nameTh"
    role="img"
  />
</template>

<style scoped>
.card-action-animation {
  display: block;
  max-width: 100%;
  background-repeat: no-repeat;
  border-radius: 14px;
  filter:
    drop-shadow(0 8px 14px rgba(0, 0, 0, 0.28))
    drop-shadow(0 0 12px rgba(255, 209, 102, 0.22));
}
</style>
