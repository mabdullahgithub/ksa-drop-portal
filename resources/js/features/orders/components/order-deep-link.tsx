import { useEffect, useRef } from 'react'
import axios from 'axios'
import { toast } from 'sonner'
import { useOrdersContext } from './orders-provider'

/**
 * Opens one order's details dialog straight from the URL: `/orders?order=KSA-1042`.
 *
 * This is what makes "View full order" in the WhatsApp inbox land somewhere
 * useful instead of dumping the agent on an unfiltered list. Renders nothing;
 * it exists purely for the effect, and must sit inside OrdersProvider.
 */
export function OrderDeepLink() {
  const { setCurrentRow, setOpen } = useOrdersContext()
  // A dialog opened once shouldn't reopen when the user closes it and the
  // component re-renders with the param still in the URL.
  const handled = useRef(false)

  useEffect(() => {
    if (handled.current) return

    const orderNumber = new URLSearchParams(window.location.search).get('order')
    if (!orderNumber) return

    handled.current = true

    const open = async () => {
      try {
        const res = await axios.get('/api/orders', {
          params: { search: orderNumber, per_page: 5 },
        })
        const results = res.data?.data ?? []
        // `search` is a LIKE across several columns, so match the exact order
        // number rather than trusting the first row back.
        const match = results.find((o: any) => o.order_number === orderNumber) ?? null

        if (!match) {
          toast.error(`Order ${orderNumber} not found`)
          return
        }

        setCurrentRow(match)
        setOpen('view')
      } catch {
        toast.error('Could not open that order')
      }
    }

    open()
  }, [setCurrentRow, setOpen])

  return null
}
