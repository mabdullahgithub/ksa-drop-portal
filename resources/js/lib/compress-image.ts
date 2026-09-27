/**
 * Shrink a photo before upload: longest side `maxSide`px, JPEG at `quality`.
 * A 4 MB phone photo becomes ~150–250 KB at the defaults, which matters on
 * mobile data and on shared hosting disk. Also turns HEIC/PNG into JPEG.
 */
export async function compressImage(file: File, maxSide = 1280, quality = 0.7): Promise<Blob> {
  const bitmap = await loadImage(file)
  const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height))
  const width = Math.round(bitmap.width * scale)
  const height = Math.round(bitmap.height * scale)

  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  canvas.getContext('2d')!.drawImage(bitmap, 0, 0, width, height)

  if ('close' in bitmap) (bitmap as ImageBitmap).close()

  const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality))
  return blob ?? file
}

async function loadImage(file: File): Promise<ImageBitmap | HTMLImageElement> {
  if ('createImageBitmap' in window) {
    try {
      // Honours the photo's EXIF rotation, so portrait shots stay upright.
      return await createImageBitmap(file, { imageOrientation: 'from-image' })
    } catch {
      // Older Safari: fall back to an <img>.
    }
  }

  return new Promise((resolve, reject) => {
    const img = new Image()
    img.onload = () => resolve(img)
    img.onerror = reject
    img.src = URL.createObjectURL(file)
  })
}
