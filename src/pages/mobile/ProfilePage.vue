<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import { uploadProfile } from '@/services/playerService'
import { useSessionStore } from '@/stores/sessionStore'
import { assetUrl } from '@/utils/paths'

const router = useRouter()
const session = useSessionStore()

const preview = ref(null)
const file = ref(null)
const loading = ref(false)
const error = ref('')
const skipped = ref(false)

function onFileChange(e) {
  const f = e.target.files?.[0]
  if (!f) return
  file.value = f
  preview.value = URL.createObjectURL(f)
}

async function handleUpload() {
  if (!file.value) {
    error.value = 'กรุณาเลือกรูปภาพ'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await uploadProfile(session.playerId, file.value)
    router.push({ name: 'mobile-region' })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function handleSkip() {
  skipped.value = true
  router.push({ name: 'mobile-region' })
}
</script>

<template>
  <MobileLayout>
    <h2 class="page-title mb-2">สวัสดี {{ session.playerName }}!</h2>
    <p class="text-muted mb-4">ถ่ายรูปโปรไฟล์ (ไม่บังคับ)</p>

    <div class="card card-farm p-4 text-center">
      <img
        :src="preview || assetUrl('resource/ui/placeholder-avatar.svg')"
        alt="โปรไฟล์"
        class="rounded-circle mb-3"
        width="120"
        height="120"
        style="object-fit: cover"
      />
      <input
        type="file"
        accept="image/*"
        capture="user"
        class="form-control mb-3"
        @change="onFileChange"
      />
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <button
        v-if="file"
        class="btn btn-farm w-100 mb-2"
        :disabled="loading"
        @click="handleUpload"
      >
        {{ loading ? 'กำลังอัปโหลด...' : 'บันทึกรูปและต่อไป' }}
      </button>
      <button class="btn btn-outline-secondary w-100" @click="handleSkip">
        ข้ามไปก่อน
      </button>
    </div>
  </MobileLayout>
</template>
