import zxingWasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url'

/**
 * Barcode reading for the scanner.
 *
 * Android Chrome has a built-in BarcodeDetector — fast and free. iPhone
 * Safari doesn't, so it gets the zxing WebAssembly reader instead, loaded
 * only when needed and served from our own build (the package would
 * otherwise fetch it from a public CDN).
 *
 * Waybills carry the tracking number as Code 128, the order number as a
 * smaller Code 128, and a QR code of the /track?q=… link.
 */

export type Detector = {
  detect(source: HTMLVideoElement): Promise<Array<{ rawValue: string }>>
}

const FORMATS = ['code_128', 'qr_code']

export async function createDetector(): Promise<Detector> {
  const Native = (window as unknown as { BarcodeDetector?: any }).BarcodeDetector

  if (Native) {
    try {
      const supported: string[] = await Native.getSupportedFormats()
      if (FORMATS.every((format) => supported.includes(format))) {
        return new Native({ formats: FORMATS })
      }
    } catch {
      // Fall through to the WebAssembly reader.
    }
  }

  const { BarcodeDetector, prepareZXingModule } = await import('barcode-detector/ponyfill')

  prepareZXingModule({
    overrides: {
      locateFile: (path: string, prefix: string) => (path.endsWith('.wasm') ? zxingWasmUrl : prefix + path),
    },
  })

  return new BarcodeDetector({ formats: ['code_128', 'qr_code'] })
}

/**
 * The label's QR code is a tracking URL (…/track?q=KSD…): keep the number.
 * Hardware scanners can add whitespace or a trailing newline.
 */
export function normalizeScannedCode(raw: string): string {
  const value = raw.trim()

  if (/^https?:\/\//i.test(value)) {
    try {
      const q = new URL(value).searchParams.get('q')
      if (q) return q.trim()
    } catch {
      // Not a URL after all.
    }
  }

  return value
}
