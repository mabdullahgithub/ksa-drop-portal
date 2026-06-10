import { z } from 'zod'

export const tagSchema = z.object({
  id: z.number(),
  name: z.string(),
  color: z.string(),
  description: z.string().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Tag = z.infer<typeof tagSchema>

export const PREDEFINED_COLORS = [
  { label: 'Indigo',  value: '#6366f1' },
  { label: 'Blue',    value: '#3b82f6' },
  { label: 'Sky',     value: '#0ea5e9' },
  { label: 'Teal',    value: '#14b8a6' },
  { label: 'Green',   value: '#22c55e' },
  { label: 'Lime',    value: '#84cc16' },
  { label: 'Yellow',  value: '#eab308' },
  { label: 'Orange',  value: '#f97316' },
  { label: 'Red',     value: '#ef4444' },
  { label: 'Pink',    value: '#ec4899' },
  { label: 'Purple',  value: '#a855f7' },
  { label: 'Slate',   value: '#64748b' },
]
