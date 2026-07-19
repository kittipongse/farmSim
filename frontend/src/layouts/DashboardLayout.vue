<script setup>
import '@/assets/phase-bg.css'
import { assetUrl } from '@/utils/paths'

defineProps({
  fullscreen: { type: Boolean, default: false },
})
</script>

<template>
  <div
    class="dashboard-layout d-flex flex-column phase-screen-bg"
    :class="{ 'dashboard-layout--fullscreen': fullscreen }"
  >
    <header v-if="!fullscreen" class="bg-success text-white py-3 px-3">
      <div class="d-flex align-items-center gap-2">
        <img :src="assetUrl('resource/logo/farmsim-edu-logo.svg')" alt="FarmSim EDU" height="40" />
        <div>
          <div class="fw-bold">FarmSim EDU</div>
          <small class="opacity-75">Dashboard</small>
        </div>
      </div>
    </header>
    <main class="dashboard-layout-main flex-grow-1" :class="{ 'p-3': !fullscreen }">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.dashboard-layout {
  min-height: 100vh;
  min-height: 100dvh;
}

.dashboard-layout--fullscreen {
  width: 100vw;
  height: 100vh;
  height: 100dvh;
  min-height: 0;
  overflow: hidden;
}

.dashboard-layout--fullscreen .dashboard-layout-main {
  min-height: 0;
  overflow: hidden;
}
</style>
