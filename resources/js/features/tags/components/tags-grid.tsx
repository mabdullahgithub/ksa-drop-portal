import { Search, Tag as TagIcon } from 'lucide-react'
import { useState } from 'react'
import { Input } from '@/components/ui/input'
import { type Tag } from '../data/schema'
import { TagCard } from './tag-card'

export function TagsGrid({ data }: { data: Tag[] }) {
  const [search, setSearch] = useState('')

  const filtered = search.trim()
    ? data.filter((t) => t.name.toLowerCase().includes(search.toLowerCase()))
    : data

  return (
    <div className='flex flex-1 flex-col gap-4'>
      {/* Search */}
      <div className='relative max-w-xs'>
        <Search className='absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground' />
        <Input
          placeholder='Search tags...'
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className='ps-8'
        />
      </div>

      {filtered.length === 0 ? (
        <div className='flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed py-16 text-center'>
          <TagIcon className='h-8 w-8 text-muted-foreground/40' />
          <p className='text-sm text-muted-foreground'>
            {search ? 'No tags match your search.' : 'No tags yet. Create your first tag.'}
          </p>
        </div>
      ) : (
        <div className='grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'>
          {filtered.map((tag) => (
            <TagCard key={tag.id} tag={tag} />
          ))}
        </div>
      )}
    </div>
  )
}
