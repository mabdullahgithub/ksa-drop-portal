import { Head, Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'

export default function InternalServerError() {
  return (
    <div className='flex min-h-svh flex-col items-center justify-center gap-4'>
      <Head title='500 - Internal Server Error' />
      <h1 className='text-7xl font-bold'>500</h1>
      <p className='text-lg text-muted-foreground'>Internal Server Error</p>
      <p className='text-sm text-muted-foreground'>Something went wrong on our end. Please try again later.</p>
      <Button asChild>
        <Link href='/'>Go Home</Link>
      </Button>
    </div>
  )
}
