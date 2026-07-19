import api, { unwrap } from './api'

export function getHallOfFame(limit = 5) {
  return api.get('/hall-of-fame', { params: { limit } }).then(unwrap)
}
