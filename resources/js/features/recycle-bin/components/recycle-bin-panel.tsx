import { useState } from 'react'
import { type ColumnDef } from '@tanstack/react-table'
import { Search, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { SearchBeam } from '@/components/search-beam'
import {
  type RecycleBinTab,
  type RestoreResult,
  RecycleBinLockedError,
  useRecycleBinActions,
  useRecycleBinList,
} from '@/hooks/useRecycleBin'
import { EmptyBinDialog } from './empty-bin-dialog'
import { RecycleBinTable } from './recycle-bin-table'

type RecycleBinPanelProps<T> = {
  tab: RecycleBinTab
  columns: ColumnDef<T>[]
  entityName: string
  entityLabel: string
  searchPlaceholder: string
  emptyMessage: string
  getRowId?: (row: T) => string
  /**
   * Unfiltered count for this tab, from the tab badges. Used for the Empty bin
   * button and its confirmation so an active search cannot make the blast
   * radius look smaller than it is.
   */
  totalInBin: number
  onChanged: () => void
  /** Called when the server reports the bin is locked, so the page can re-prompt. */
  onLocked: () => void
}

/**
 * One tab of the bin: search, listing, bulk restore/purge and Empty bin.
 * All three tabs differ only in their columns and wording.
 */
export function RecycleBinPanel<T extends { id: number }>({
  tab,
  columns,
  entityName,
  entityLabel,
  searchPlaceholder,
  emptyMessage,
  getRowId,
  totalInBin,
  onChanged,
  onLocked,
}: RecycleBinPanelProps<T>) {
  const { items, meta, loading, setPage, setPerPage, search, setSearch, refresh } =
    useRecycleBinList<T>(tab, true, onLocked)
  const { restore, purge, purgeAll } = useRecycleBinActions(tab)
  const [showEmptyDialog, setShowEmptyDialog] = useState(false)

  const afterChange = async () => {
    await refresh()
    onChanged()
  }

  /**
   * Restores can be partial: an item whose parent client is still deleted is
   * refused, and a client product may come back under a new code if something
   * took its old one. Surface both rather than claiming a clean success.
   */
  const reportRestore = (result: RestoreResult) => {
    if (result.restored_count > 0) {
      toast.success(result.message)
    }

    result.renamed?.forEach((item) => {
      toast.info(`"${item.name}" was restored as ${item.to} — ${item.from} was already taken.`)
    })

    result.blocked?.forEach((item) => {
      toast.warning(`"${item.name}" was not restored. ${item.reason}`)
    })

    if (result.restored_count === 0 && !result.blocked?.length) {
      toast.error('Nothing was restored.')
    }
  }

  /** A lock failure means re-prompt for the PIN, not an error toast. */
  const guardLock = async (action: () => Promise<void>) => {
    try {
      await action()
    } catch (error) {
      if (error instanceof RecycleBinLockedError) {
        onLocked()
        return
      }
      throw error
    }
  }

  const handleRestore = (ids: (number | string)[]) =>
    guardLock(async () => {
      reportRestore(await restore(ids))
      await afterChange()
    })

  const handlePurge = (ids: (number | string)[]) =>
    guardLock(async () => {
      const result = await purge(ids)
      toast.success(result.message)
      await afterChange()
    })

  const handlePurgeAll = () =>
    guardLock(async () => {
      const result = await purgeAll()
      toast.success(result.message)
      await afterChange()
    })

  return (
    <div className='space-y-4'>
      <div className='flex flex-wrap items-center justify-between gap-2'>
        <div className='relative w-full max-w-sm'>
          <Search className='text-muted-foreground absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2' />
          <SearchBeam>
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={searchPlaceholder}
              className='h-9 ps-8'
            />
          </SearchBeam>
        </div>

        <Button
          variant='destructive'
          size='sm'
          disabled={totalInBin === 0}
          onClick={() => setShowEmptyDialog(true)}
          className='gap-1.5'
        >
          <Trash2 className='h-4 w-4' />
          Empty {entityLabel} bin ({totalInBin})
        </Button>
      </div>

      <RecycleBinTable<T>
        data={items}
        columns={columns}
        meta={meta}
        loading={loading}
        entityName={entityName}
        emptyMessage={search ? `No deleted ${entityName}s match "${search}".` : emptyMessage}
        getRowId={getRowId}
        onPageChange={setPage}
        onPageSizeChange={setPerPage}
        onRestore={handleRestore}
        onPurge={handlePurge}
      />

      <EmptyBinDialog
        open={showEmptyDialog}
        onOpenChange={setShowEmptyDialog}
        count={totalInBin}
        entityLabel={entityLabel}
        onConfirm={handlePurgeAll}
      />
    </div>
  )
}
