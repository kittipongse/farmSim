<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import CardActionAnimation from '@/components/sprites/CardActionAnimation.vue'
import MapWeatherEffects from '@/components/dashboard/MapWeatherEffects.vue'
import PlayerAvatar from '@/components/PlayerAvatar.vue'
import thailandMap from '@/assets/images/thailand.png'
import { DECISION_CARDS, UI_SPRITES, spriteIndexForCode } from '@/constants/cardSprites'
import { hasExactActionSprite } from '@/constants/actionSprites'
import { SIMULATION_MONTH_SECONDS } from '@/constants/simulation'
import {
  THAILAND_REGION_POSITIONS,
  clampMapPosition,
  regionKeyForPlayer,
  spreadMarkerOffset,
} from '@/constants/thailandMapRegions'

const ROUND_SECONDS = SIMULATION_MONTH_SECONDS

const props = defineProps({
  room: { type: Object, default: null },
  players: { type: Array, default: () => [] },
  ranking: { type: Array, default: () => [] },
  currentCards: { type: Object, default: () => ({}) },
  remaining: { type: Number, default: 10 },
  active: { type: Boolean, default: false },
  paused: { type: Boolean, default: false },
  maxPlayers: { type: Number, default: 8 },
  fullscreen: { type: Boolean, default: false },
  weatherEvent: { type: Object, default: null },
  breakingNews: { type: Boolean, default: false },
})

const emit = defineEmits(['weather-thunder'])

const localRemaining = ref(ROUND_SECONDS)
let timer = null

const playerList = computed(() => (Array.isArray(props.players) ? props.players : []))
const playerCount = computed(() =>
  Math.max(playerList.value.length, props.room?.player_count || 0)
)
const year = computed(() => props.room?.current_year || 1)
const month = computed(() => props.room?.current_month || 1)

const timerLabel = computed(() => {
  const seconds = Math.max(0, localRemaining.value)
  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60
  return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`
})

function cardMeta(code) {
  return DECISION_CARDS.find((card) => card.code === code)
}

function currentCardFor(playerId) {
  return props.currentCards?.[playerId] || null
}

const mapPlayers = computed(() => {
  const keyed = playerList.value.map((player, index) => ({
    player,
    regionKey: regionKeyForPlayer(player, index),
  }))

  const totalsByRegion = {}
  for (const item of keyed) {
    totalsByRegion[item.regionKey] = (totalsByRegion[item.regionKey] || 0) + 1
  }

  const indexByRegion = {}
  return keyed.map(({ player, regionKey }) => {
    const region = THAILAND_REGION_POSITIONS[regionKey] || THAILAND_REGION_POSITIONS.central
    const indexInRegion = indexByRegion[regionKey] || 0
    indexByRegion[regionKey] = indexInRegion + 1
    const totalInRegion = totalsByRegion[regionKey] || 1
    const offset = spreadMarkerOffset(indexInRegion, totalInRegion)
    const card = currentCardFor(player.id)
    const pos = clampMapPosition(region.x + offset.x, region.y + offset.y)

    return {
      player,
      regionKey,
      regionLabel: region.label,
      card,
      x: pos.x,
      y: pos.y,
    }
  })
})

const missionCards = computed(() =>
  playerList.value.slice(0, props.maxPlayers).map((player) => {
    const card = currentCardFor(player.id)
    return {
      player,
      card,
      meta: cardMeta(card?.card_code),
    }
  })
)

function resetTimer() {
  const serverRemaining = Number(props.remaining)
  localRemaining.value = serverRemaining > 0 && serverRemaining <= ROUND_SECONDS
    ? serverRemaining
    : ROUND_SECONDS
}

function stopTimer() {
  if (!timer) return
  clearInterval(timer)
  timer = null
}

function startTimer() {
  stopTimer()
  if (!props.active || props.paused) return
  timer = setInterval(() => {
    localRemaining.value = Math.max(0, localRemaining.value - 1)
  }, 1000)
}

watch(
  () => [props.active, year.value, month.value, props.paused],
  () => {
    resetTimer()
    startTimer()
  },
  { immediate: true }
)

watch(
  () => props.paused,
  (paused) => {
    if (paused) stopTimer()
    else startTimer()
  }
)

watch(
  () => props.remaining,
  () => {
    const serverRemaining = Number(props.remaining)
    if (serverRemaining >= 0 && serverRemaining <= ROUND_SECONDS) {
      localRemaining.value = serverRemaining
    }
  }
)

onUnmounted(stopTimer)
</script>

<template>
  <section class="thailand-game-map" :class="{ 'thailand-game-map--fullscreen': fullscreen }">
    <div class="thailand-map-stage" :style="{ backgroundImage: `url('${thailandMap}')` }">
      <MapWeatherEffects
        :active="active"
        :event="weatherEvent"
        :breaking-news="breakingNews"
        @weather-thunder="emit('weather-thunder')"
      />
      <aside class="thailand-player-panel">
        <div class="thailand-player-panel-title">
          <span>ผู้เล่น</span>
          <strong>{{ playerCount }} / {{ maxPlayers }}</strong>
        </div>
        <ol class="thailand-player-list">
          <li v-for="(player, index) in playerList" :key="player.id">
            <span class="thailand-player-rank">{{ index + 1 }}</span>
            <PlayerAvatar
              :name="player.name"
              :image="player.profile_image"
              :size="28"
            />
            <span class="thailand-player-name">{{ player.name }}</span>
          </li>
        </ol>
      </aside>

      <div class="thailand-region-label thailand-region-label--north">ภาคเหนือ</div>
      <div class="thailand-region-label thailand-region-label--central">ภาคกลาง</div>
      <div class="thailand-region-label thailand-region-label--isan">ภาคอีสาน</div>
      <div class="thailand-region-label thailand-region-label--south">ภาคใต้</div>

      <div
        v-for="item in mapPlayers"
        :key="item.player.id"
        class="thailand-player-marker"
        :style="{ left: `${item.x}%`, top: `${item.y}%` }"
      >
        <div class="thailand-player-action">
          <CardActionAnimation
            :code="item.card?.card_code || 'IDLE'"
            :playing="active && !!item.card?.card_code && hasExactActionSprite(item.card.card_code)"
            :size="82"
            :fps="7"
          />
          <span v-if="item.card?.card_code" class="thailand-action-badge">
            <CardSprite
              :index="spriteIndexForCode(item.card.card_code)"
              size="hand"
              :label="item.card.card_code"
            />
          </span>
        </div>
        <span class="thailand-pin" />
        <span class="thailand-marker-name">{{ item.player.name }}</span>
      </div>

      <div class="thailand-round-timer">
        <div class="thailand-timer-ring">
          <span>{{ timerLabel }}</span>
        </div>
        <small>นับถอยหลัง</small>
      </div>

      <div class="thailand-mission-strip">
        <div
          v-for="(mission, index) in missionCards"
          :key="mission.player.id"
          class="thailand-mission-card"
          :class="{ 'is-empty': !mission.card }"
        >
          <span class="thailand-mission-number">{{ index + 1 }}</span>
          <CardSprite
            :index="mission.card ? spriteIndexForCode(mission.card.card_code) : UI_SPRITES.PLACE"
            size="hand"
            :dim="!mission.card"
            :label="mission.meta?.nameTh || 'ยังไม่มีภารกิจเดือนนี้'"
          />
          <div class="thailand-mission-copy">
            <strong>{{ mission.player.name }}</strong>
            <span>{{ mission.meta?.nameTh || 'ไม่มีภารกิจเดือนนี้' }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.thailand-game-map {
  width: 100%;
  color: #fff;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
}

.thailand-game-map--fullscreen {
  width: 100vw;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
}

.thailand-map-stage {
  position: relative;
  width: 100%;
  min-height: min(74vh, 720px);
  aspect-ratio: 819 / 546;
  overflow: hidden;
  border-radius: 22px;
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
  box-shadow: 0 24px 50px rgba(0, 0, 0, 0.34);
}

.thailand-game-map--fullscreen .thailand-map-stage {
  width: 100%;
  height: 100%;
  min-height: 0;
  aspect-ratio: auto;
  border-radius: 0;
  box-shadow: none;
}

.thailand-map-stage::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    linear-gradient(90deg, rgba(0, 0, 0, 0.32), transparent 22%, transparent 78%, rgba(0, 0, 0, 0.18)),
    linear-gradient(0deg, rgba(0, 24, 48, 0.2), transparent 36%);
}

.thailand-player-panel,
.thailand-player-marker,
.thailand-round-timer,
.thailand-mission-strip,
.thailand-region-label {
  position: absolute;
  z-index: 2;
}

.thailand-player-panel {
  top: 7.5rem;
  left: 1rem;
  width: 168px;
  padding: 0.55rem;
  background: rgba(255, 255, 255, 0.88);
  color: #153047;
  text-shadow: none;
  border-radius: 12px;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
}

.thailand-player-panel-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 900;
  font-size: 0.84rem;
  margin-bottom: 0.35rem;
}

.thailand-player-panel-title strong {
  color: #0b8f55;
}

.thailand-player-list {
  display: flex;
  flex-direction: column;
  gap: 0.22rem;
  padding: 0;
  margin: 0;
  list-style: none;
}

.thailand-player-list li {
  display: grid;
  grid-template-columns: 18px 28px 1fr;
  align-items: center;
  gap: 0.3rem;
  min-width: 0;
}

.thailand-player-rank {
  display: grid;
  place-items: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #eef6ff;
  font-size: 0.68rem;
  font-weight: 900;
}

.thailand-player-name {
  overflow: hidden;
  font-size: 0.7rem;
  font-weight: 800;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.thailand-region-label {
  padding: 0.18rem 0.45rem;
  border-radius: 999px;
  background: rgba(9, 33, 49, 0.48);
  border: 1px solid rgba(255, 255, 255, 0.25);
  font-size: 0.72rem;
  font-weight: 900;
  letter-spacing: 0.01em;
}

.thailand-region-label--north {
  top: 11%;
  left: 44%;
}

.thailand-region-label--central {
  top: 41%;
  left: 40%;
}

.thailand-region-label--isan {
  top: 33%;
  right: 14%;
}

.thailand-region-label--south {
  bottom: 26%;
  left: 44%;
}

.thailand-player-marker {
  transform: translate(-50%, -50%);
  display: flex;
  flex-direction: column;
  align-items: center;
}

.thailand-player-action {
  position: relative;
  display: grid;
  place-items: center;
  width: 96px;
  height: 78px;
  border: 3px solid rgba(255, 255, 255, 0.92);
  border-radius: 50%;
  background: radial-gradient(circle, rgba(254, 240, 184, 0.68), rgba(42, 132, 79, 0.52));
  box-shadow: 0 8px 18px rgba(0, 0, 0, 0.26);
}

.thailand-action-badge {
  position: absolute;
  top: -10px;
  right: -9px;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  padding: 3px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.95);
  box-shadow: 0 5px 12px rgba(0, 0, 0, 0.24);
}

.thailand-action-badge .card-sprite {
  width: 24px;
}

.thailand-pin {
  width: 14px;
  height: 14px;
  margin-top: -2px;
  border: 3px solid #fff;
  border-radius: 50% 50% 50% 0;
  background: #f44336;
  transform: rotate(-45deg);
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.32);
}

.thailand-marker-name {
  max-width: 120px;
  margin-top: 0.18rem;
  padding: 0.12rem 0.42rem;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(7, 24, 42, 0.7);
  font-size: 0.72rem;
  font-weight: 900;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.thailand-round-timer {
  right: 1.25rem;
  bottom: 1.25rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.1rem;
}

.thailand-timer-ring {
  display: grid;
  place-items: center;
  width: 92px;
  height: 92px;
  border: 9px solid #ffc02e;
  border-right-color: #1fb6ff;
  border-bottom-color: #1fb6ff;
  border-radius: 50%;
  background: rgba(6, 27, 49, 0.86);
  box-shadow: 0 8px 22px rgba(0, 0, 0, 0.32);
}

.thailand-timer-ring span {
  font-size: 1.35rem;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
}

.thailand-round-timer small {
  font-size: 0.72rem;
  font-weight: 900;
}

.thailand-mission-strip {
  right: 7.4rem;
  bottom: 1.15rem;
  left: 1.25rem;
  display: grid;
  grid-template-columns: repeat(8, minmax(72px, 1fr));
  gap: 0.45rem;
}

.thailand-mission-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.34rem;
  min-width: 0;
  padding: 0.35rem 0.4rem;
  border: 2px solid rgba(255, 255, 255, 0.75);
  border-radius: 11px;
  background: rgba(255, 255, 255, 0.86);
  color: #123047;
  text-shadow: none;
}

.thailand-mission-card.is-empty {
  opacity: 0.78;
}

.thailand-mission-card .card-sprite {
  flex: 0 0 34px;
  width: 34px;
}

.thailand-mission-number {
  position: absolute;
  top: -8px;
  left: -6px;
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #169bd5;
  color: #fff;
  font-size: 0.7rem;
  font-weight: 900;
}

.thailand-mission-copy {
  min-width: 0;
}

.thailand-mission-copy strong,
.thailand-mission-copy span {
  display: block;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.thailand-mission-copy strong {
  font-size: 0.68rem;
}

.thailand-mission-copy span {
  font-size: 0.62rem;
  color: #52606d;
}

@media (max-width: 1100px) {
  .thailand-map-stage {
    min-height: 620px;
  }

  .thailand-mission-strip {
    grid-template-columns: repeat(4, minmax(92px, 1fr));
    right: 7rem;
  }
}

.thailand-game-map--fullscreen .thailand-mission-strip {
  display: none;
}

.thailand-game-map--fullscreen .thailand-player-panel {
  top: clamp(5.75rem, 12vh, 7.4rem);
  left: clamp(0.75rem, 1.5vw, 1.25rem);
  width: clamp(132px, 14vw, 176px);
}

.thailand-game-map--fullscreen .thailand-round-timer {
  right: clamp(0.75rem, 2vw, 1.5rem);
  bottom: clamp(0.75rem, 2vw, 1.5rem);
}

@media (max-width: 900px), (max-height: 620px) {
  .thailand-game-map--fullscreen .thailand-player-panel {
    top: 5.15rem;
    width: 128px;
    padding: 0.45rem;
  }

  .thailand-game-map--fullscreen .thailand-player-panel-title {
    font-size: 0.72rem;
  }

  .thailand-game-map--fullscreen .thailand-player-list li {
    grid-template-columns: 16px 24px 1fr;
    gap: 0.22rem;
  }

  .thailand-game-map--fullscreen .thailand-player-rank {
    width: 16px;
    height: 16px;
    font-size: 0.6rem;
  }

  .thailand-game-map--fullscreen .thailand-player-name {
    font-size: 0.62rem;
  }

  .thailand-game-map--fullscreen .thailand-player-action {
    width: 74px;
    height: 60px;
  }

  .thailand-game-map--fullscreen .thailand-timer-ring {
    width: 72px;
    height: 72px;
    border-width: 7px;
  }

  .thailand-game-map--fullscreen .thailand-timer-ring span {
    font-size: 1.25rem;
  }
}
</style>
