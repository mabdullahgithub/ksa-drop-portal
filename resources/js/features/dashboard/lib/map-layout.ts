/**
 * Layout for the city heatmap: keeps neighbouring dots from sitting on top of
 * each other (Dammam, Khobar, Dhahran and Qatif are a few pixels apart) and
 * finds each label a spot clear of the dots and of the other labels.
 */

export type Bubble = { id: string; x: number; y: number; r: number }
export type Placed = Bubble & { px: number; py: number }

/** How close to the coast a dot's centre may sit: all but the biggest dots stay wholly on land. */
const COAST_MARGIN = 10

/**
 * The land the dots must stay on. `keepInside` returns the point itself when
 * it is at least `margin` inside the outline, otherwise the nearest point that is.
 */
export function landOf(outline: Array<[number, number]>) {
  const n = outline.length

  const contains = (x: number, y: number) => {
    let inside = false
    for (let i = 0, j = n - 1; i < n; j = i++) {
      const [xi, yi] = outline[i]
      const [xj, yj] = outline[j]
      if (yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) inside = !inside
    }
    return inside
  }

  /** The nearest point on the outline, and the direction pointing inland from it. */
  const nearestEdge = (x: number, y: number) => {
    let best = { x, y, d: Infinity, nx: 0, ny: 0 }
    for (let i = 0; i < n; i++) {
      const [ax, ay] = outline[i]
      const [bx, by] = outline[(i + 1) % n]
      const dx = bx - ax
      const dy = by - ay
      const len2 = dx * dx + dy * dy || 1
      const t = Math.max(0, Math.min(1, ((x - ax) * dx + (y - ay) * dy) / len2))
      const px = ax + t * dx
      const py = ay + t * dy
      const d = Math.hypot(x - px, y - py)
      if (d < best.d) {
        const len = Math.sqrt(len2)
        best = { x: px, y: py, d, nx: -dy / len, ny: dx / len }
      }
    }
    // Of the edge's two normals, the one that points onto land.
    if (!contains(best.x + best.nx * 0.5, best.y + best.ny * 0.5)) {
      best.nx = -best.nx
      best.ny = -best.ny
    }
    return best
  }

  const keepInside = (x: number, y: number, margin: number): [number, number] => {
    if (contains(x, y) && nearestEdge(x, y).d >= margin) return [x, y]
    // Step in from the coast; a concave corner can need a second step.
    let px = x
    let py = y
    for (let k = 0; k < 3; k++) {
      const e = nearestEdge(px, py)
      px = e.x + e.nx * margin
      py = e.y + e.ny * margin
      if (contains(px, py) && nearestEdge(px, py).d >= margin - 0.5) break
    }
    return [px, py]
  }

  return { contains, keepInside }
}

/**
 * Pushes overlapping dots apart, a little at a time. The smaller dot of a pair
 * moves most, a weak spring pulls every dot back towards its true location,
 * and no dot strays further than `maxShift` from it — so the map stays honest
 * and a hairline can show where a moved dot really is. With `land`, every dot
 * also stays on it: a crowded coast spreads inland, never out to sea.
 */
export function separate(
  input: Bubble[],
  { gap = 2, iterations = 120, maxShift = 30, land }: { gap?: number; iterations?: number; maxShift?: number; land?: ReturnType<typeof landOf> } = {}
): Placed[] {
  const marginOf = (b: Bubble) => Math.min(b.r, COAST_MARGIN)
  // A town whose coordinates fall just off the simplified coastline starts on land.
  const bubbles = land
    ? input.map((b) => {
        const [x, y] = land.keepInside(b.x, b.y, marginOf(b))
        return { ...b, x, y }
      })
    : input
  const pos = bubbles.map((b) => ({ x: b.x, y: b.y }))

  for (let it = 0; it < iterations; it++) {
    for (let i = 0; i < bubbles.length; i++) {
      for (let j = i + 1; j < bubbles.length; j++) {
        const a = bubbles[i]
        const b = bubbles[j]
        let dx = pos[j].x - pos[i].x
        let dy = pos[j].y - pos[i].y
        let d = Math.hypot(dx, dy)
        const min = a.r + b.r + gap
        if (d >= min) continue

        // Two cities on the very same spot: part them along a fixed angle.
        if (d < 0.01) {
          const angle = (j * 2.399) % (Math.PI * 2)
          dx = Math.cos(angle)
          dy = Math.sin(angle)
          d = 1
        }

        const push = (min - d) / 2
        const ux = dx / d
        const uy = dy / d
        // Weighted by area: a big city barely moves, a small one gets out of the way.
        const wa = (b.r * b.r) / (a.r * a.r + b.r * b.r)
        pos[i].x -= ux * push * wa * 2
        pos[i].y -= uy * push * wa * 2
        pos[j].x += ux * push * (1 - wa) * 2
        pos[j].y += uy * push * (1 - wa) * 2
      }
    }

    for (let i = 0; i < bubbles.length; i++) {
      const b = bubbles[i]
      pos[i].x += (b.x - pos[i].x) * 0.015
      pos[i].y += (b.y - pos[i].y) * 0.015

      const sx = pos[i].x - b.x
      const sy = pos[i].y - b.y
      const shift = Math.hypot(sx, sy)
      if (shift > maxShift) {
        pos[i].x = b.x + (sx / shift) * maxShift
        pos[i].y = b.y + (sy / shift) * maxShift
      }

      if (land) {
        const [x, y] = land.keepInside(pos[i].x, pos[i].y, marginOf(b))
        pos[i].x = x
        pos[i].y = y
      }
    }
  }

  return bubbles.map((b, i) => ({ ...b, px: pos[i].x, py: pos[i].y }))
}

export type Label = {
  id: string
  /** Top-left of the label box. */
  x: number
  y: number
  w: number
  h: number
  /** Leader line from the dot's edge to the label, when the label sits away from it. */
  leader: { x1: number; y1: number; x2: number; y2: number } | null
}

type Rect = { x: number; y: number; w: number; h: number }

function rectsOverlap(a: Rect, b: Rect, pad = 2) {
  return a.x < b.x + b.w + pad && a.x + a.w + pad > b.x && a.y < b.y + b.h + pad && a.y + a.h + pad > b.y
}

function rectCircleOverlap(r: Rect, cx: number, cy: number, radius: number) {
  const nx = Math.max(r.x, Math.min(cx, r.x + r.w))
  const ny = Math.max(r.y, Math.min(cy, r.y + r.h))
  return (cx - nx) ** 2 + (cy - ny) ** 2 < (radius + 1.5) ** 2
}

/**
 * Places each label (in the order given — most important first) at the first
 * candidate spot that is inside the canvas and clear of every dot and every
 * label already placed. Nearer spots are tried before farther ones; when
 * nothing is clear, the spot with the least overlap wins.
 */
export function placeLabels(
  targets: Array<Placed & { width: number }>,
  dots: Placed[],
  bounds: { width: number; height: number },
  height = 16
): Label[] {
  const placed: Label[] = []

  for (const t of targets) {
    const w = t.width
    const h = height
    const { px: cx, py: cy, r } = t
    const candidates: Rect[] = []

    for (const d of [5, 14, 26, 40, 56]) {
      const diag = (r + d) * 0.72
      candidates.push(
        { x: cx + r + d, y: cy - h / 2, w, h }, // right
        { x: cx - r - d - w, y: cy - h / 2, w, h }, // left
        { x: cx + diag, y: cy - diag - h, w, h }, // up-right
        { x: cx + diag, y: cy + diag, w, h }, // down-right
        { x: cx - diag - w, y: cy - diag - h, w, h }, // up-left
        { x: cx - diag - w, y: cy + diag, w, h }, // down-left
        { x: cx - w / 2, y: cy - r - d - h, w, h }, // above
        { x: cx - w / 2, y: cy + r + d, w, h } // below
      )
    }

    let best: Rect | null = null
    let bestScore = Infinity

    for (const c of candidates) {
      if (c.x < 2 || c.y < 2 || c.x + c.w > bounds.width - 2 || c.y + c.h > bounds.height - 2) continue

      let score = 0
      for (const l of placed) if (rectsOverlap(c, l)) score += 100
      for (const dot of dots) if (rectCircleOverlap(c, dot.px, dot.py, dot.r)) score += dot.id === t.id ? 50 : 10 + dot.r

      if (score === 0) {
        best = c
        break
      }
      if (score < bestScore) {
        bestScore = score
        best = c
      }
    }

    if (!best) continue

    // A leader line once the label has moved off the dot's edge.
    const lx = Math.max(best.x, Math.min(cx, best.x + best.w))
    const ly = Math.max(best.y, Math.min(cy, best.y + best.h))
    const gap = Math.hypot(lx - cx, ly - cy) - r
    let leader: Label['leader'] = null
    if (gap > 9) {
      const angle = Math.atan2(ly - cy, lx - cx)
      leader = {
        x1: cx + Math.cos(angle) * (r + 2),
        y1: cy + Math.sin(angle) * (r + 2),
        x2: lx - Math.cos(angle) * 3,
        y2: ly - Math.sin(angle) * 3,
      }
    }

    placed.push({ id: t.id, ...best, leader })
  }

  return placed
}

/** A label's width in canvas units, near enough for 12px semibold text. */
export function labelWidth(name: string, count: string) {
  return name.length * 6.9 + count.length * 6.3 + 10
}
