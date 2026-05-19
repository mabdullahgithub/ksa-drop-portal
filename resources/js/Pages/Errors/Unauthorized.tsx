import { Head, Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'

export default function Unauthorized() {
  return (
    <div className='flex min-h-svh flex-col items-center justify-center gap-4'>
      <Head title='401 - Unauthorized' />
      <h1 className='text-7xl font-bold'>401</h1>
      <p className='text-lg text-muted-foreground'>Unauthorized</p>
      <p className='text-sm text-muted-foreground'>You need to be authenticated to access this resource.</p>
      <Button asChild>
        <Link href='/'>Go Home</Link>
      </Button>
    </div>
  )
}
