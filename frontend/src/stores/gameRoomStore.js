import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useGameRoomStore = defineStore('gameRoom', () => {
  const room = ref(null)
  const players = ref([])
  const lobbyRemaining = ref(120)
  const countdownRemaining = ref(0)
  const simulationRemaining = ref(0)
  const breakingNewsRemaining = ref(0)
  const simulation = ref(null)
  const ranking = ref([])
  const gameSummary = ref(null)
  const bonusQuiz = ref(null)
  const presentation = ref(null)

  function updateFromPoll(data) {
    if (!data?.room) return
    room.value = data.room
    players.value = Array.isArray(data.players) ? [...data.players] : []
    lobbyRemaining.value = data.lobby_remaining_seconds ?? 0
    countdownRemaining.value = data.countdown_remaining_seconds ?? 0
    simulationRemaining.value = data.simulation_remaining_seconds ?? 0
    breakingNewsRemaining.value = data.breaking_news_remaining_seconds
      ?? data.simulation?.breaking_news_remaining_seconds
      ?? 0
    if (data.simulation) simulation.value = data.simulation
    bonusQuiz.value = data.simulation?.bonus_quiz ?? null
    presentation.value = data.presentation ?? null
    if (data.ranking) ranking.value = data.ranking
    gameSummary.value = data.game_summary ?? null
  }

  function reset() {
    room.value = null
    players.value = []
    lobbyRemaining.value = 120
    countdownRemaining.value = 0
    simulationRemaining.value = 0
    breakingNewsRemaining.value = 0
    simulation.value = null
    bonusQuiz.value = null
    presentation.value = null
    ranking.value = []
    gameSummary.value = null
  }

  return {
    room,
    players,
    lobbyRemaining,
    countdownRemaining,
    simulationRemaining,
    breakingNewsRemaining,
    simulation,
    bonusQuiz,
    presentation,
    ranking,
    gameSummary,
    updateFromPoll,
    reset,
  }
})
