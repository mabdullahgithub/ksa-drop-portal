/**
 * What this browser can say about itself, sent along with a message and kept
 * for the team's message details (App\Services\Chat\ChatOrigin). Most of it
 * exists only in some browsers; whatever is missing is left out. No
 * permission prompt: precise location is never asked for.
 */
export async function clientInfo(): Promise<string> {
  const nav = navigator as Navigator & {
    userAgentData?: {
      platform?: string
      brands?: Array<{ brand: string; version: string }>
      getHighEntropyValues?: (hints: string[]) => Promise<{ platformVersion?: string; model?: string; fullVersionList?: Array<{ brand: string; version: string }> }>
    }
    connection?: { effectiveType?: string; type?: string; downlink?: number; rtt?: number }
  }

  // The user agent string is frozen in Chrome (every Mac claims 10.15.7); these are the real versions.
  const detail = await nav.userAgentData?.getHighEntropyValues?.(['platformVersion', 'model', 'fullVersionList']).catch(() => undefined)
  const brands = (detail?.fullVersionList ?? nav.userAgentData?.brands)
    ?.filter((entry) => !/not.?a.?brand/i.test(entry.brand))
    .map((entry) => `${entry.brand} ${entry.version}`)
    .join(', ')

  const info = {
    platform: [nav.userAgentData?.platform ?? nav.platform, detail?.platformVersion].filter(Boolean).join(' '),
    model: detail?.model,
    brands,
    language: nav.language,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    screen: `${window.screen.width}×${window.screen.height} @${window.devicePixelRatio}x`,
    viewport: `${window.innerWidth}×${window.innerHeight}`,
    connection: nav.connection?.effectiveType,
    connection_type: nav.connection?.type,
    downlink: nav.connection?.downlink != null ? `${nav.connection.downlink} Mbps` : undefined,
    rtt: nav.connection?.rtt != null ? `${nav.connection.rtt} ms` : undefined,
  }

  return JSON.stringify(Object.fromEntries(Object.entries(info).filter(([, value]) => value)))
}
