import { z } from 'zod'

export const orderSchema = z.object({
  id: z.number(),
  order_number: z.string(),
  customer_name: z.string().nullable(),
  customer_email: z.string().nullable(),
  customer_phone: z.string().nullable(),
  total: z.string(),
  currency: z.string(),
  fulfillment_status: z.enum(['pending', 'unfulfilled', 'fulfilled', 'cancelled']),
  financial_status: z.enum(['pending', 'paid', 'refunded', 'partially_refunded']),
  payment_method: z.string().nullable(),
  shipping_country: z.string().nullable(),
  status_color: z.enum(['success', 'warning', 'info', 'error', 'default']),
  financial_status_color: z.enum(['success', 'warning', 'info', 'error', 'default']),
  formatted_total: z.string(),
  created_at: z.string(),
  utm_source: z.string().nullable(),
  risk_level: z.string().nullable(),
})

export type OrderTable = z.infer<typeof orderSchema>
