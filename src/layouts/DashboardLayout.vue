<script setup>
import '@/assets/phase-bg.css'
import { assetUrl } from '@/utils/paths'
</script>

<template>
  <div class="min-vh-100 d-flex flex-column phase-screen-bg">
    <header class="bg-success text-white py-3 px-3">
      <div class="d-flex align-items-center gap-2">
        <img :src="assetUrl('resource/logo/farmsim-edu-logo.svg')" alt="FarmSim EDU" height="40" />
        <div>
          <div class="fw-bold">FarmSim EDU</div>
          <small class="opacity-75">Dashboard</small>
        </div>
      </div>
    </header>
    <main class="flex-grow-1 p-3">
      <slot />
    </main>
  </div>
</template>
