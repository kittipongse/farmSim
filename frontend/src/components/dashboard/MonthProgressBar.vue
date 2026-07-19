<script setup>
import { computed } from 'vue'
import { SIMULATION_MONTH_SECONDS } from '@/constants/simulation'

const ROUND_SECONDS = SIMULATION_MONTH_SECONDS
const MONTHS = [
  'ม.ค.',
  'ก.พ.',
  'มี.ค.',
  'เม.ย.',
  'พ.ค.',
  'มิ.ย.',
  'ก.ค.',
  'ส.ค.',
  'ก.ย.',
  'ต.ค.',
  'พ.ย.',
  'ธ.ค.',
]

const props = defineProps({
  active: { type: Boolean, default: false },
  month: { type: Number, default: 1 },
  remaining: { type: Number, default: 10 },
  finished: { type: Boolean, default: false },
  paused: { type: Boolean, default: false },
  monthsWithCards: { type: Object, default: () => ({}) },
  planning: { type: Boolean, default: false },
})

const currentMonth = computed(() =>
  Math.min(12, Math.max(1, Number(props.month) || 1))
)

const activeProgress = computed(() => {
  if (!props.active || props.finished) return 1
  const rem = Math.max(0, Number(props.remaining) || 0)
  return Math.min(1, Math.max(0, 1 - rem / ROUND_SECONDS))
})

const completedMonth = computed(() => {
  if (props.finished) return 12
  if (!props.active) return 0
  return Math.max(0, currentMonth.value - 1)
})

function hasCardsPlaced(monthNumber) {
  return Number(props.monthsWithCards?.[monthNumber] || 0) > 0
}

function badgeStyle(index) {
  const monthNumber = index + 1
  const state = stateForMonth(index)

  if (state === 'completed') {
    return { backgroundColor: 'rgba(255, 143, 0, 0.8)' }
  }

  if (state === 'placed') {
    return { backgroundColor: 'rgba(255, 143, 0, 0.45)' }
  }

  if (state === 'active') {
    const progress = activeProgress.value
    const r = Math.round(108 + (255 - 108) * progress)
    const g = Math.round(117 + (143 - 117) * progress)
    const b = Math.round(125 + (0 - 125) * progress)
    return { backgroundColor: `rgba(${r}, ${g}, ${b}, 0.55)` }
  }

  return { backgroundColor: 'rgba(108, 117, 125, 0.35)' }
}

function stateForMonth(index) {
  const monthNumber = index + 1
  if (monthNumber <= completedMonth.value) return 'completed'
  if (props.active && monthNumber === currentMonth.value) return 'active'
  if ((props.planning || props.active) && hasCardsPlaced(monthNumber)) return 'placed'
  return 'pending'
}
</script>

<template>
  <section class="month-progress-bar" aria-label="ป้ายเดือน มกราคม ถึง ธันวาคม">
    <div class="month-progress-steps">
      <div
        v-for="(monthName, index) in MONTHS"
        :key="monthName"
        class="month-progress-badge"
        :class="`is-${stateForMonth(index)}`"
        :style="badgeStyle(index)"
      >
        <span class="month-progress-label">{{ monthName }}</span>
        <span
          v-if="(planning || active) && hasCardsPlaced(index + 1)"
          class="month-progress-dot"
          :title="`${monthsWithCards[index + 1]} คนวางการ์ด`"
        />
      </div>
    </div>
  </section>
</template>

<style scoped>
.month-progress-bar {
  position: relative;
  z-index: 3;
  width: 100%;
  padding: 0;
  color: #fff;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.65);
}

.month-progress-steps {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 0.25rem;
}

.month-progress-badge {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0.25rem 0.1rem;
  border-radius: 8px;
  text-align: center;
  transition: background-color 0.35s linear;
}

.month-progress-badge.is-completed {
  box-shadow: 0 0 0 1px rgba(255, 180, 60, 0.9);
}

.month-progress-badge.is-active {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(255, 143, 0, 0.35);
}

.month-progress-label {
  font-size: clamp(0.58rem, 1.1vw, 0.74rem);
  font-weight: 900;
  line-height: 1.1;
  white-space: nowrap;
}

.month-progress-dot {
  width: 5px;
  height: 5px;
  margin-top: 2px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 0 4px rgba(255, 255, 255, 0.8);
}
</style>
