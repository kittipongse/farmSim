<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import PlayerAvatar from '@/components/PlayerAvatar.vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import { DECISION_CARDS, THAI_MONTHS, spriteIndexForCode, UI_SPRITES } from '@/constants/cardSprites'
import { completePresentation } from '@/services/presentationService'

const props = defineProps({
  active: { type: Boolean, default: false },
  presentation: { type: Object, default: null },
  roomCode: { type: String, default: '' },
})

const emit = defineEmits(['complete'])

const scrollTrackRef = ref(null)
const scrollContentRef = ref(null)
const scrollDuration = ref(42)
const animating = ref(false)
let completeTimer = null
let startedQueueId = null

const current = computed(() => props.presentation?.current || null)
const isWinner = computed(() => Boolean(current.value?.is_winner))
const queueWaiting = computed(() => props.presentation?.queue_waiting || 0)

const cardPlanMonths = computed(() => {
  const map = {}
  for (const item of current.value?.cards_by_month || []) {
    map[item.month] = item
  }
  return map
})

function monthSprite(month) {
  const placed = cardPlanMonths.value[month]
  if (placed) return spriteIndexForCode(placed.card_code)
  return UI_SPRITES.PLACE
}

function cardLabel(month) {
  const placed = cardPlanMonths.value[month]
  if (!placed) return '—'
  const card = DECISION_CARDS.find((c) => c.code === placed.card_code)
  return card?.nameTh || placed.card_code
}

function rankBadge(rank) {
  if (rank === 1) return '🥇 อันดับ 1'
  if (rank === 2) return '🥈 อันดับ 2'
  if (rank === 3) return '🥉 อันดับ 3'
  return `#${rank}`
}

async function startScrollAnimation() {
  if (!props.active || !current.value || animating.value) return
  if (startedQueueId === current.value.queue_id) return

  await nextTick()
  const track = scrollTrackRef.value
  const content = scrollContentRef.value
  if (!track || !content) return

  startedQueueId = current.value.queue_id
  animating.value = true

  const viewport = track.clientHeight
  const contentHeight = content.scrollHeight
  const travel = Math.max(viewport, contentHeight + viewport * 0.35)
  const seconds = Math.min(75, Math.max(32, Math.ceil(travel / 42)))
  scrollDuration.value = seconds

  content.style.setProperty('--scroll-distance', `${travel}px`)
  content.classList.remove('is-scrolling')
  void content.offsetWidth
  content.classList.add('is-scrolling')
  content.style.setProperty('--scroll-duration', `${seconds}s`)

  clearTimeout(completeTimer)
  completeTimer = setTimeout(() => {
    finishPresentation()
  }, seconds * 1000 + 600)
}

async function finishPresentation() {
  if (!props.roomCode) return
  try {
    await completePresentation(props.roomCode)
  } catch {
    // polling will sync
  }
  animating.value = false
  startedQueueId = null
  emit('complete')
}

watch(
  () => [props.active, current.value?.queue_id],
  ([active]) => {
    if (active && current.value) {
      startScrollAnimation()
    } else {
      clearTimeout(completeTimer)
      animating.value = false
      startedQueueId = null
    }
  },
  { immediate: true }
)

onUnmounted(() => {
  clearTimeout(completeTimer)
})
</script>

<template>
  <div v-if="active && current" class="result-presentation">
    <div class="result-presentation-backdrop" />

    <div v-if="isWinner" class="result-presentation-confetti" aria-hidden="true">
      <span v-for="n in 24" :key="n" class="confetti-piece" :style="{ '--i': n }" />
    </div>

    <div ref="scrollTrackRef" class="result-presentation-track">
      <div
        ref="scrollContentRef"
        class="result-presentation-scroll"
        :class="{ 'is-winner': isWinner }"
      >
        <section class="result-slide result-slide--hero" :class="{ 'is-winner': isWinner }">
          <div v-if="isWinner" class="winner-crown">👑</div>
          <div class="hero-avatar-wrap" :class="{ 'is-winner': isWinner }">
            <PlayerAvatar
              :name="current.name"
              :image="current.profile_image"
              :size="isWinner ? 140 : 110"
            />
          </div>
          <h2 class="hero-name">{{ current.name }}</h2>
          <p v-if="current.region_name_th" class="hero-region">{{ current.region_name_th }}</p>
          <div class="hero-rank" :class="{ 'is-winner': isWinner }">
            {{ rankBadge(current.rank) }} · {{ current.total_score }} คะแนน
          </div>
          <p v-if="isWinner" class="winner-tag">🏆 ผู้ชนะการแข่งขัน 🏆</p>
        </section>

        <section class="result-slide">
          <h3>แผนการ์ด 12 เดือน</h3>
          <p class="slide-sub">
            วางครบ {{ current.card_plan?.placed_count || 0 }} เดือน
            · ใช้ {{ current.card_plan?.unique_types || 0 }} ชนิดการ์ด
          </p>
          <div class="plan-grid">
            <div v-for="m in 12" :key="m" class="plan-grid-item">
              <span class="plan-grid-month">{{ THAI_MONTHS[m - 1] }}</span>
              <CardSprite
                :index="monthSprite(m)"
                size="slot"
                :glow="!!cardPlanMonths[m]"
                :label="cardLabel(m)"
              />
            </div>
          </div>
        </section>

        <section v-if="current.strengths?.length" class="result-slide result-slide--good">
          <h3>ข้อดีของการวางแผน</h3>
          <ul>
            <li v-for="(item, i) in current.strengths" :key="`s-${i}`">{{ item }}</li>
          </ul>
        </section>

        <section v-if="current.mistakes?.length" class="result-slide result-slide--warn">
          <h3>ข้อควรปรับปรุง</h3>
          <ul>
            <li v-for="(item, i) in current.mistakes" :key="`m-${i}`">{{ item }}</li>
          </ul>
        </section>

        <section class="result-slide result-slide--stats">
          <h3>สรุปทรัพยากร</h3>
          <div class="stats-row">
            <span>💰 {{ current.resources?.coins ?? '—' }}</span>
            <span>💧 {{ current.resources?.water ?? '—' }}</span>
            <span>📦 {{ current.resources?.stock_amount ?? '—' }}</span>
            <span>🌾 {{ current.total_yield ?? 0 }}</span>
          </div>
          <p v-if="queueWaiting > 1" class="queue-hint">
            ถัดไปในคิว {{ queueWaiting - 1 }} คน
          </p>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.result-presentation {
  position: fixed;
  inset: 0;
  z-index: 2000;
  overflow: hidden;
  color: #fff;
}

.result-presentation-backdrop {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(8, 18, 36, 0.96), rgba(4, 10, 22, 0.98));
}

.result-presentation-track {
  position: relative;
  z-index: 2;
  height: 100%;
  overflow: hidden;
}

.result-presentation-scroll {
  padding: 8vh 1.5rem 40vh;
  will-change: transform;
}

.result-presentation-scroll.is-scrolling {
  animation: scroll-up var(--scroll-duration, 42s) linear forwards;
}

.result-presentation-scroll.is-winner .result-slide--hero {
  border-color: rgba(255, 215, 0, 0.85);
  box-shadow: 0 0 40px rgba(255, 193, 7, 0.35);
}

@keyframes scroll-up {
  from { transform: translateY(100vh); }
  to { transform: translateY(calc(-1 * var(--scroll-distance, 120vh))); }
}

.result-slide {
  width: min(920px, 92vw);
  margin: 0 auto 2.5rem;
  padding: 1.5rem 1.75rem;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.14);
  backdrop-filter: blur(8px);
}

.result-slide--hero {
  text-align: center;
  padding-top: 2rem;
  padding-bottom: 2rem;
}

.result-slide--hero.is-winner {
  background: linear-gradient(160deg, rgba(255, 193, 7, 0.22), rgba(255, 87, 34, 0.12));
  animation: winner-glow 2.4s ease-in-out infinite;
}

@keyframes winner-glow {
  0%, 100% { box-shadow: 0 0 24px rgba(255, 193, 7, 0.35); }
  50% { box-shadow: 0 0 48px rgba(255, 215, 0, 0.65); }
}

.winner-crown {
  font-size: 3rem;
  animation: crown-bounce 1.2s ease-in-out infinite;
}

@keyframes crown-bounce {
  0%, 100% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-8px) scale(1.08); }
}

.hero-avatar-wrap {
  margin: 0.75rem auto 1rem;
  width: fit-content;
  border-radius: 50%;
  padding: 4px;
}

.hero-avatar-wrap.is-winner {
  padding: 6px;
  background: linear-gradient(135deg, #ffd54f, #ff8f00, #ffd54f);
  animation: ring-spin 4s linear infinite;
}

@keyframes ring-spin {
  to { filter: hue-rotate(360deg); }
}

.hero-name {
  font-size: clamp(1.8rem, 4vw, 2.6rem);
  font-weight: 900;
  margin-bottom: 0.25rem;
}

.hero-region {
  color: rgba(255, 255, 255, 0.72);
  margin-bottom: 0.75rem;
}

.hero-rank {
  display: inline-block;
  padding: 0.45rem 1rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  font-weight: 800;
  font-size: 1.1rem;
}

.hero-rank.is-winner {
  background: linear-gradient(90deg, #ffb300, #ff6f00);
  color: #2b1600;
  animation: winner-pulse 1.5s ease-in-out infinite;
}

@keyframes winner-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

.winner-tag {
  margin-top: 1rem;
  font-size: 1.25rem;
  font-weight: 900;
  color: #ffd54f;
  letter-spacing: 0.04em;
  animation: winner-shimmer 2s ease-in-out infinite;
}

@keyframes winner-shimmer {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.72; }
}

.result-slide h3 {
  font-size: clamp(1.25rem, 2.5vw, 1.6rem);
  font-weight: 900;
  margin-bottom: 0.75rem;
}

.slide-sub {
  color: rgba(255, 255, 255, 0.72);
  margin-bottom: 1rem;
}

.plan-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.65rem;
}

@media (max-width: 768px) {
  .plan-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

.plan-grid-item {
  text-align: center;
}

.plan-grid-month {
  display: block;
  font-size: 0.72rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
  color: rgba(255, 255, 255, 0.78);
}

.result-slide--good {
  border-color: rgba(76, 175, 80, 0.45);
  background: rgba(27, 94, 32, 0.22);
}

.result-slide--warn {
  border-color: rgba(255, 193, 7, 0.45);
  background: rgba(120, 86, 0, 0.22);
}

.result-slide ul {
  margin: 0;
  padding-left: 1.2rem;
  font-size: clamp(0.95rem, 2vw, 1.08rem);
  line-height: 1.55;
}

.result-slide li + li {
  margin-top: 0.45rem;
}

.stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  font-size: 1.15rem;
  font-weight: 700;
}

.queue-hint {
  margin-top: 1rem;
  margin-bottom: 0;
  color: rgba(255, 255, 255, 0.65);
  font-size: 0.95rem;
}

.result-presentation-confetti {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 1;
  overflow: hidden;
}

.confetti-piece {
  position: absolute;
  top: -12%;
  left: calc((var(--i) * 4.1%) + 1%);
  width: 10px;
  height: 16px;
  background: hsl(calc(var(--i) * 15deg), 85%, 58%);
  opacity: 0.85;
  animation: confetti-fall calc(2.8s + (var(--i) * 0.08s)) linear infinite;
  animation-delay: calc(var(--i) * -0.18s);
  transform: rotate(calc(var(--i) * 24deg));
}

@keyframes confetti-fall {
  0% { transform: translateY(-10vh) rotate(0deg); opacity: 0; }
  10% { opacity: 1; }
  100% { transform: translateY(110vh) rotate(720deg); opacity: 0.2; }
}
</style>
