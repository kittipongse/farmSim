<script setup>
import { computed } from 'vue'
import { spritePosition } from '@/constants/cardSprites'

const props = defineProps({
  index: { type: Number, required: true },
  glow: { type: Boolean, default: false },
  dim: { type: Boolean, default: false },
  size: { type: String, default: 'slot' },
  label: { type: String, default: '' },
})

const style = computed(() => {
  const { x, y } = spritePosition(props.index)
  return {
    backgroundPosition: `${x}% ${y}%`,
  }
})

const sizeClass = computed(() => {
  if (props.size === 'hand') return 'card-sprite--hand'
  if (props.size === 'slot-lg') return 'card-sprite--slot-lg'
  return 'card-sprite--slot'
})
</script>

<template>
  <span
    class="card-sprite"
    :class="[sizeClass, { 'card-sprite--glow': glow, 'card-sprite--dim': dim }]"
    :style="style"
    :aria-label="label"
    role="img"
  />
</template>
