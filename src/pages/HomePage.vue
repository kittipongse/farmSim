<script setup>
import '@/assets/phase-bg.css'
import { assetUrl } from '@/utils/paths'
</script>

<template>
  <div class="min-vh-100 d-flex align-items-center phase-screen-bg">
    <div class="container text-center py-5">
      <img
        :src="assetUrl('resource/images/logo.png')"
        alt="FarmSim EDU"
        class="phase-logo-img mb-4"
      />
      <p class="lead mb-5">เกมจำลองการเกษตรออนไลน์ สำหรับนักเรียน ม.3</p>
      <div class="row justify-content-center g-3">
        <div class="col-md-4">
          <router-link to="/create" class="btn btn-farm btn-lg w-100 py-3">
            สร้างห้องเกม
          </router-link>
          <small class="d-block mt-2">เลือกประเทศ → เปิด Dashboard</small>
        </div>
        <div class="col-md-4">
          <router-link to="/join" class="btn btn-outline-success btn-lg w-100 py-3">
            เข้าร่วมเกม
          </router-link>
          <small class="d-block mt-2">ใช้ Game PIN + ชื่อ</small>
        </div>
      </div>
    </div>
  </div>
</template>
