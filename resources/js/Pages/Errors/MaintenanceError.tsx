import { Head, Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'

export default function MaintenanceError() {
  return (
    <div className='flex min-h-svh flex-col items-center justify-center gap-4'>
      <Head title='503 - Maintenance' />
      <h1 className='text-7xl font-bold'>503</h1>
      <p className='text-lg text-muted-foreground'>Under Maintenance</p>
      <p className='text-sm text-muted-foreground'>We are performing scheduled maintenance. Please check back soon.</p>
      <Button asChild>
        <Link href='/'>Go Home</Link>
      </Button>
    </div>
  )
}
