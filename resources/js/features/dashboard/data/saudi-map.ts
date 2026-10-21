/**
 * A simplified outline of Saudi Arabia and the coordinates of the cities
 * orders ship to, projected onto one SVG canvas for the dashboard heatmap.
 *
 * Equirectangular with the longitude squeezed by cos(24.5°), the country's
 * middle latitude, so the shape is not stretched sideways. Accurate to a few
 * kilometres — plenty for a dot map, far too coarse for routing.
 */

const LON_MIN = 34.3
const LAT_MAX = 32.4
const LON_SCALE = Math.cos((24.5 * Math.PI) / 180)
const SCALE = 40
const PAD = 14

export const MAP_WIDTH = Math.round((55.9 - LON_MIN) * LON_SCALE * SCALE + PAD * 2)
export const MAP_HEIGHT = Math.round((LAT_MAX - 16.2) * SCALE + PAD * 2)

export function project(lat: number, lon: number): [number, number] {
  return [(lon - LON_MIN) * LON_SCALE * SCALE + PAD, (LAT_MAX - lat) * SCALE + PAD]
}

/** [lon, lat], clockwise from the Gulf of Aqaba. */
const OUTLINE: Array<[number, number]> = [
  [34.96, 29.36], [36.07, 29.19], [36.5, 29.5], [37.5, 29.95], [38.0, 30.5],
  [37.0, 31.5], [39.0, 32.0], [39.2, 32.16], [40.4, 31.89], [42.0, 31.1],
  [44.7, 29.18], [46.57, 29.1], [47.46, 29.0], [47.71, 28.53], [48.42, 28.55],
  [48.81, 27.69], [49.3, 27.46], [49.47, 27.11], [50.15, 26.69], [50.21, 26.28],
  [50.11, 25.94], [50.24, 25.61], [50.53, 25.33], [50.66, 24.99], [50.81, 24.75],
  [51.11, 24.56], [51.39, 24.63], [51.58, 24.25], [51.62, 24.01], [52.0, 23.0],
  [55.01, 22.5], [55.21, 22.71], [55.67, 22.0], [55.0, 20.0], [52.0, 19.0],
  [49.12, 18.62], [48.18, 18.17], [47.47, 17.12], [47.0, 16.95], [46.75, 17.28],
  [46.37, 17.23], [45.4, 17.33], [45.22, 17.43], [44.06, 17.41], [43.79, 17.32],
  [43.38, 17.58], [43.12, 17.09], [43.22, 16.67], [42.78, 16.35], [42.65, 16.77],
  [42.35, 17.08], [42.27, 17.47], [41.75, 17.83], [41.22, 18.67], [40.94, 19.49],
  [40.25, 20.17], [39.8, 20.34], [39.14, 21.29], [39.02, 22.58], [38.49, 23.69],
  [38.02, 24.08], [37.48, 24.29], [37.15, 24.86], [37.21, 25.08], [36.93, 25.6],
  [36.64, 25.83], [36.25, 26.57], [35.64, 27.38], [35.13, 28.06], [34.63, 28.06],
  [34.79, 28.61], [34.83, 28.96],
]

/** The outline on the canvas, for drawing and for keeping dots on land. */
export const COUNTRY_POINTS: Array<[number, number]> = OUTLINE.map(([lon, lat]) => project(lat, lon))

export const COUNTRY_PATH =
  COUNTRY_POINTS.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`).join('') + 'Z'

/** Parallels drawn faintly behind the country, every two degrees. */
export const PARALLELS = [18, 20, 22, 24, 26, 28, 30, 32].map((lat) => ({
  lat,
  y: project(lat, LON_MIN)[1],
}))

/** [lat, lon] by city name, as CityDirectory names them. */
const CITIES: Record<string, [number, number]> = {
  Riyadh: [24.71, 46.68],
  Jeddah: [21.49, 39.19],
  Makkah: [21.39, 39.86],
  Madinah: [24.47, 39.61],
  Dammam: [26.43, 50.1],
  Khobar: [26.28, 50.21],
  Dhahran: [26.29, 50.11],
  Taif: [21.27, 40.42],
  Tabuk: [28.38, 36.57],
  Buraydah: [26.33, 43.97],
  Unaizah: [26.08, 43.99],
  'Khamis Mushait': [18.31, 42.73],
  Abha: [18.22, 42.51],
  Hail: [27.52, 41.69],
  'Hafar Al Batin': [28.43, 45.96],
  Jubail: [27.0, 49.66],
  'Al Kharj': [24.16, 47.31],
  Qatif: [26.56, 49.99],
  'Al Ahsa': [25.38, 49.59],
  Hofuf: [25.36, 49.57],
  Mubarraz: [25.41, 49.58],
  Najran: [17.49, 44.13],
  Jazan: [16.89, 42.55],
  Yanbu: [24.09, 38.06],
  Sakaka: [29.97, 40.21],
  Arar: [30.98, 41.04],
  'Ar Rass': [25.87, 43.5],
  'Al Bahah': [20.01, 41.47],
  Bisha: [19.98, 42.6],
  'Al Qurayyat': [31.33, 37.34],
  Diriyah: [24.73, 46.57],
  'Al Majmaah': [25.9, 45.35],
  'Az Zulfi': [26.3, 44.8],
  'Ad Dawadmi': [24.51, 44.39],
  Rabigh: [22.8, 39.03],
  'Al Ula': [26.61, 37.92],
  'Ras Tanura': [26.64, 50.16],
  Abqaiq: [25.93, 49.67],
  Khafji: [28.44, 48.49],
  Sabya: [17.15, 42.63],
  'Abu Arish': [16.97, 42.83],
  Samtah: [16.6, 42.94],
  Qunfudhah: [19.13, 41.08],
  Lith: [20.15, 40.27],
  'Wadi Ad Dawasir': [20.47, 44.78],
  Sharurah: [17.47, 47.11],
  Turaif: [31.67, 38.66],
  Duba: [27.35, 35.69],
  Umluj: [25.02, 37.27],
  Wajh: [26.24, 36.47],
  Rafha: [29.63, 43.49],
  Muhayil: [18.55, 42.05],
  Namas: [19.12, 42.13],
  'Ahad Rafidah': [18.21, 42.85],
  Afif: [23.91, 42.92],
  Khurma: [21.92, 42.04],
  Ranyah: [21.26, 42.85],
  Badr: [23.78, 38.79],
  Khulais: [22.15, 39.32],
  Jumum: [21.62, 39.7],
  Tayma: [27.63, 38.55],
  Mithnab: [25.86, 44.22],
  Bukayriyah: [26.14, 43.66],
  Badai: [25.99, 43.73],
  'Dumat Al Jandal': [29.81, 39.87],
  Shaqra: [25.25, 45.25],
  Qassim: [26.33, 43.97],
  Khaybar: [25.7, 39.29],
  Hotat: [23.49, 46.85],
  Muzahmiyya: [24.47, 46.26],
  Quwayiyah: [24.07, 45.28],
  Sulayyil: [20.46, 45.58],
  'Al Aflaj': [22.29, 46.73],
  Dhurma: [24.6, 46.13],
  Thadiq: [25.29, 45.87],
  Huraymila: [25.12, 46.12],
  Rumah: [25.57, 47.16],
  'Sarat Abidah': [18.17, 43.11],
  Tanomah: [18.92, 42.17],
  'Rijal Almaa': [18.21, 42.28],
  'Bariq': [18.93, 41.93],
  Mahd: [23.5, 40.86],
  Hanakiyah: [24.86, 40.5],
  Uqlat: [26.0, 42.67],
  'Al Nairyah': [27.47, 48.48],
  Safwa: [26.65, 49.95],
  Saihat: [26.48, 50.04],
  Tarout: [26.57, 50.07],
  'Ras Al Khair': [27.5, 49.24],
  Baljurashi: [19.86, 41.56],
  Mukhwah: [19.77, 41.43],
  Dawadmi: [24.51, 44.39],
}

/** "Al-Khobar" / "al khobar" / "Khobar" all become "khobar". */
function cityKey(name: string) {
  return name
    .toLowerCase()
    .replace(/['’`]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim()
    .replace(/^(al|el|ar|ad|as|at|az|an|ash) /, '')
}

const BY_KEY = new Map(Object.entries(CITIES).map(([name, coords]) => [cityKey(name), coords]))

/** The SVG point of a city, or null for one we have no coordinates for. */
export function cityPoint(name: string): [number, number] | null {
  const coords = BY_KEY.get(cityKey(name))
  return coords ? project(coords[0], coords[1]) : null
}

export type Region = 'central' | 'western' | 'eastern' | 'southern' | 'northern'

/** The five broad regions orders are grouped into, in the palette's validated order. */
export const REGIONS: Array<{ key: Region; label: string; color: string }> = [
  { key: 'central', label: 'Central', color: 'var(--viz-r1)' },
  { key: 'western', label: 'Western', color: 'var(--viz-r2)' },
  { key: 'eastern', label: 'Eastern', color: 'var(--viz-r3)' },
  { key: 'southern', label: 'Southern', color: 'var(--viz-r4)' },
  { key: 'northern', label: 'Northern', color: 'var(--viz-r5)' },
]

const REGION_CITIES: Record<Region, string[]> = {
  central: [
    'Riyadh', 'Diriyah', 'Al Kharj', 'Al Majmaah', 'Az Zulfi', 'Ad Dawadmi', 'Dawadmi', 'Buraydah', 'Unaizah',
    'Ar Rass', 'Mithnab', 'Bukayriyah', 'Badai', 'Shaqra', 'Afif', 'Hotat', 'Muzahmiyya', 'Quwayiyah', 'Sulayyil',
    'Al Aflaj', 'Dhurma', 'Thadiq', 'Huraymila', 'Rumah', 'Wadi Ad Dawasir', 'Uqlat', 'Qassim',
  ],
  western: [
    'Jeddah', 'Makkah', 'Madinah', 'Taif', 'Yanbu', 'Rabigh', 'Al Ula', 'Qunfudhah', 'Lith', 'Khurma', 'Ranyah',
    'Badr', 'Khulais', 'Jumum', 'Khaybar', 'Mahd', 'Hanakiyah',
  ],
  eastern: [
    'Dammam', 'Khobar', 'Dhahran', 'Jubail', 'Qatif', 'Al Ahsa', 'Hofuf', 'Mubarraz', 'Ras Tanura', 'Abqaiq',
    'Khafji', 'Hafar Al Batin', 'Al Nairyah', 'Safwa', 'Saihat', 'Tarout', 'Ras Al Khair',
  ],
  southern: [
    'Khamis Mushait', 'Abha', 'Najran', 'Jazan', 'Al Bahah', 'Bisha', 'Sabya', 'Abu Arish', 'Samtah', 'Sharurah',
    'Muhayil', 'Namas', 'Ahad Rafidah', 'Sarat Abidah', 'Tanomah', 'Rijal Almaa', 'Bariq', 'Baljurashi', 'Mukhwah',
  ],
  northern: [
    'Tabuk', 'Hail', 'Sakaka', 'Arar', 'Al Qurayyat', 'Turaif', 'Duba', 'Umluj', 'Wajh', 'Rafha', 'Tayma',
    'Dumat Al Jandal',
  ],
}

const REGION_BY_KEY = new Map(
  (Object.entries(REGION_CITIES) as Array<[Region, string[]]>).flatMap(([region, names]) =>
    names.map((name) => [cityKey(name), region] as const)
  )
)

/** The region a city sits in, or null for one we cannot place. */
export function cityRegion(name: string): Region | null {
  return REGION_BY_KEY.get(cityKey(name)) ?? null
}
