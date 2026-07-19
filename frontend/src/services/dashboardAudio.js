/**
 * เสียง dashboard — title จาก mp3, SFX สังเคราะห์ด้วย Web Audio API
 */

import titleMusicUrl from '@/assets/sounds/dashsong1.mp3'

let ctx = null
let master = null
let titleAudio = null
let rainNode = null
let rainGain = null
let windNode = null
let windGain = null
let muted = false
let themePlaying = false

const TITLE_VOLUME = 0.5

function ensureContext() {
  if (ctx) return ctx
  const AudioContext = window.AudioContext || window.webkitAudioContext
  if (!AudioContext) return null
  ctx = new AudioContext()
  master = ctx.createGain()
  master.gain.value = muted ? 0 : 0.85
  master.connect(ctx.destination)
  return ctx
}

export async function resumeAudio() {
  const audio = ensureContext()
  if (!audio) return false
  if (audio.state === 'suspended') {
    await audio.resume()
  }
  return audio.state === 'running'
}

export function isMuted() {
  return muted
}

export function setMuted(value) {
  muted = Boolean(value)
  if (master) {
    master.gain.setTargetAtTime(muted ? 0 : 0.85, ctx.currentTime, 0.05)
  }
  syncTitleVolume()
}

function getTitleAudio() {
  if (!titleAudio) {
    titleAudio = new Audio(titleMusicUrl)
    titleAudio.loop = true
    titleAudio.preload = 'auto'
  }
  return titleAudio
}

function syncTitleVolume() {
  const audio = titleAudio
  if (!audio) return
  audio.volume = muted ? 0 : TITLE_VOLUME
}

function playTone(freq, start, duration, type = 'triangle', volume = 0.12) {
  if (!ctx || muted) return
  const osc = ctx.createOscillator()
  const gain = ctx.createGain()
  osc.type = type
  osc.frequency.value = freq
  gain.gain.setValueAtTime(0, start)
  gain.gain.linearRampToValueAtTime(volume, start + 0.02)
  gain.gain.exponentialRampToValueAtTime(0.001, start + duration)
  osc.connect(gain)
  gain.connect(master)
  osc.start(start)
  osc.stop(start + duration + 0.05)
}

export async function startTitleTheme() {
  await resumeAudio()
  if (themePlaying) return
  const audio = getTitleAudio()
  syncTitleVolume()
  try {
    await audio.play()
    themePlaying = true
  } catch {
    themePlaying = false
  }
}

export function stopTitleTheme() {
  themePlaying = false
  if (!titleAudio) return
  titleAudio.pause()
  titleAudio.currentTime = 0
}

export async function playBreakingNewsAlert() {
  await resumeAudio()
  if (!ctx || muted) return
  const now = ctx.currentTime
  ;[880, 660, 880, 1100].forEach((freq, i) => {
    playTone(freq, now + i * 0.12, 0.14, 'square', 0.08)
  })
  const noise = ctx.createBufferSource()
  const buffer = ctx.createBuffer(1, ctx.sampleRate * 0.25, ctx.sampleRate)
  const data = buffer.getChannelData(0)
  for (let i = 0; i < data.length; i += 1) {
    data[i] = (Math.random() * 2 - 1) * 0.35
  }
  noise.buffer = buffer
  const ng = ctx.createGain()
  ng.gain.setValueAtTime(0.12, now)
  ng.gain.exponentialRampToValueAtTime(0.001, now + 0.25)
  noise.connect(ng)
  ng.connect(master)
  noise.start(now)
  noise.stop(now + 0.3)
}

export async function playThunder() {
  await resumeAudio()
  if (!ctx || muted) return
  const now = ctx.currentTime
  const noise = ctx.createBufferSource()
  const buffer = ctx.createBuffer(1, ctx.sampleRate * 1.2, ctx.sampleRate)
  const data = buffer.getChannelData(0)
  for (let i = 0; i < data.length; i += 1) {
    data[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / data.length, 1.8)
  }
  noise.buffer = buffer
  const filter = ctx.createBiquadFilter()
  filter.type = 'lowpass'
  filter.frequency.value = 420
  const gain = ctx.createGain()
  gain.gain.setValueAtTime(0.45, now)
  gain.gain.exponentialRampToValueAtTime(0.001, now + 1.1)
  noise.connect(filter)
  filter.connect(gain)
  gain.connect(master)
  noise.start(now)
  noise.stop(now + 1.2)
  playTone(55, now, 0.35, 'sine', 0.2)
}

export async function playChime() {
  await resumeAudio()
  if (!ctx || muted) return
  const now = ctx.currentTime
  ;[523.25, 659.25, 783.99].forEach((freq, i) => {
    playTone(freq, now + i * 0.08, 0.35, 'sine', 0.07)
  })
}

/** เสียงตบมือสังเคราะห์สำหรับประกาศผู้ตอบถูก */
export async function playApplause() {
  await resumeAudio()
  if (!ctx || muted) return
  const now = ctx.currentTime
  const duration = 2.2
  const buffer = ctx.createBuffer(1, Math.floor(ctx.sampleRate * duration), ctx.sampleRate)
  const data = buffer.getChannelData(0)

  for (let i = 0; i < data.length; i += 1) {
    const t = i / ctx.sampleRate
    const envelope = Math.exp(-t * 0.9) * (0.55 + 0.45 * Math.sin(t * 18))
    // คลัสเตอร์คลิกสั้น ๆ เลียนเสียงตบมือหลายคน
    const burst = Math.random() > 0.72 ? (Math.random() * 2 - 1) : (Math.random() * 2 - 1) * 0.15
    data[i] = burst * envelope * 0.55
  }

  const source = ctx.createBufferSource()
  source.buffer = buffer
  const filter = ctx.createBiquadFilter()
  filter.type = 'bandpass'
  filter.frequency.value = 2200
  filter.Q.value = 0.6
  const gain = ctx.createGain()
  gain.gain.setValueAtTime(0.001, now)
  gain.gain.linearRampToValueAtTime(0.55, now + 0.08)
  gain.gain.exponentialRampToValueAtTime(0.001, now + duration)
  source.connect(filter)
  filter.connect(gain)
  gain.connect(master)
  source.start(now)
  source.stop(now + duration + 0.05)

  // โน้ตฉลองเบา ๆ
  ;[523.25, 659.25, 783.99, 1046.5].forEach((freq, i) => {
    playTone(freq, now + 0.15 + i * 0.1, 0.28, 'triangle', 0.06)
  })
}

export async function startRainAmbience() {
  await resumeAudio()
  if (!ctx || rainNode) return
  const bufferSize = ctx.sampleRate * 2
  const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate)
  const data = buffer.getChannelData(0)
  for (let i = 0; i < bufferSize; i += 1) {
    data[i] = Math.random() * 2 - 1
  }
  rainNode = ctx.createBufferSource()
  rainNode.buffer = buffer
  rainNode.loop = true
  const filter = ctx.createBiquadFilter()
  filter.type = 'bandpass'
  filter.frequency.value = 1800
  filter.Q.value = 0.4
  rainGain = ctx.createGain()
  rainGain.gain.value = 0
  rainNode.connect(filter)
  filter.connect(rainGain)
  rainGain.connect(master)
  rainNode.start()
  rainGain.gain.setTargetAtTime(muted ? 0 : 0.14, ctx.currentTime, 0.4)
}

export function stopRainAmbience() {
  if (!rainGain || !ctx) {
    rainNode = null
    rainGain = null
    return
  }
  rainGain.gain.setTargetAtTime(0, ctx.currentTime, 0.3)
  const node = rainNode
  rainNode = null
  rainGain = null
  window.setTimeout(() => {
    try { node.stop() } catch { /* already stopped */ }
  }, 400)
}

export async function startWindAmbience() {
  await resumeAudio()
  if (!ctx || windNode) return
  const bufferSize = ctx.sampleRate * 3
  const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate)
  const data = buffer.getChannelData(0)
  let last = 0
  for (let i = 0; i < bufferSize; i += 1) {
    last = last * 0.98 + (Math.random() * 2 - 1) * 0.08
    data[i] = last
  }
  windNode = ctx.createBufferSource()
  windNode.buffer = buffer
  windNode.loop = true
  const filter = ctx.createBiquadFilter()
  filter.type = 'lowpass'
  filter.frequency.value = 600
  windGain = ctx.createGain()
  windGain.gain.value = 0
  windNode.connect(filter)
  filter.connect(windGain)
  windGain.connect(master)
  windNode.start()
  windGain.gain.setTargetAtTime(muted ? 0 : 0.18, ctx.currentTime, 0.5)
}

export function stopWindAmbience() {
  if (!windGain || !ctx) {
    windNode = null
    windGain = null
    return
  }
  windGain.gain.setTargetAtTime(0, ctx.currentTime, 0.3)
  const node = windNode
  windNode = null
  windGain = null
  window.setTimeout(() => {
    try { node.stop() } catch { /* noop */ }
  }, 400)
}

export function stopAllAmbience() {
  stopRainAmbience()
  stopWindAmbience()
}

export function disposeDashboardAudio() {
  stopTitleTheme()
  stopAllAmbience()
  if (titleAudio) {
    titleAudio.src = ''
    titleAudio = null
  }
  if (ctx) {
    ctx.close().catch(() => {})
  }
  ctx = null
  master = null
}
