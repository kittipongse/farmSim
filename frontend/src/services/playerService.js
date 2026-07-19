import api, { unwrap } from './api'
import { fileToBase64 } from '@/utils/imageUpload'

export function getPlayer(playerId) {
  return api.get(`/players/${playerId}`).then(unwrap)
}

export function getGameReview(playerId) {
  return api.get(`/players/${playerId}/game-review`).then(unwrap)
}

export function selectRegion(playerId, regionId) {
  return api.post(`/players/${playerId}/select-region`, { region_id: regionId }).then(unwrap)
}

/** อัปโหลดรูปโปรไฟล์ — ส่ง base64 ผ่าน /api (same-origin หลีกเลี่ยง CORS) */
export async function uploadProfile(playerId, file) {
  const imageBase64 = await fileToBase64(file)
  const filename = file?.name || `photo_${Date.now()}.jpg`
  return api
    .post(
      `/players/${playerId}/upload-profile`,
      {
        image_base64: imageBase64,
        filename,
        mime_type: file?.type || 'image/jpeg',
      },
      { timeout: 60000 },
    )
    .then(unwrap)
}
