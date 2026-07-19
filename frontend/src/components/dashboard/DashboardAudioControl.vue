<script setup>
defineProps({
  audioReady: { type: Boolean, default: false },
  muted: { type: Boolean, default: false },
})

const emit = defineEmits(['enable', 'toggle-mute'])
</script>

<template>
  <div class="dashboard-audio-control">
    <button
      v-if="!audioReady"
      type="button"
      class="btn btn-sm btn-warning fw-bold"
      @click="emit('enable')"
    >
      🔊 เปิดเสียง
    </button>
    <button
      v-else
      type="button"
      class="btn btn-sm"
      :class="muted ? 'btn-outline-light' : 'btn-light'"
      @click="emit('toggle-mute')"
    >
      {{ muted ? '🔇 ปิดเสียงอยู่' : '🔊 เสียงเปิด' }}
    </button>
  </div>
</template>

<style scoped>
.dashboard-audio-control {
  position: fixed;
  top: 0.65rem;
  right: 0.75rem;
  z-index: 1080;
}
</style>
