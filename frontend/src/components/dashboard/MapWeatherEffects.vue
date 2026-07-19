<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { MAP_ZONE_RECTS, resolveWeatherProfile } from '@/constants/weatherEffects'

const props = defineProps({
  active: { type: Boolean, default: false },
  event: { type: Object, default: null },
  breakingNews: { type: Boolean, default: false },
})

const emit = defineEmits(['weather-thunder'])

const canvasRef = ref(null)
const profile = computed(() => resolveWeatherProfile(props.event))
const intensity = computed(() => (props.breakingNews ? 1 : 0.55))

let raf = null
let clouds = []
let raindrops = []
let lightningTimer = 0
let flashAlpha = 0
let resizeObserver = null

function zonePixels(zoneKey, width, height) {
  const rect = MAP_ZONE_RECTS[zoneKey]
  if (!rect) return null
  return {
    x: (rect.left / 100) * width,
    y: (rect.top / 100) * height,
    w: (rect.width / 100) * width,
    h: (rect.height / 100) * height,
  }
}

function seedClouds(width, height) {
  clouds = []
  const zones = profile.value.regions
  for (let i = 0; i < 14; i += 1) {
    const zoneKey = zones[i % zones.length]
    const zone = zonePixels(zoneKey, width, height)
    if (!zone) continue
    clouds.push({
      x: zone.x + Math.random() * zone.w,
      y: zone.y + Math.random() * zone.h * 0.6,
      scale: 0.55 + Math.random() * 0.9,
      speed: 0.15 + Math.random() * 0.35,
      opacity: 0.35 + Math.random() * 0.35,
      zoneKey,
    })
  }
}

function seedRain(width, height) {
  raindrops = []
  const type = profile.value.type
  if (!['flood', 'storm', 'rain_light'].includes(type) && !props.breakingNews) return
  const count = type === 'rain_light' ? 80 : 160
  for (let i = 0; i < count; i += 1) {
    raindrops.push({
      x: Math.random() * width,
      y: Math.random() * height,
      len: 8 + Math.random() * 14,
      speed: 9 + Math.random() * 10,
    })
  }
}

function drawCloud(ctx, x, y, scale, opacity) {
  ctx.save()
  ctx.globalAlpha = opacity
  ctx.fillStyle = 'rgba(245, 248, 255, 0.92)'
  const r = 18 * scale
  ctx.beginPath()
  ctx.ellipse(x, y, r * 1.3, r * 0.72, 0, 0, Math.PI * 2)
  ctx.ellipse(x - r, y + r * 0.15, r, r * 0.62, 0, 0, Math.PI * 2)
  ctx.ellipse(x + r * 0.95, y + r * 0.2, r * 1.05, r * 0.68, 0, 0, Math.PI * 2)
  ctx.fill()
  ctx.restore()
}

function drawLightning(ctx, zone) {
  const cx = zone.x + zone.w * 0.5
  const top = zone.y + zone.h * 0.1
  ctx.save()
  ctx.strokeStyle = 'rgba(255, 255, 220, 0.95)'
  ctx.lineWidth = 3
  ctx.shadowColor = '#fff'
  ctx.shadowBlur = 16
  ctx.beginPath()
  ctx.moveTo(cx, top)
  let px = cx
  let py = top
  for (let i = 0; i < 5; i += 1) {
    px += (Math.random() - 0.5) * zone.w * 0.25
    py += zone.h * 0.16
    ctx.lineTo(px, py)
  }
  ctx.stroke()
  ctx.restore()
}

function drawDrought(ctx, width, height, zones) {
  ctx.save()
  ctx.globalAlpha = 0.18 * intensity.value
  ctx.fillStyle = '#ff9800'
  ctx.fillRect(0, 0, width, height)
  ctx.globalAlpha = 0.35
  ctx.strokeStyle = 'rgba(255, 200, 80, 0.5)'
  for (const zoneKey of zones) {
    const zone = zonePixels(zoneKey, width, height)
    if (!zone) continue
    for (let i = 0; i < 4; i += 1) {
      const y = zone.y + zone.h * (0.2 + i * 0.18)
      ctx.beginPath()
      ctx.moveTo(zone.x, y)
      for (let x = zone.x; x < zone.x + zone.w; x += 12) {
        ctx.lineTo(x, y + Math.sin((x + performance.now() * 0.004) * 0.08) * 4)
      }
      ctx.stroke()
    }
  }
  ctx.restore()
}

function drawFire(ctx, zones, width, height) {
  for (const zoneKey of zones) {
    const zone = zonePixels(zoneKey, width, height)
    if (!zone) continue
    for (let i = 0; i < 6; i += 1) {
      const fx = zone.x + Math.random() * zone.w
      const fy = zone.y + zone.h * 0.55 + Math.random() * zone.h * 0.3
      const grad = ctx.createRadialGradient(fx, fy, 2, fx, fy, 22 + Math.random() * 18)
      grad.addColorStop(0, 'rgba(255, 220, 80, 0.75)')
      grad.addColorStop(0.5, 'rgba(255, 100, 20, 0.45)')
      grad.addColorStop(1, 'rgba(255, 60, 0, 0)')
      ctx.fillStyle = grad
      ctx.beginPath()
      ctx.arc(fx, fy, 24, 0, Math.PI * 2)
      ctx.fill()
    }
  }
}

function tick() {
  const canvas = canvasRef.value
  if (!canvas || !props.active) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const { width, height } = canvas
  ctx.clearRect(0, 0, width, height)

  const zones = profile.value.regions
  const type = profile.value.type

  if (type === 'drought') {
    drawDrought(ctx, width, height, zones)
  }

  for (const cloud of clouds) {
    const zone = zonePixels(cloud.zoneKey, width, height)
    if (!zone) continue
    cloud.x += cloud.speed * intensity.value
    if (cloud.x > zone.x + zone.w + 40) {
      cloud.x = zone.x - 40
      cloud.y = zone.y + Math.random() * zone.h * 0.5
    }
    const showCloud = ['flood', 'storm', 'ambient', 'rain_light', 'swarm'].includes(type)
      || props.breakingNews
    if (showCloud) {
      drawCloud(ctx, cloud.x, cloud.y, cloud.scale, cloud.opacity * intensity.value)
    }
  }

  const showRain = ['flood', 'storm', 'rain_light'].includes(type)
    || (props.breakingNews && type !== 'drought')
  if (showRain) {
    ctx.save()
    ctx.strokeStyle = 'rgba(174, 214, 255, 0.65)'
    ctx.lineWidth = 1.4
    for (const drop of raindrops) {
      let inZone = false
      for (const zoneKey of zones) {
        const zone = zonePixels(zoneKey, width, height)
        if (!zone) continue
        if (drop.x >= zone.x && drop.x <= zone.x + zone.w && drop.y >= zone.y && drop.y <= zone.y + zone.h) {
          inZone = true
          break
        }
      }
      if (!inZone && type !== 'storm') continue
      ctx.beginPath()
      ctx.moveTo(drop.x, drop.y)
      ctx.lineTo(drop.x - 3, drop.y + drop.len)
      ctx.stroke()
      drop.y += drop.speed * intensity.value
      drop.x -= 1.2
      if (drop.y > height) {
        drop.y = -drop.len
        drop.x = Math.random() * width
      }
    }
    ctx.restore()
  }

  if (['flood', 'storm'].includes(type) && props.breakingNews) {
    lightningTimer -= 1
    if (lightningTimer <= 0) {
      lightningTimer = 90 + Math.floor(Math.random() * 120)
      flashAlpha = 0.55
      const zoneKey = zones[Math.floor(Math.random() * zones.length)]
      const zone = zonePixels(zoneKey, width, height)
      if (zone) drawLightning(ctx, zone)
      emit('weather-thunder')
    }
  }

  if (flashAlpha > 0) {
    ctx.save()
    ctx.globalAlpha = flashAlpha
    ctx.fillStyle = '#fff'
    ctx.fillRect(0, 0, width, height)
    ctx.restore()
    flashAlpha *= 0.82
  }

  if (type === 'fire' && props.breakingNews) {
    drawFire(ctx, zones, width, height)
  }

  raf = requestAnimationFrame(tick)
}

function resizeCanvas() {
  const canvas = canvasRef.value
  const parent = canvas?.parentElement
  if (!canvas || !parent) return
  const rect = parent.getBoundingClientRect()
  const dpr = Math.min(window.devicePixelRatio || 1, 2)
  canvas.width = Math.floor(rect.width * dpr)
  canvas.height = Math.floor(rect.height * dpr)
  canvas.style.width = `${rect.width}px`
  canvas.style.height = `${rect.height}px`
  const ctx = canvas.getContext('2d')
  if (ctx) ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  seedClouds(rect.width, rect.height)
  seedRain(rect.width, rect.height)
}

function start() {
  stop()
  resizeCanvas()
  raf = requestAnimationFrame(tick)
}

function stop() {
  if (raf) cancelAnimationFrame(raf)
  raf = null
}

watch(
  () => [props.active, props.event?.id, props.event?.code, props.breakingNews],
  () => {
    if (!props.active) {
      stop()
      return
    }
    seedRain(
      canvasRef.value?.parentElement?.clientWidth || 800,
      canvasRef.value?.parentElement?.clientHeight || 500,
    )
    if (!raf) start()
  }
)

onMounted(() => {
  const parent = canvasRef.value?.parentElement
  if (!parent) return
  resizeObserver = new ResizeObserver(() => resizeCanvas())
  resizeObserver.observe(parent)
  if (props.active) start()
})

onUnmounted(() => {
  stop()
  resizeObserver?.disconnect()
})
</script>

<template>
  <canvas
    v-show="active"
    ref="canvasRef"
    class="map-weather-canvas"
    aria-hidden="true"
  />
</template>

<style scoped>
.map-weather-canvas {
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
}
</style>
