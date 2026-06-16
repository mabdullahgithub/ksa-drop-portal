import { Head, Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'

export default function NotFound() {
  return (
    <div className='flex min-h-svh flex-col items-center justify-center gap-6 px-6 text-center'>
      <Head title='404 - Not Found' />
      <img
        src='/images/login/3d-casual-life-young-man-sitting-on-floor-near-laptop.png'
        className='h-auto w-full max-w-xs select-none object-contain sm:max-w-sm'
        alt='Page not found'
      />
      <h1 className='text-7xl font-bold'>404</h1>
      <p className='text-lg text-muted-foreground'>Not Found</p>
      <p className='text-sm text-muted-foreground'>
        The resource you are looking for does not exist.
      </p>
      <Button asChild>
        <Link href='/'>Go Home</Link>
      </Button>
    </div>
  )
}
