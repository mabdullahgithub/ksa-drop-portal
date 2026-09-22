/**
 * The one clock every viewer shares, matching `app.business_timezone` on the
 * server. Order dates and date filters use it whatever the device's timezone.
 */
export const BUSINESS_TIMEZONE = 'Asia/Riyadh'
export const BUSINESS_TIMEZONE_LABEL = 'KSA time (GMT+3)'

const partsFormatter = new Intl.DateTimeFormat('en-US', {
  timeZone: BUSINESS_TIMEZONE,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hourCycle: 'h23',
})

/**
 * Returns a Date whose local fields show the business-timezone wall clock, so
 * date-fns `format()` and friends print KSA time on any device. Only use the
 * result for display, never for sending back to the server.
 */
export function toBusinessTime(value: string | number | Date): Date {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return date

  const parts: Record<string, number> = {}
  for (const { type, value: v } of partsFormatter.formatToParts(date)) {
    if (type !== 'literal') parts[type] = Number(v)
  }

  return new Date(
    parts.year,
    parts.month - 1,
    parts.day,
    parts.hour,
    parts.minute,
    parts.second,
    date.getMilliseconds()
  )
}

/** Today's date in the business timezone. */
export const businessToday = () => toBusinessTime(new Date())
