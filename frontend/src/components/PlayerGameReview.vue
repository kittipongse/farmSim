<script setup>
import { THAI_MONTHS } from '@/constants/cardSprites'

defineProps({
  review: {
    type: Object,
    required: true,
  },
})

const STATUS_LABELS = {
  harvested: 'เก็บเกี่ยวแล้ว',
  growing: 'ยังเติบโต/ไม่ได้เก็บ',
  planned: 'ยังไม่ได้เริ่ม/ไม่ได้เก็บ',
  sold: 'ขายแล้ว',
}

const MISMATCH_LABELS = {
  wrong_region: 'ไม่เหมาะภูมิภาค',
  unknown_crop: 'ไม่รู้จักในระบบ',
}

function monthLabel(m) {
  return THAI_MONTHS[(m || 1) - 1] || m
}
</script>

<template>
  <div v-if="review?.player" class="player-review text-start">
    <div class="card card-farm p-3 mb-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h5 class="mb-1">{{ review.player.name }}</h5>
          <p class="text-muted small mb-0">
            ภูมิภาค {{ review.player.region_name_th || '—' }}
            · ความสามารถ {{ review.player.agricultural_capability }}%
          </p>
        </div>
        <div v-if="review.my_rank" class="text-end">
          <div class="display-6 fw-bold text-success mb-0">#{{ review.my_rank.rank }}</div>
          <div class="small text-muted">{{ review.my_rank.total_score }} คะแนน</div>
        </div>
      </div>
    </div>

    <div v-if="review.player.score" class="card card-farm p-3 mb-3">
      <h6 class="mb-2">คะแนนรายมิติ</h6>
      <div class="row g-2 small">
        <div class="col-6">ผลผลิต: <strong>{{ review.player.score.production_score ?? '—' }}</strong></div>
        <div class="col-6">ทรัพยากร: <strong>{{ review.player.score.resource_score ?? '—' }}</strong></div>
        <div class="col-6">ความสามารถ: <strong>{{ review.player.score.capability_score ?? '—' }}</strong></div>
        <div class="col-6">รวม: <strong>{{ review.player.score.total_score }}</strong></div>
      </div>
    </div>

    <div v-if="review.player.resources" class="card card-farm p-3 mb-3">
      <h6 class="mb-2">ทรัพยากรปลายเกม</h6>
      <div class="row g-2 small">
        <div class="col-4">💰 {{ review.player.resources.coins }}</div>
        <div class="col-4">💧 {{ review.player.resources.water }}</div>
        <div class="col-4">📦 {{ review.player.resources.stock_amount }}</div>
        <div class="col-4">🛠 Tech {{ review.player.resources.tech_level }}</div>
        <div class="col-4">🌱 ดิน {{ review.player.resources.soil_quality ?? '—' }}</div>
        <div class="col-4">🌾 ผลผลิต {{ review.player.total_yield }}</div>
      </div>
    </div>

    <div v-if="review.player.strengths?.length" class="card card-farm p-3 mb-3 border-success">
      <h6 class="text-success mb-2">ข้อดีของการวางแผน</h6>
      <ul class="mb-0 ps-3 small">
        <li v-for="(s, i) in review.player.strengths" :key="`s-${i}`" class="mb-1">{{ s }}</li>
      </ul>
    </div>

    <div v-if="review.player.mistakes?.length" class="card card-farm p-3 mb-3 border-warning">
      <h6 class="text-warning-emphasis mb-2">ข้อผิดพลาด / จุดที่ควรแก้</h6>
      <ul class="mb-0 ps-3 small">
        <li v-for="(m, i) in review.player.mistakes" :key="`m-${i}`" class="mb-1">{{ m }}</li>
      </ul>
    </div>

    <div v-if="review.player.advice?.length" class="card card-farm p-3 mb-3 border-info">
      <h6 class="text-info-emphasis mb-2">คำแนะนำรอบถัดไป</h6>
      <ul class="mb-0 ps-3 small">
        <li v-for="(a, i) in review.player.advice" :key="`a-${i}`" class="mb-1">{{ a }}</li>
      </ul>
    </div>

    <div v-if="review.player.crop_history?.length" class="card card-farm p-3 mb-3">
      <h6 class="mb-2">ประวัติการปลูก</h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 small">
          <thead>
            <tr>
              <th>พืช</th>
              <th>ปลูก</th>
              <th>เก็บ</th>
              <th>ผลผลิต</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(crop, i) in review.player.crop_history"
              :key="i"
              :class="{ 'table-warning': !crop.region_match || !crop.season_match || crop.status !== 'harvested' }"
            >
              <td>
                {{ crop.input_crop_name || crop.crop_name }}
                <div class="mt-1">
                  <span v-if="!crop.region_match" class="badge bg-warning text-dark me-1">
                    {{ MISMATCH_LABELS[crop.mismatch_reason] || 'ไม่เหมาะภูมิภาค' }}
                  </span>
                  <span v-if="!crop.season_match" class="badge bg-info text-dark me-1">ไม่ตรงฤดู</span>
                </div>
              </td>
              <td>{{ monthLabel(crop.plant_month) }}</td>
              <td>{{ crop.harvest_month > 12 ? 'เกินปี' : monthLabel(crop.harvest_month) }}</td>
              <td>
                {{ crop.yield_amount }}
                <div class="text-muted">{{ STATUS_LABELS[crop.status] || crop.status }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="review.ranking?.length" class="card card-farm p-3 mb-3">
      <h6 class="mb-2">อันดับทั้งห้อง</h6>
      <ol class="mb-0 small ps-3">
        <li
          v-for="r in review.ranking"
          :key="r.player_id"
          class="mb-1"
          :class="{ 'fw-bold text-success': r.player_id === review.player.player_id }"
        >
          {{ r.player_name }} — {{ r.total_score }} คะแนน
        </li>
      </ol>
    </div>
  </div>
</template>
