import { createRouter, createWebHistory } from 'vue-router'
import { useSessionStore } from '@/stores/sessionStore'
import { ensureJoinRoomSession, normalizeRoomCode } from '@/utils/sessionGuard'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/pages/HomePage.vue'),
  },
  {
    path: '/create',
    name: 'create',
    component: () => import('@/pages/create/CreateRoomPage.vue'),
  },
  {
    path: '/join',
    name: 'join',
    component: () => import('@/pages/mobile/JoinPage.vue'),
  },
  {
    path: '/join/:roomCode',
    name: 'join-room',
    component: () => import('@/pages/mobile/JoinPage.vue'),
  },
  {
    path: '/dashboard/lobby/:roomCode',
    name: 'dashboard-lobby',
    component: () => import('@/pages/dashboard/LobbyDashboardPage.vue'),
  },
  {
    path: '/dashboard/game/:roomCode',
    name: 'dashboard-game',
    component: () => import('@/pages/dashboard/GameDashboardPage.vue'),
  },
  {
    path: '/mobile/profile',
    name: 'mobile-profile',
    meta: { requiresSession: true },
    component: () => import('@/pages/mobile/ProfilePage.vue'),
  },
  {
    path: '/mobile/select-region',
    name: 'mobile-region',
    meta: { requiresSession: true },
    component: () => import('@/pages/mobile/SelectRegionPage.vue'),
  },
  {
    path: '/mobile/waiting',
    name: 'mobile-waiting',
    meta: { requiresSession: true },
    component: () => import('@/pages/mobile/WaitingPage.vue'),
  },
  {
    path: '/mobile/plan-cards',
    name: 'mobile-plan-cards',
    meta: { requiresSession: true },
    component: () => import('@/pages/mobile/CardPlanPage.vue'),
  },
  {
    path: '/mobile/simulation',
    name: 'mobile-simulation',
    meta: { requiresSession: true },
    component: () => import('@/pages/mobile/SimulationPage.vue'),
  },
  {
    path: '/mobile/finished',
    name: 'mobile-finished',
    meta: { requiresSession: true },
    component: () => import('@/pages/mobile/FinishedPage.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to) => {
  const session = useSessionStore()
  const targetRoom = normalizeRoomCode(to.params.roomCode)
  const forceFresh = to.query.fresh === '1'

  if (to.name === 'join-room' && targetRoom) {
    ensureJoinRoomSession(targetRoom, { forceFresh })
  }

  if (to.meta.requiresSession && !session.isLoggedIn) {
    if (targetRoom) {
      return { name: 'join-room', params: { roomCode: targetRoom }, query: to.query }
    }
    return { name: 'join' }
  }
})

export default router
