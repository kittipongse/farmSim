import { ref, computed } from 'vue'
import { validateCrop } from '@/services/cardService'

/**
 * ตรวจชื่อพืชตอนวางแผน — ไม่เปิดเผยฤดู/ภูมิภาค/Coin
 * ถ้าพิมพ์ผิด: แสดงชื่อสุ่ม 3 ชนิดให้เลือก
 */
export function useCropPlantInput(getPlayerId) {
  const cropName = ref('')
  const cropCheck = ref(null)
  const cropChecking = ref(false)
  let debounceTimer = null

  const cropValid = computed(() => cropCheck.value?.valid === true)

  const cropFeedbackClass = computed(() => {
    if (!cropCheck.value || cropChecking.value) return ''
    return cropCheck.value.valid ? 'alert-success' : 'alert-danger'
  })

  const cropFeedbackText = computed(() => {
    if (cropChecking.value) return 'กำลังตรวจสอบชื่อพืช...'
    if (!cropCheck.value) return ''
    return cropCheck.value.message
      || (cropCheck.value.valid
        ? `พบ "${cropCheck.value.display_name}" ในระบบ`
        : 'ไม่พบพืชในระบบ — พิมพ์ชื่อให้ถูกต้อง')
  })

  const cropSuggestions = computed(() => cropCheck.value?.suggestions || [])

  function resetCropInput() {
    cropName.value = ''
    cropCheck.value = null
    cropChecking.value = false
    if (debounceTimer) {
      clearTimeout(debounceTimer)
      debounceTimer = null
    }
  }

  function scheduleCropValidate() {
    if (debounceTimer) clearTimeout(debounceTimer)
    const name = cropName.value.trim()
    if (!name) {
      cropCheck.value = null
      return
    }
    debounceTimer = setTimeout(() => {
      runCropValidate()
    }, 350)
  }

  async function runCropValidate() {
    const name = cropName.value.trim()
    if (!name) {
      cropCheck.value = null
      return null
    }
    cropChecking.value = true
    try {
      const result = await validateCrop(getPlayerId(), { crop_name: name })
      cropCheck.value = result
      return result
    } catch (e) {
      cropCheck.value = {
        valid: false,
        message: e.message || 'ไม่สามารถตรวจสอบชื่อพืชได้',
        suggestions: [],
      }
      return cropCheck.value
    } finally {
      cropChecking.value = false
    }
  }

  function pickCropSuggestion(name) {
    cropName.value = name
    runCropValidate()
  }

  return {
    cropName,
    cropCheck,
    cropChecking,
    cropValid,
    cropFeedbackClass,
    cropFeedbackText,
    cropSuggestions,
    resetCropInput,
    scheduleCropValidate,
    runCropValidate,
    pickCropSuggestion,
  }
}
