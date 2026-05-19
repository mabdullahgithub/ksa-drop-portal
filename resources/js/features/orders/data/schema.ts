import { z } from 'zod'

export const orderSchema = z.object({
  id: z.string(),
  title: z.string(),
  status: z.string(),
  label: z.string(),
  priority: z.string(),
  customer: z.string(),
  total: z.number(),
})

export type Order = z.infer<typeof orderSchema>
