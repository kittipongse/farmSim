<script setup>
defineProps({
  summary: {
    type: Object,
    required: true,
  },
  compact: {
    type: Boolean,
    default: false,
  },
})

const MISMATCH_LABELS = {
  wrong_region: 'ไม่ตรงภูมิภาค',
  unknown_crop: 'ไม่รู้จักในประเทศ',
}
</script>

<template>
  <div v-for="player in summary.players" :key="player.player_id" class="mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
      <h5 class="mb-0">
        {{ player.name }}
        <span v-if="player.region_name_th" class="text-muted fw-normal small">
          ({{ player.region_name_th }})
        </span>
      </h5>
      <span class="badge bg-secondary">
        ความสามารถ {{ player.agricultural_capability }}%
      </span>
    </div>

    <div v-if="player.issues?.length" class="alert alert-warning py-2 small mb-2">
      <div class="fw-bold mb-1">ปัญหาที่พบ</div>
      <ul class="mb-0 ps-3">
        <li v-for="(issue, i) in player.issues" :key="i">{{ issue }}</li>
      </ul>
    </div>

    <div v-if="player.outcomes?.length" class="alert alert-success py-2 small mb-2">
      <div class="fw-bold mb-1">ผลการดำเนินการ</div>
      <ul class="mb-0 ps-3">
        <li v-for="(line, i) in player.outcomes" :key="i">{{ line }}</li>
      </ul>
    </div>

    <div v-if="!compact && player.crop_history?.length" class="table-responsive">
      <table class="table table-sm table-bordered mb-0 small">
        <thead>
          <tr>
            <th>ปี</th>
            <th>พืชที่ปลูก</th>
            <th>เดือนปลูก</th>
            <th>ผลผลิต</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(crop, i) in player.crop_history"
            :key="i"
            :class="{ 'table-warning': !crop.region_match || !crop.season_match }"
          >
            <td>{{ crop.year }}</td>
            <td>
              {{ crop.input_crop_name || crop.crop_name }}
              <span
                v-if="!crop.region_match"
                class="badge bg-warning text-dark ms-1"
              >
                {{ MISMATCH_LABELS[crop.mismatch_reason] || 'ไม่เหมาะภูมิภาค' }}
              </span>
              <span
                v-if="!crop.season_match"
                class="badge bg-info text-dark ms-1"
              >
                ไม่ตรงฤดู
              </span>
            </td>
            <td>{{ crop.plant_month }}</td>
            <td>{{ crop.yield_amount }}</td>
            <td>{{ crop.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
