import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

const CLOUD_API_TARGET = 'https://znix.online/farmsim'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const base = env.VITE_BASE_PATH || '/'

  return {
    base,
    plugins: [vue()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      host: true,
      port: 5173,
      proxy: {
        // dev: เรียก /api/* บน localhost → proxy ไป cloud (เลี่ยง CORS)
        '/api': {
          target: CLOUD_API_TARGET,
          changeOrigin: true,
          secure: true,
        },
      },
    },
  }
})
