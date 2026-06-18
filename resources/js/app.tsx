import '../css/app.css'

import { createInertiaApp } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createRoot } from 'react-dom/client'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

// After a deploy, asset chunk hashes change. A tab still running the old build
// will fail to lazy-load a page chunk that no longer exists, which otherwise
// leaves a blank white page. Vite fires `vite:preloadError` in that case — force
// a one-time hard reload to fetch the new manifest. The guard prevents a reload
// loop if the asset is genuinely unreachable.
const CHUNK_RELOAD_KEY = 'chunk_reload_at'
window.addEventListener('vite:preloadError', (event) => {
  const last = Number(sessionStorage.getItem(CHUNK_RELOAD_KEY) || 0)
  if (Date.now() - last > 10_000) {
    event.preventDefault()
    sessionStorage.setItem(CHUNK_RELOAD_KEY, String(Date.now()))
    window.location.reload()
  }
})

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx')
    ),
  setup({ el, App, props }) {
    const root = createRoot(el)
    root.render(<App {...props} />)
  },
  progress: {
    color: '#4B5563',
  },
})
