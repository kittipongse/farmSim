import api, { unwrap } from './api'

export function getCountries() {
  return api.get('/countries').then(unwrap)
}

export function getRegions(countryId) {
  return api.get(`/countries/${countryId}/regions`).then(unwrap)
}

export function getPlantingGuide(regionId) {
  return api.get(`/regions/${regionId}/planting-guide`).then(unwrap)
}
