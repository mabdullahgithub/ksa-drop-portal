import { Search } from 'lucide-react'
import { useState } from 'react'
import { Input } from '@/components/ui/input'
import { SearchBeam } from '@/components/search-beam'
import { type Tag } from '../data/schema'
import { TagCard } from './tag-card'
import { EmptyState } from '@/components/empty-state'

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
        <SearchBeam>
          <Input
            placeholder='Search tags...'
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className='ps-8'
          />
        </SearchBeam>
      </div>

      {filtered.length === 0 ? (
        <EmptyState
          bot='clover'
          state={search ? 'default' : 'sleeping'}
          title={search ? 'No tags match your search.' : 'No tags yet. Create your first tag.'}
          className='flex-1 rounded-xl border border-dashed py-16'
        />
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
