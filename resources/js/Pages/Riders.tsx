import { Head, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Riders as RidersFeature } from '@/features/riders'
import type { RiderRow, RiderSupportContact, WarehouseOption } from '@/features/riders/data/types'
import type { PageProps } from '@/types'

type Props = PageProps<{ riders: RiderRow[]; warehouses: WarehouseOption[]; support: RiderSupportContact | null }>

export default function Riders() {
  const { riders, warehouses, support } = usePage<Props>().props

  return (
    <AuthenticatedLayout>
      <Head title='Riders' />
      <RidersFeature riders={riders} warehouses={warehouses} support={support} />
    </AuthenticatedLayout>
  )
}
