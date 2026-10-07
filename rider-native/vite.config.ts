import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'
import { nativephpHotFile, nativephpMobile } from './vendor/nativephp/mobile/resources/js/vite-plugin.js'

/**
 * The rider screens are the portal's own (../resources/js/features/rider-app):
 * `@` points there, so the web app and the phone app are one copy of the code.
 * React, Tailwind and Vite also come from the portal's node_modules, one
 * folder up — the only package installed here is axios, which NativePHP's
 * plugin insists on.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/native-app.tsx'],
            hotFile: nativephpHotFile(),
            refresh: true,
        }),
        react(),
        tailwindcss(),
        nativephpMobile(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, '../resources/js'),
        },
    },
    server: {
        fs: { allow: ['..'] },
    },
})
