<script setup>
import { computed } from 'vue'
import PlayerAvatar from '@/components/PlayerAvatar.vue'

const props = defineProps({
  players: { type: Array, default: () => [] },
  maxPlayers: { type: Number, default: 8 },
  roomPlayerCount: { type: Number, default: 0 },
})

const playerList = computed(() => (Array.isArray(props.players) ? props.players : []))

const displayCount = computed(() =>
  Math.max(playerList.value.length, props.roomPlayerCount || 0)
)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">ผู้เล่น</h5>
      <span class="badge badge-lobby fs-6">{{ displayCount }} / {{ maxPlayers }}</span>
    </div>
    <div class="row g-2">
      <div v-for="p in playerList" :key="p.id" class="col-6 col-md-3">
        <div class="card card-farm h-100">
          <div class="card-body text-center p-2">
            <PlayerAvatar
              :name="p.name"
              :image="p.profile_image"
              :size="56"
              class="mb-2"
            />
            <div class="fw-semibold text-truncate">{{ p.name }}</div>
            <small class="text-muted d-block text-truncate">
              {{ p.region_name_th || 'ยังไม่เลือกภูมิภาค' }}
            </small>
            <span v-if="p.is_ready" class="badge bg-success mt-1">พร้อม</span>
          </div>
        </div>
      </div>
      <div
        v-for="n in Math.max(0, maxPlayers - displayCount)"
        :key="'empty-' + n"
        class="col-6 col-md-3"
      >
        <div class="card card-farm h-100 border-dashed opacity-50">
          <div class="card-body text-center p-2 d-flex align-items-center justify-content-center" style="min-height: 120px">
            <span class="text-muted">รอผู้เล่น...</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.border-dashed {
  border: 2px dashed rgba(255, 255, 255, 0.45) !important;
  background: rgba(0, 0, 0, 0.15);
}
</style>
