import { businessToday } from '@/lib/business-time'
import type { HistoryDates, HistoryRange } from '../types'

/**
 * Calendar days in KSA as yyyy-MM-dd — what a date input holds and what the
 * server reads (GET /rider/api/history?from=&to=).
 */

/** Longest period the server answers (RiderPerformance::MAX_DAYS). */
export const MAX_DAYS = 92

const DAY_MS = 86_400_000

const pad = (n: number) => String(n).padStart(2, '0')

export function today(): string {
  const now = businessToday()
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

export const addDays = (day: string, n: number) => new Date(Date.parse(day) + n * DAY_MS).toISOString().slice(0, 10)

/** Days in a period, both ends counted. */
export const daysBetween = (from: string, to: string) => Math.round((Date.parse(to) - Date.parse(from)) / DAY_MS) + 1

/** The dates a period covers: the rider's own, or what a ready-made one comes to today. */
export function periodDates(range: HistoryRange, custom: HistoryDates | null): HistoryDates {
  if (range === 'custom' && custom) return custom
  const to = today()
  if (range === 'yesterday') return { from: addDays(to, -1), to: addDays(to, -1) }
  return { from: range === 'week' ? addDays(to, -6) : to, to }
}
