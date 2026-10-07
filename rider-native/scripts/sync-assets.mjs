/**
 * Copy the rider app's icons and welcome clips from the portal's public/
 * folder, so there is one copy to edit. Only the files the app shows: the
 * source masters next to them are far too big to ship in a phone app.
 */
import { cpSync, existsSync, mkdirSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const from = resolve(here, '../../public/rider-icons')
const to = resolve(here, '../public/rider-icons')

const clip = (name) => [`${name}.webm`, `${name}-hevc.mov`, `${name}-poster.webp`]

const files = [
    'icon-192.png',
    'icon-512.png',
    ...clip('rider-welcome-animated-icon/rider-welcome'),
    ...clip('manager-animated-icon/manager-welcome'),
]

const missing = files.filter((file) => !existsSync(resolve(from, file)))
if (missing.length) {
    console.error(`Missing in ${from}:\n  ${missing.join('\n  ')}`)
    process.exit(1)
}

for (const file of files) {
    mkdirSync(dirname(resolve(to, file)), { recursive: true })
    cpSync(resolve(from, file), resolve(to, file))
}

console.log(`Copied ${files.length} rider app files into public/rider-icons`)
