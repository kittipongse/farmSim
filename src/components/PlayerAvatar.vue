<script setup>
import { ref, computed, watch } from 'vue'
import { profileImageUrl } from '@/utils/media'

const props = defineProps({
  name: { type: String, default: '' },
  image: { type: String, default: null },
  size: { type: Number, default: 80 },
})

const useImage = ref(hasValidImage(props.image))

function hasValidImage(image) {
  return !!profileImageUrl(image)
}

const initial = computed(() => {
  const n = (props.name || '?').trim()
  return n ? n.charAt(0).toUpperCase() : '?'
})

const imageSrc = computed(() => profileImageUrl(props.image))

const style = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  fontSize: `${Math.round(props.size * 0.4)}px`,
}))

function onError() {
  useImage.value = false
}

watch(
  () => props.image,
  (value) => {
    useImage.value = hasValidImage(value)
  }
)
</script>

<template>
  <img
    v-if="useImage"
    :src="imageSrc"
    :alt="name || 'โปรไฟล์'"
    class="rounded-circle"
    :style="style"
    @error="onError"
  />
  <div
    v-else
    class="avatar-initial rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white"
    :style="style"
    :aria-label="name || 'โปรไฟล์'"
  >
    {{ initial }}
  </div>
</template>

<style scoped>
.avatar-initial {
  background: linear-gradient(135deg, #2d6a4f, #40916c);
  object-fit: cover;
  flex-shrink: 0;
}

img {
  object-fit: cover;
}
</style>
