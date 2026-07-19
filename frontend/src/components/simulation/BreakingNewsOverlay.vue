<script setup>
import { computed } from 'vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import { BREAKING_NEWS_SECONDS } from '@/constants/simulation'

const props = defineProps({
  active: { type: Boolean, default: false },
  event: { type: Object, default: null },
  remaining: { type: Number, default: BREAKING_NEWS_SECONDS },
})

const displayRemaining = computed(() => Math.max(0, Math.ceil(props.remaining)))
</script>

<template>
  <div v-if="active && event" class="breaking-news-overlay">
    <div class="breaking-news-card">
      <div class="breaking-news-banner">BREAKING NEWS</div>
      <div class="breaking-news-body">
        <CardSprite
          :index="event.sprite_index ?? 8"
          size="hand"
          :glow="true"
          :label="event.name_th"
        />
        <div class="breaking-news-text">
          <h3>{{ event.name_th }}</h3>
          <p class="mb-1">{{ event.name_en }}</p>
          <div class="breaking-news-timer">{{ displayRemaining }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.breaking-news-overlay {
  position: fixed;
  inset: 0;
  z-index: 1060;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.72);
  pointer-events: none;
}

.breaking-news-card {
  width: min(92vw, 420px);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 18px 48px rgba(0, 0, 0, 0.45);
  animation: breaking-pop 0.35s ease-out;
}

.breaking-news-banner {
  background: linear-gradient(90deg, #c62828, #e53935);
  color: #fff;
  text-align: center;
  font-weight: 900;
  letter-spacing: 0.12em;
  padding: 0.55rem 1rem;
}

.breaking-news-body {
  display: flex;
  gap: 1rem;
  align-items: center;
  background: #0c1c34;
  color: #fff;
  padding: 1rem 1.1rem 1.2rem;
}

.breaking-news-text h3 {
  margin: 0 0 0.25rem;
  font-size: 1.15rem;
  font-weight: 800;
}

.breaking-news-text p {
  color: rgba(255, 255, 255, 0.72);
  font-size: 0.85rem;
}

.breaking-news-timer {
  margin-top: 0.5rem;
  font-size: 2rem;
  font-weight: 900;
  color: #ffbe4d;
  font-variant-numeric: tabular-nums;
}

@keyframes breaking-pop {
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
