import axios from 'axios'
import { apiBaseUrl } from '@/utils/paths'

const api = axios.create({
  baseURL: apiBaseUrl(),
  headers: {
    'Content-Type': 'application/json; charset=utf-8',
    'Accept': 'application/json; charset=utf-8',
  },
  timeout: 15000,
})

api.interceptors.response.use(
  (response) => {
    const data = response.data
    if (data && data.success === false) {
      return Promise.reject(new Error(data.message || 'เกิดข้อผิดพลาด'))
    }
    return response
  },
  (error) => {
    const data = error.response?.data
    let message = data?.message || error.message || 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'
    if (message === 'Network Error') {
      message = 'เชื่อมต่อ API ไม่ได้ — ตรวจสอบอินเทอร์เน็ต หรือ restart npm run dev'
    } else if (error.response?.status === 500 && !data?.message) {
      message = 'เซิร์ฟเวอร์ error 500 — อัปโหลด backend ล่าสุด (DashboardController.php)'
    }
    return Promise.reject(new Error(message))
  }
)

export function unwrap(response) {
  const payload = response?.data?.data
  if (payload === undefined) {
    return Promise.reject(new Error('ข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง'))
  }
  return payload
}

export default api
