import { useState } from 'react'
import { LayoutGrid, Table2 } from 'lucide-react'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Button } from '@/components/ui/button'
import { type Tag } from './data/schema'
import { TagsDialogs } from './components/tags-dialogs'
import { TagsGrid } from './components/tags-grid'
import { TagsPrimaryButtons } from './components/tags-primary-buttons'
import { TagsProvider } from './components/tags-provider'
import { TagsTable } from './components/tags-table'

export function Tags({ tags }: { tags: Tag[] }) {
  const [view, setView] = useState<'grid' | 'table'>('grid')

  return (
    <TagsProvider>
      <Header fixed>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div className='space-y-1'>
            <h2 className='text-3xl font-bold tracking-tight'>Tags</h2>
            <p className='text-muted-foreground'>
              Create and manage tags to categorize and organize your orders.
            </p>
          </div>
          <div className='flex items-center gap-2'>
            {/* View toggle */}
            <div className='flex items-center rounded-md border p-0.5'>
              <Button
                variant={view === 'grid' ? 'secondary' : 'ghost'}
                size='icon'
                className='h-7 w-7'
                onClick={() => setView('grid')}
                title='Card view'
              >
                <LayoutGrid size={14} />
              </Button>
              <Button
                variant={view === 'table' ? 'secondary' : 'ghost'}
                size='icon'
                className='h-7 w-7'
                onClick={() => setView('table')}
                title='Table view'
              >
                <Table2 size={14} />
              </Button>
            </div>
            <TagsPrimaryButtons />
          </div>
        </div>

        {view === 'grid' ? (
          <TagsGrid data={tags} />
        ) : (
          <TagsTable data={tags} />
        )}
      </Main>

      <TagsDialogs />
    </TagsProvider>
  )
}
