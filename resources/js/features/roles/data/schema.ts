import { z } from 'zod'

export const roleSchema = z.object({
  id: z.number(),
  name: z.string(),
  permissions: z.array(z.string()),
  users_count: z.number(),
  is_super_admin: z.boolean(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Role = z.infer<typeof roleSchema>
