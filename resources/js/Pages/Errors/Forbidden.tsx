import { Head, Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'

export default function Forbidden() {
  return (
    <div className='flex min-h-svh flex-col items-center justify-center gap-4'>
      <Head title='403 - Forbidden' />
      <h1 className='text-7xl font-bold'>403</h1>
      <p className='text-lg text-muted-foreground'>Forbidden</p>
      <p className='text-sm text-muted-foreground'>You don&apos;t have permission to access this resource.</p>
      <Button asChild>
        <Link href='/'>Go Home</Link>
      </Button>
    </div>
  )
}
