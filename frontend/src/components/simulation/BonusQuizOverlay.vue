<script setup>
import { computed } from 'vue'
import bonusFrameUrl from '@/assets/images/example3.png'

const props = defineProps({
  active: { type: Boolean, default: false },
  quiz: { type: Object, default: null },
})

const progressLabel = computed(() => {
  if (!props.quiz) return ''
  return `${props.quiz.answers_count || 0} / ${props.quiz.total_players || 0} คนตอบแล้ว`
})

const remainingSeconds = computed(() => Math.max(0, Number(props.quiz?.remaining_seconds) || 0))
const idleCloseActive = computed(() => Boolean(props.quiz?.idle_close_active))
const idleCloseRemaining = computed(() =>
  Math.max(0, Number(props.quiz?.idle_close_remaining_seconds) || 0)
)
const displayTimerSeconds = computed(() => {
  if (idleCloseActive.value && idleCloseRemaining.value > 0) {
    return idleCloseRemaining.value
  }
  return remainingSeconds.value
})

const scoring = computed(() => props.quiz?.scoring || {
  first_correct: 10,
  later_correct: 2,
  wrong: -10,
})

const recentAnswers = computed(() => {
  const list = props.quiz?.answers || []
  return list.slice(-5).reverse()
})
</script>

<template>
  <div v-if="active && quiz" class="bonus-quiz-overlay">
    <div class="bonus-quiz-frame" :style="{ backgroundImage: `url(${bonusFrameUrl})` }">
      <div class="bonus-quiz-content">
        <div class="bonus-quiz-title">โบนัสทายปัญหา!</div>
        <p class="bonus-quiz-question">{{ quiz.question }}</p>
        <div class="bonus-quiz-score-row">
          <span class="is-first">ถูกคนแรก +{{ scoring.first_correct }}</span>
          <span class="is-later">ถูกถัดไป +{{ scoring.later_correct }}</span>
          <span class="is-wrong">ผิด {{ scoring.wrong }}</span>
        </div>
        <div class="bonus-quiz-choices">A. ใช่ · B. ไม่ใช่</div>
        <div class="bonus-quiz-progress">{{ progressLabel }}</div>
        <div v-if="displayTimerSeconds > 0" class="bonus-quiz-timer">
          <template v-if="idleCloseActive">
            ไม่มีใครตอบ · ปิดอัตโนมัติใน {{ displayTimerSeconds }} วินาที
          </template>
          <template v-else>
            ปิดอัตโนมัติใน {{ displayTimerSeconds }} วินาที
          </template>
        </div>
        <ul v-if="recentAnswers.length" class="bonus-quiz-answers list-unstyled mb-0">
          <li v-for="item in recentAnswers" :key="`${item.player_id}-${item.answer}`">
            <span class="bonus-quiz-name">{{ item.player_name }}</span>
            <span :class="item.is_correct ? 'text-success' : 'text-danger'">
              {{ item.is_correct ? '✓' : '✗' }}
              {{ item.coins_delta > 0 ? `+${item.coins_delta}` : item.coins_delta }}
            </span>
          </li>
        </ul>
        <p class="bonus-quiz-wait mb-0">ตอบบนมือถือ · เร็วได้คะแนนมากกว่า</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bonus-quiz-overlay {
  position: fixed;
  inset: 0;
  z-index: 1070;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.55);
  pointer-events: none;
}

.bonus-quiz-frame {
  width: min(92vw, 720px);
  aspect-ratio: 16 / 9;
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: bonus-pop 0.35s ease-out;
}

.bonus-quiz-content {
  width: 58%;
  max-height: 42%;
  margin-top: 4%;
  text-align: center;
  color: #3b2a16;
  overflow: auto;
}

.bonus-quiz-title {
  font-size: clamp(1rem, 2.4vw, 1.35rem);
  font-weight: 900;
  color: #8a5a12;
  margin-bottom: 0.35rem;
}

.bonus-quiz-question {
  font-size: clamp(1.05rem, 2.8vw, 1.55rem);
  font-weight: 800;
  line-height: 1.35;
  margin-bottom: 0.45rem;
}

.bonus-quiz-rules {
  font-size: clamp(0.72rem, 1.6vw, 0.9rem);
  color: #5c4a32;
  margin-bottom: 0.35rem;
}

.bonus-quiz-score-row {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-bottom: 0.35rem;
  font-size: clamp(0.7rem, 1.5vw, 0.85rem);
  font-weight: 700;
}

.bonus-quiz-score-row .is-first { color: #2e7d32; }
.bonus-quiz-score-row .is-later { color: #0277bd; }
.bonus-quiz-score-row .is-wrong { color: #c62828; }

.bonus-quiz-choices {
  font-size: clamp(0.75rem, 1.6vw, 0.9rem);
  font-weight: 700;
  color: #5c4a32;
  margin-bottom: 0.35rem;
}

.bonus-quiz-progress {
  font-size: clamp(0.85rem, 2vw, 1rem);
  font-weight: 700;
  color: #2d6a4f;
  margin-bottom: 0.35rem;
}

.bonus-quiz-timer {
  font-size: clamp(0.78rem, 1.8vw, 0.95rem);
  font-weight: 700;
  color: #b5651d;
  margin-bottom: 0.35rem;
}

.bonus-quiz-answers {
  font-size: clamp(0.72rem, 1.5vw, 0.85rem);
  margin-bottom: 0.35rem;
}

.bonus-quiz-name {
  margin-right: 0.35rem;
  font-weight: 600;
}

.bonus-quiz-wait {
  font-size: clamp(0.72rem, 1.6vw, 0.88rem);
  color: #6b5a42;
}

@keyframes bonus-pop {
  from {
    opacity: 0;
    transform: scale(0.94) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
</style>
