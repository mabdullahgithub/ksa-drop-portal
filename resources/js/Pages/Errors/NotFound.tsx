import { Head, Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'

export default function NotFound() {
  return (
    <div className='flex min-h-svh flex-col items-center justify-center gap-4'>
      <Head title='404 - Not Found' />
      <h1 className='text-7xl font-bold'>404</h1>
      <p className='text-lg text-muted-foreground'>Not Found</p>
      <p className='text-sm text-muted-foreground'>The resource you are looking for does not exist.</p>
      <Button asChild>
        <Link href='/'>Go Home</Link>
      </Button>
    </div>
  )
}
