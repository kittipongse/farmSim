<script setup>
import { computed } from 'vue'
import PlayerAvatar from '@/components/PlayerAvatar.vue'

const props = defineProps({
  players: { type: Array, default: () => [] },
  maxPlayers: { type: Number, default: 8 },
})

const slots = computed(() => {
  const list = Array.isArray(props.players) ? props.players : []
  const result = []
  for (let i = 0; i < props.maxPlayers; i += 1) {
    result.push({ index: i + 1, player: list[i] || null })
  }
  return result
})
</script>

<template>
  <div class="lobby-panel-center">
    <div class="lobby-panel-center-header">ผู้เล่นในห้อง</div>
    <div class="lobby-panel-center-body">
      <div class="lobby-player-grid">
        <div
          v-for="slot in slots"
          :key="slot.index"
          class="lobby-player-slot"
          :class="slot.player ? 'filled' : 'empty'"
        >
          <span class="lobby-slot-num">{{ slot.index }}</span>
          <template v-if="slot.player">
            <PlayerAvatar
              :name="slot.player.name"
              :image="slot.player.profile_image"
              :size="52"
            />
            <div class="lobby-slot-name">{{ slot.player.name }}</div>
            <div v-if="slot.player.is_ready" class="lobby-slot-ready">✓ พร้อมแล้ว</div>
            <div v-else class="lobby-slot-ready">กำลังตั้งค่า...</div>
          </template>
          <template v-else>
            <div class="lobby-slot-silhouette" />
            <div class="lobby-slot-wait">รอผู้เล่น...</div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
