<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import CardSprite from '@/components/cards/CardSprite.vue'
import CardActionAnimation from '@/components/sprites/CardActionAnimation.vue'
import PlayerAvatar from '@/components/PlayerAvatar.vue'
import thailandMap from '@/assets/images/thailand.png'
import { DECISION_CARDS, UI_SPRITES, spriteIndexForCode } from '@/constants/cardSprites'
import { hasExactActionSprite } from '@/constants/actionSprites'

const REGION_POSITIONS = {
  north: { x: 50, y: 20, label: 'ภาคเหนือ' },
  central: { x: 50, y: 48, label: 'ภาคกลาง' },
  south: { x: 50, y: 74, label: 'ภาคใต้' },
  east: { x: 76, y: 42, label: 'ภาคตะวันออก' },
  west: { x: 24, y: 42, label: 'ภาคตะวันตก' },
}

const REGION_ORDER = ['north', 'central', 'south', 'east', 'west']
const MARKER_OFFSETS = [
  { x: 0, y: 0 },
  { x: -6, y: 5 },
  { x: 6, y: 5 },
  { x: -7, y: -6 },
  { x: 7, y: -6 },
  { x: 0, y: 9 },
  { x: -11, y: 0 },
  { x: 11, y: 0 },
]
const ROUND_SECONDS = 15

const props = defineProps({
  room: { type: Object, default: null },
  players: { type: Array, default: () => [] },
  ranking: { type: Array, default: () => [] },
  currentCards: { type: Object, default: () => ({}) },
  remaining: { type: Number, default: ROUND_SECONDS },
  active: { type: Boolean, default: false },
  maxPlayers: { type: Number, default: 8 },
})

const localRemaining = ref(ROUND_SECONDS)
let timer = null

const playerList = computed(() => (Array.isArray(props.players) ? props.players : []))
const playerCount = computed(() =>
  Math.max(playerList.value.length, props.room?.player_count || 0)
)
const year = computed(() => props.room?.current_year || 1)
const month = computed(() => props.room?.current_month || 1)

const scoreByPlayerId = computed(() => {
  const map = {}
  for (const row of props.ranking || []) {
    map[row.player_id] = row.total_score
  }
  return map
})

function cardMeta(code) {
  return DECISION_CARDS.find((card) => card.code === code)
}

function currentCardFor(playerId) {
  return props.currentCards?.[playerId] || null
}

function normalizeRegionText(player) {
  return [
    player?.region_name_th,
    player?.region_name_en,
    player?.region_code,
    player?.region,
  ]
    .filter(Boolean)
    .join(' ')
    .toString()
    .toLowerCase()
}

function regionKeyForPlayer(player, fallbackIndex = 0) {
  const regionText = normalizeRegionText(player)
  if (regionText.includes('เหนือ') || regionText.includes('north')) return 'north'
  if (regionText.includes('ใต้') || regionText.includes('south')) return 'south'
  if (regionText.includes('ตะวันออก') || regionText.includes('east')) return 'east'
  if (regionText.includes('ตะวันตก') || regionText.includes('west')) return 'west'
  if (regionText.includes('กลาง') || regionText.includes('central')) return 'central'
  if (player?.region_id) return REGION_ORDER[(Number(player.region_id) - 1) % REGION_ORDER.length]
  return REGION_ORDER[fallbackIndex % REGION_ORDER.length]
}

const mapPlayers = computed(() => {
  const regionCounts = {}
  return playerList.value.map((player, index) => {
    const regionKey = regionKeyForPlayer(player, index)
    const region = REGION_POSITIONS[regionKey] || REGION_POSITIONS.central
    const count = regionCounts[regionKey] || 0
    regionCounts[regionKey] = count + 1
    const offset = MARKER_OFFSETS[count % MARKER_OFFSETS.length]
    const card = currentCardFor(player.id)

    return {
      player,
      regionKey,
      regionLabel: region.label,
      card,
      x: Math.min(90, Math.max(10, region.x + offset.x)),
      y: Math.min(82, Math.max(14, region.y + offset.y)),
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

function displayScore(player) {
  const score = scoreByPlayerId.value[player.id]
  return Number.isFinite(Number(score)) ? Number(score).toLocaleString() : '-'
}

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
  if (!props.active) return
  timer = setInterval(() => {
    localRemaining.value = Math.max(0, localRemaining.value - 1)
  }, 1000)
}

watch(
  () => [props.active, year.value, month.value],
  () => {
    resetTimer()
    startTimer()
  },
  { immediate: true }
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
  <section class="thailand-game-map">
    <div class="thailand-map-stage" :style="{ backgroundImage: `url('${thailandMap}')` }">
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
            <span class="thailand-player-score">{{ displayScore(player) }}</span>
          </li>
        </ol>
      </aside>

      <div class="thailand-region-label thailand-region-label--north">ภาคเหนือ</div>
      <div class="thailand-region-label thailand-region-label--west">ภาคตะวันตก</div>
      <div class="thailand-region-label thailand-region-label--central">ภาคกลาง</div>
      <div class="thailand-region-label thailand-region-label--east">ภาคตะวันออก</div>
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
          <span>{{ String(localRemaining).padStart(2, '0') }}</span>
        </div>
        <small>วินาที</small>
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
  grid-template-columns: 18px 28px 1fr auto;
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

.thailand-player-score {
  font-size: 0.66rem;
  font-weight: 900;
  color: #a66b00;
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
  top: 12%;
  left: 47%;
}

.thailand-region-label--west {
  top: 37%;
  left: 17%;
}

.thailand-region-label--central {
  top: 43%;
  left: 47%;
}

.thailand-region-label--east {
  top: 35%;
  right: 16%;
}

.thailand-region-label--south {
  bottom: 28%;
  left: 47%;
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
  font-size: 1.7rem;
  font-weight: 900;
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
</style>
