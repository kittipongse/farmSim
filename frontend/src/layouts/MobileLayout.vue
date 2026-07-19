<script setup>
import '@/assets/phase-bg.css'
</script>

<template>
  <div class="min-vh-100 d-flex flex-column phase-screen-bg">
    <header class="bg-success text-white py-2 px-3 sticky-top">
      <div class="d-flex align-items-center justify-content-between">
        <span class="fw-bold">FarmSim EDU</span>
        <small class="opacity-75">Mobile</small>
      </div>
    </header>
    <main class="flex-grow-1 p-3 container" style="max-width: 480px">
      <slot />
    </main>
  </div>
</template>
