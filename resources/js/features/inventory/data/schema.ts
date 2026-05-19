import { z } from 'zod'

export const productSchema = z.object({
  id: z.number(),
  handle: z.string(),
  title: z.string(),
  vendor: z.string().nullable(),
  type: z.string().nullable(),
  variant_sku: z.string().nullable(),
  variant_price: z.string().nullable(),
  variant_compare_at_price: z.string().nullable(),
  variant_inventory_qty: z.number(),
  status: z.enum(['active', 'draft', 'archived']),
  published: z.boolean(),
  primary_image: z.string().nullable(),
  created_at: z.string(),
})

export type ProductTable = z.infer<typeof productSchema>
