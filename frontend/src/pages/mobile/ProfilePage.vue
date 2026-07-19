<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import { uploadProfile } from '@/services/playerService'
import { useSessionStore } from '@/stores/sessionStore'
import { assetUrl } from '@/utils/paths'
import { prepareProfileImage } from '@/utils/imageUpload'

const router = useRouter()
const session = useSessionStore()

const cameraInput = ref(null)
const galleryInput = ref(null)
const preview = ref(null)
const file = ref(null)
const fileLabel = ref('')
const loading = ref(false)
const error = ref('')

function openCamera() {
  cameraInput.value?.click()
}

function openGallery() {
  galleryInput.value?.click()
}

async function onFileChange(e) {
  const f = e.target.files?.[0]
  e.target.value = ''
  if (!f) return
  error.value = ''
  try {
    const prepared = await prepareProfileImage(f)
    file.value = prepared
    fileLabel.value = prepared.name
    if (preview.value) URL.revokeObjectURL(preview.value)
    preview.value = URL.createObjectURL(prepared)
  } catch (err) {
    file.value = null
    fileLabel.value = ''
    error.value = err.message || 'เลือกรูปไม่สำเร็จ'
  }
}

async function handleUpload() {
  if (!file.value) {
    error.value = 'กรุณาเลือกหรือถ่ายรูปภาพ'
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
        ref="cameraInput"
        type="file"
        accept="image/*"
        capture="user"
        class="d-none"
        @change="onFileChange"
      />
      <input
        ref="galleryInput"
        type="file"
        accept="image/jpeg,image/png,image/webp,image/gif,image/*"
        class="d-none"
        @change="onFileChange"
      />

      <div class="d-grid gap-2 mb-3">
        <button type="button" class="btn btn-outline-light" @click="openCamera">
          ถ่ายรูป
        </button>
        <button type="button" class="btn btn-outline-secondary" @click="openGallery">
          เลือกจากแกลเลอรี
        </button>
      </div>

      <p v-if="fileLabel" class="small text-muted mb-2">{{ fileLabel }}</p>

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
