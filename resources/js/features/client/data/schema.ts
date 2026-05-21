import { z } from 'zod'

export const clientSchema = z.object({
  id: z.number(),
  company_name: z.string(),
  client_id: z.string(),
  contact_person: z.string().nullable(),
  phone: z.string().nullable(),
  client_types: z.array(z.enum(['dropshipper', 'fulfilment'])),
  status: z.enum(['active', 'inactive', 'suspended']),
  status_color: z.enum(['success', 'warning', 'error', 'default']),
  type_label: z.string(),
  portal_features: z.array(z.enum(['orders', 'inventory', 'revenue'])),
  user: z.object({
    id: z.number(),
    name: z.string(),
    email: z.string(),
  }).optional(),
  orders_count: z.number().optional(),
  created_at: z.string(),
})

export type ClientTable = z.infer<typeof clientSchema>

export const createClientSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters'),
  email: z.string().email('Invalid email address'),
  client_types: z.array(z.enum(['dropshipper', 'fulfilment'])).min(1, 'Select at least one type'),
  company_name: z.string().min(2, 'Company name is required'),
  client_id: z.string().min(2).max(6).regex(/^[A-Z0-9]+$/, 'Must be uppercase alphanumeric').optional().or(z.literal('')),
  contact_person: z.string().optional(),
  phone: z.string().optional(),
  secondary_phone: z.string().optional(),
  address: z.string().optional(),
  city: z.string().optional(),
  country: z.string().max(2).optional(),
  postal_code: z.string().optional(),
  tax_id: z.string().optional(),
  commercial_registration: z.string().optional(),
  portal_features: z.array(z.enum(['orders', 'inventory', 'revenue'])),
  notes: z.string().optional(),
})

export type CreateClientFormValues = z.infer<typeof createClientSchema>
