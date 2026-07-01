<script setup>
import { THAI_MONTHS } from '@/constants/cardSprites'

defineProps({
  guide: {
    type: Object,
    required: true,
  },
  collapsed: {
    type: Boolean,
    default: false,
  },
})

function monthLabels(months) {
  return (months || []).map((m) => THAI_MONTHS[m - 1] || m).join(', ')
}
</script>

<template>
  <div class="planting-guide">
    <div v-if="guide.region" class="small text-muted mb-2">
      ภูมิภาค {{ guide.region.name_th }} · {{ guide.region.country_name_th }}
    </div>

    <div v-if="guide.seasons?.length" class="mb-3">
      <div class="fw-bold small mb-1">ฤดูกาลในประเทศ</div>
      <div class="row g-2">
        <div v-for="s in guide.seasons" :key="s.id" class="col-12 col-md-6">
          <div class="border rounded p-2 small bg-light">
            <div class="fw-semibold">{{ s.name_th }}</div>
            <div class="text-muted">{{ monthLabels(s.months) }}</div>
            <div v-if="s.description_th" class="text-muted mt-1">{{ s.description_th }}</div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!collapsed && guide.crops?.length">
      <div class="fw-bold small mb-1">พืชและฤดูปลูกที่เหมาะสม</div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 small">
          <thead>
            <tr>
              <th>พืช</th>
              <th>โต (เดือน)</th>
              <th>ฤดูที่เหมาะ</th>
              <th>เดือนปลูก</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in guide.crops" :key="c.id">
              <td>{{ c.name_th }}</td>
              <td>{{ c.growth_months }}</td>
              <td>{{ (c.ideal_seasons || []).join(', ') }}</td>
              <td>{{ monthLabels(c.ideal_plant_months) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
