<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MobileLayout from '@/layouts/MobileLayout.vue'
import { joinRoom } from '@/services/gameRoomService'
import { useSessionStore } from '@/stores/sessionStore'
import { useGameRoomStore } from '@/stores/gameRoomStore'
import { ensureJoinRoomSession, normalizeRoomCode } from '@/utils/sessionGuard'

const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const gameRoom = useGameRoomStore()

const roomCode = ref('')
const pin = ref('')
const name = ref('')
const loading = ref(false)
const error = ref('')
const switchedRoom = ref(false)

const canSubmit = computed(() => roomCode.value && pin.value && name.value.trim())

async function handleJoin() {
  if (!canSubmit.value) {
    error.value = 'กรุณากรอกข้อมูลให้ครบ'
    return
  }
  loading.value = true
  error.value = ''
  try {
    const data = await joinRoom(roomCode.value.toUpperCase(), name.value.trim(), pin.value)
    gameRoom.reset()
    session.setSession({
      player: data.player,
      room: data.room,
      pin: pin.value,
    })
    router.push({ name: 'mobile-profile' })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function syncJoinRoute() {
  const urlRoomCode = normalizeRoomCode(route.params.roomCode)
  const forceFresh = route.query.fresh === '1'

  if (urlRoomCode) {
    roomCode.value = urlRoomCode
  }

  if (urlRoomCode) {
    const { switched } = ensureJoinRoomSession(urlRoomCode, { forceFresh })
    if (switched) {
      switchedRoom.value = true
      pin.value = ''
      name.value = ''
      return
    }
  }

  switchedRoom.value = false

  if (session.isLoggedIn && session.roomCode) {
    const sameRoom = !urlRoomCode || normalizeRoomCode(session.roomCode) === urlRoomCode
    if (!session.playerName) {
      router.replace({ name: 'mobile-profile' })
    } else if (route.name === 'join-room' && sameRoom && !forceFresh) {
      router.replace({ name: 'mobile-waiting' })
    }
  }
}

watch(
  () => [route.params.roomCode, route.query.fresh, route.name],
  () => syncJoinRoute(),
  { immediate: true }
)
</script>

<template>
  <MobileLayout>
    <h2 class="page-title mb-4">เข้าร่วมเกม</h2>
    <div v-if="switchedRoom" class="alert alert-warning">
      สแกน QR ห้องใหม่แล้ว — กรุณากรอก Game PIN และชื่อเพื่อเข้าร่วมห้อง
      <strong>{{ roomCode }}</strong>
    </div>
    <div class="card card-farm p-4">
      <div class="mb-3">
        <label class="form-label">รหัสห้อง</label>
        <input
          v-model="roomCode"
          type="text"
          class="form-control form-control-lg text-uppercase"
          placeholder="เช่น ABC123"
          maxlength="6"
          :readonly="!!route.params.roomCode"
        />
      </div>
      <div class="mb-3">
        <label class="form-label">Game PIN</label>
        <input
          v-model="pin"
          type="text"
          class="form-control form-control-lg"
          placeholder="6 หลัก"
          maxlength="6"
          inputmode="numeric"
          autocomplete="off"
        />
      </div>
      <div class="mb-4">
        <label class="form-label">ชื่อผู้เล่น</label>
        <input
          v-model="name"
          type="text"
          class="form-control form-control-lg"
          placeholder="ชื่อของคุณ"
          maxlength="100"
          autocomplete="off"
        />
      </div>
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <button class="btn btn-farm btn-lg w-100" :disabled="loading" @click="handleJoin">
        {{ loading ? 'กำลังเข้า...' : 'เข้าร่วมเกม' }}
      </button>
    </div>
  </MobileLayout>
</template>
