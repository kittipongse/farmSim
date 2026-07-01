<script setup>
import { computed } from 'vue'

const props = defineProps({
  countryNameTh: { type: String, default: '' },
  countryNameEn: { type: String, default: '' },
  countryCode: { type: String, default: '' },
  players: { type: Array, default: () => [] },
})

const regionSummary = computed(() => {
  const regions = [...new Set(
    (props.players || [])
      .map((p) => p.region_name_th)
      .filter(Boolean)
  )]
  if (regions.length === 0) return 'เลือกเมื่อเข้าร่วมเกม'
  if (regions.length <= 2) return regions.join(', ')
  return `${regions.slice(0, 2).join(', ')} +${regions.length - 2}`
})

const farmType = computed(() => {
  if (props.countryCode === 'US') return 'พืชไร่ + ปศุสัตว์'
  if (props.countryCode === 'TH') return 'พืชไร่ + ปศุสัตว์'
  return 'พืชไร่ + ปศุสัตว์'
})
</script>

<template>
  <div>
    <div class="lobby-panel lobby-info">
      <h3>ข้อมูลเกม</h3>
      <ul class="lobby-info-list">
        <li>
          <span class="lobby-info-icon">🌍</span>
          <span>
            <strong>{{ countryNameTh || '—' }}</strong>
            <span v-if="countryNameEn"> ({{ countryNameEn }})</span>
          </span>
        </li>
        <li>
          <span class="lobby-info-icon">🗺️</span>
          <span>ภูมิภาค: {{ regionSummary }}</span>
        </li>
        <li>
          <span class="lobby-info-icon">🌾</span>
          <span>ประเภท: {{ farmType }}</span>
        </li>
      </ul>
    </div>

    <div class="lobby-panel lobby-rules">
      <h3>กติกาโดยย่อ</h3>
      <ul>
        <li>ผู้เล่น 8 คนต่อห้อง</li>
        <li>วางแผนปลูกด้วยการ์ด 8 ใบต่อปี</li>
        <li>เปลี่ยนแผนได้ปีละ 2 ครั้ง</li>
        <li>เล่นครบ 5 ปี (60 เดือน)</li>
        <li>คะแนนจากผลผลิต ทรัพยากร ความยั่งยืน และความรู้</li>
      </ul>
    </div>
  </div>
</template>
