<script setup>
import { ref, computed, onMounted } from 'vue'
import PlayerAvatar from '@/components/PlayerAvatar.vue'
import { getHallOfFame } from '@/services/hallOfFameService'

const entries = ref([])
const loading = ref(true)

const scrollItems = computed(() => {
  if (!entries.value.length) return []
  // ซ้ำรายการให้เลื่อนต่อเนื่องลื่น
  return [...entries.value, ...entries.value]
})

onMounted(async () => {
  try {
    entries.value = (await getHallOfFame(5)) || []
  } catch {
    entries.value = []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="!loading && entries.length" class="hof-wrap" aria-label="คะแนน Top 5">
    <div class="hof-label">
      <span class="hof-label-text">สถิติ Top 5</span>
    </div>
    <div class="hof-track">
      <div class="hof-marquee" :style="{ animationDuration: `${Math.max(18, entries.length * 6)}s` }">
        <div
          v-for="(item, idx) in scrollItems"
          :key="`${item.rank}-${idx}`"
          class="hof-item"
        >
          <span class="hof-rank" :class="'hof-rank-' + item.rank">{{ item.rank }}</span>
          <PlayerAvatar
            :name="item.player_name"
            :image="item.profile_image"
            :size="40"
          />
          <div class="hof-meta">
            <span class="hof-name">{{ item.player_name }}</span>
            <span class="hof-score">{{ item.total_score.toLocaleString('th-TH') }} คะแนน</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.hof-wrap {
  width: min(920px, 100%);
  margin: 0 auto 1.75rem;
  text-align: left;
}

.hof-label {
  display: flex;
  justify-content: center;
  margin-bottom: 0.55rem;
}

.hof-label-text {
  font-size: 0.85rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.92);
  background: rgba(45, 106, 79, 0.85);
  padding: 0.28rem 0.9rem;
  border-radius: 999px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.hof-track {
  overflow: hidden;
  border-radius: 14px;
  background: linear-gradient(
    90deg,
    rgba(27, 67, 50, 0.72),
    rgba(45, 106, 79, 0.55),
    rgba(27, 67, 50, 0.72)
  );
  border: 1px solid rgba(255, 255, 255, 0.18);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.14);
  mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
}

.hof-marquee {
  display: flex;
  width: max-content;
  gap: 1.25rem;
  padding: 0.7rem 1rem;
  animation: hof-scroll linear infinite;
  will-change: transform;
}

.hof-track:hover .hof-marquee {
  animation-play-state: paused;
}

.hof-item {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-shrink: 0;
  min-width: 200px;
  padding: 0.35rem 0.75rem 0.35rem 0.45rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.16);
}

.hof-rank {
  flex-shrink: 0;
  width: 1.55rem;
  height: 1.55rem;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: 800;
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
}

.hof-rank-1 {
  background: #c9a227;
  color: #1b4332;
}

.hof-rank-2 {
  background: #9aa0a6;
  color: #1b4332;
}

.hof-rank-3 {
  background: #b87333;
  color: #fff;
}

.hof-meta {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
  min-width: 0;
}

.hof-name {
  font-size: 0.95rem;
  font-weight: 800;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 140px;
}

.hof-score {
  font-size: 0.78rem;
  font-weight: 700;
  color: #d8f3dc;
  white-space: nowrap;
}

@keyframes hof-scroll {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-50%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .hof-marquee {
    animation: none;
    flex-wrap: wrap;
    width: 100%;
    justify-content: center;
  }
}
</style>
