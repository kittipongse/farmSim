<script setup>
import { ref, onMounted } from 'vue'
import { getRegions } from '@/services/countryService'

const props = defineProps({
  countryId: { type: Number, required: true },
  modelValue: { type: Number, default: null },
  takenRegionIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const regions = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    regions.value = await getRegions(props.countryId)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})

function select(id) {
  if (props.takenRegionIds.includes(id)) return
  emit('update:modelValue', id)
}

function isTaken(id) {
  return props.takenRegionIds.includes(id)
}
</script>

<template>
  <div>
    <div v-if="loading" class="text-center py-4">กำลังโหลดภูมิภาค...</div>
    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-else class="row g-3">
      <div v-for="r in regions" :key="r.id" class="col-6">
        <button
          type="button"
          class="btn w-100 text-start p-3 h-100"
          :class="[
            modelValue === r.id ? 'btn-farm' : 'btn-outline-secondary',
            isTaken(r.id) ? 'disabled opacity-50' : '',
          ]"
          :disabled="isTaken(r.id)"
          @click="select(r.id)"
        >
          <div class="fw-bold">{{ r.name_th }}</div>
          <small :class="modelValue === r.id ? 'text-white-50' : 'text-muted'">{{ r.name_en }}</small>
          <div class="mt-1 small" :class="modelValue === r.id ? 'text-white-50' : 'text-muted'">
            {{ r.description }}
          </div>
          <div class="mt-1 small">
            เหรียญ {{ r.default_coins }} | น้ำ {{ r.default_water }}%
          </div>
          <span v-if="isTaken(r.id)" class="badge bg-danger mt-1">ถูกเลือกแล้ว</span>
        </button>
      </div>
    </div>
  </div>
</template>
