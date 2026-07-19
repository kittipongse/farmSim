<script setup>
import { computed } from 'vue'

const props = defineProps({
  active: { type: Boolean, default: false },
  quiz: { type: Object, default: null },
})

const winners = computed(() => props.quiz?.winners || [])
const remainingSeconds = computed(() => Math.max(0, Number(props.quiz?.remaining_seconds) || 0))
</script>

<template>
  <div v-if="active && quiz" class="bonus-reveal-overlay">
    <div class="bonus-reveal-card">
      <div class="bonus-reveal-title">ผู้ตอบถูก!</div>
      <p class="bonus-reveal-sub">โบนัสทายปัญหา · {{ quiz.question }}</p>

      <ol v-if="winners.length" class="bonus-reveal-list">
        <li v-for="(w, index) in winners" :key="w.player_id">
          <span class="bonus-reveal-rank">{{ w.correct_order || index + 1 }}</span>
          <span class="bonus-reveal-name">{{ w.player_name }}</span>
          <span class="bonus-reveal-coins">+{{ w.coins_delta }}</span>
        </li>
      </ol>
      <p v-else class="bonus-reveal-empty">ไม่มีผู้ตอบถูกในรอบนี้</p>

      <div v-if="remainingSeconds > 0" class="bonus-reveal-timer">
        ปิดใน {{ remainingSeconds }} วินาที
      </div>
    </div>
  </div>
</template>

<style scoped>
.bonus-reveal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1080;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.6);
  pointer-events: none;
  animation: reveal-fade 0.3s ease-out;
}

.bonus-reveal-card {
  width: min(92vw, 560px);
  padding: 1.5rem 1.75rem 1.25rem;
  border-radius: 18px;
  background: linear-gradient(160deg, #fff8e1 0%, #ffe082 55%, #ffca28 100%);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
  text-align: center;
  color: #3e2723;
  animation: reveal-pop 0.4s ease-out;
}

.bonus-reveal-title {
  font-size: clamp(1.5rem, 4vw, 2.1rem);
  font-weight: 900;
  color: #e65100;
  margin-bottom: 0.35rem;
}

.bonus-reveal-sub {
  font-size: clamp(0.8rem, 1.8vw, 0.95rem);
  color: #5d4037;
  margin-bottom: 1rem;
  line-height: 1.35;
}

.bonus-reveal-list {
  list-style: none;
  margin: 0 0 1rem;
  padding: 0;
  text-align: left;
}

.bonus-reveal-list li {
  display: grid;
  grid-template-columns: 2rem 1fr auto;
  gap: 0.65rem;
  align-items: center;
  padding: 0.55rem 0.75rem;
  margin-bottom: 0.4rem;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.7);
  font-size: clamp(0.95rem, 2.2vw, 1.15rem);
  font-weight: 700;
}

.bonus-reveal-list li:first-child {
  background: rgba(255, 215, 0, 0.55);
  box-shadow: 0 0 0 2px rgba(255, 143, 0, 0.5);
}

.bonus-reveal-rank {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #ff8f00;
  color: #fff;
  font-size: 0.9rem;
}

.bonus-reveal-coins {
  color: #2e7d32;
  font-weight: 900;
}

.bonus-reveal-empty {
  font-size: 1.05rem;
  color: #6d4c41;
  margin: 1rem 0;
}

.bonus-reveal-timer {
  font-size: 0.9rem;
  font-weight: 700;
  color: #bf360c;
}

@keyframes reveal-fade {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes reveal-pop {
  from {
    opacity: 0;
    transform: scale(0.92) translateY(12px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
</style>
