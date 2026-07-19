/** แปลง/ย่อรูปเป็น JPEG ก่อนอัปโหลด (รองรับมือถือ) */
export function prepareProfileImage(file) {
  if (!file) {
    return Promise.reject(new Error('ไม่พบไฟล์รูป'))
  }

  const name = (file.name || '').toLowerCase()
  if (name.endsWith('.heic') || name.endsWith('.heif') || file.type === 'image/heic' || file.type === 'image/heif') {
    return Promise.reject(new Error('รูป HEIC ยังไม่รองรับ — เลือก JPG หรือถ่ายใหม่เป็น JPEG'))
  }

  if (file.type === 'image/jpeg' && file.size <= 2 * 1024 * 1024) {
    return Promise.resolve(ensureJpegName(file))
  }

  return new Promise((resolve, reject) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      URL.revokeObjectURL(url)
      const maxWidth = 1200
      let width = img.width
      let height = img.height
      if (width > maxWidth) {
        height = Math.round((height * maxWidth) / width)
        width = maxWidth
      }
      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height
      const ctx = canvas.getContext('2d')
      if (!ctx) {
        reject(new Error('เบราว์เซอร์ไม่รองรับการประมวลผลรูป'))
        return
      }
      ctx.drawImage(img, 0, 0, width, height)
      canvas.toBlob(
        (blob) => {
          if (!blob) {
            reject(new Error('แปลงรูปไม่สำเร็จ ลองถ่ายใหม่'))
            return
          }
          resolve(new File([blob], 'photo.jpg', { type: 'image/jpeg', lastModified: Date.now() }))
        },
        'image/jpeg',
        0.88,
      )
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('โหลดรูปไม่สำเร็จ — ลองถ่ายใหม่หรือเลือก JPG/PNG'))
    }
    img.src = url
  })
}

function ensureJpegName(file) {
  if (file.name && /\.jpe?g$/i.test(file.name)) return file
  return new File([file], 'photo.jpg', { type: 'image/jpeg', lastModified: file.lastModified })
}

/** อ่านไฟล์เป็น base64 (ไม่มี prefix data:...) */
export function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => {
      const result = reader.result
      if (typeof result !== 'string') {
        reject(new Error('อ่านรูปไม่สำเร็จ'))
        return
      }
      const comma = result.indexOf(',')
      resolve(comma >= 0 ? result.slice(comma + 1) : result)
    }
    reader.onerror = () => reject(new Error('อ่านรูปไม่สำเร็จ'))
    reader.readAsDataURL(file)
  })
}
