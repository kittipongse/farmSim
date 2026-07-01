import { createApp } from 'vue'
import { createPinia } from 'pinia'
import piniaPersistPlugin from './plugins/piniaPersist'
import App from './App.vue'
import router from './router'
import { assetUrl } from '@/utils/paths'

import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'
import './assets/main.css'

document.documentElement.style.setProperty(
  '--fs-bg-image',
  `url('${assetUrl('resource/images/bg1.png')}')`
)
document.documentElement.style.setProperty(
  '--cardgame-image',
  `url('${assetUrl('resource/images/cardgame.png')}')`
)

const app = createApp(App)
const pinia = createPinia()
pinia.use(piniaPersistPlugin)

app.use(pinia)
app.use(router)
app.mount('#app')
