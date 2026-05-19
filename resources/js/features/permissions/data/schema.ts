import { z } from 'zod'

export const categorySchema = z.object({
  id: z.number(),
  name: z.string(),
  label: z.string(),
  order: z.number(),
}).nullable()

export const permissionSchema = z.object({
  id: z.number(),
  name: z.string(),
  roles: z.array(z.string()),
  category_id: z.number().nullable(),
  category: categorySchema,
  created_at: z.string(),
  updated_at: z.string(),
})

export type Permission = z.infer<typeof permissionSchema>
export type Category = z.infer<typeof categorySchema>
