import { useState } from 'react'
import { type ColumnDef, flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table'
import { cn } from '@/lib/utils'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { type RecycleBinMeta } from '@/hooks/useRecycleBin'
import { RecycleBinBulkActions } from './recycle-bin-bulk-actions'
import { RecycleBinPagination } from './recycle-bin-pagination'

type RecycleBinTableProps<T> = {
  data: T[]
  columns: ColumnDef<T>[]
  meta: RecycleBinMeta | null
  loading: boolean
  entityName: string
  emptyMessage: string
  /**
   * Row identity. The inventory tab MUST pass this: its ids come from two
   * different tables and collide, so without a composite key selecting
   * catalog product 7 would also select client product 7 — and a bulk purge
   * would destroy the wrong row.
   */
  getRowId?: (row: T) => string
  onPageChange: (page: number) => void
  onPageSizeChange: (size: number) => void
  /** Omitted when the user may not restore; hides the action. */
  onRestore?: (ids: (number | string)[]) => Promise<void>
  /** Omitted when the user may not purge; hides the action. */
  onPurge?: (ids: (number | string)[]) => Promise<void>
}

export function RecycleBinTable<T extends { id: number }>({
  data,
  columns,
  meta,
  loading,
  entityName,
  emptyMessage,
  getRowId,
  onPageChange,
  onPageSizeChange,
  onRestore,
  onPurge,
}: RecycleBinTableProps<T>) {
  const [rowSelection, setRowSelection] = useState({})

  const table = useReactTable({
    data,
    columns,
    state: { rowSelection },
    enableRowSelection: true,
    onRowSelectionChange: setRowSelection,
    getCoreRowModel: getCoreRowModel(),
    getRowId: getRowId ? (row) => getRowId(row) : (row) => String(row.id),
    manualPagination: true,
    manualSorting: true,
    pageCount: meta?.last_page || 0,
  })

  if (loading) {
    return (
      <div className='space-y-2'>
        {Array.from({ length: 6 }).map((_, index) => (
          <Skeleton key={index} className='h-10 w-full' />
        ))}
      </div>
    )
  }

  return (
    <div className='space-y-3'>
      <div className='overflow-hidden rounded-md border border-muted/50'>
        <Table className='min-w-xl'>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id} className='border-b border-muted/50 hover:bg-transparent'>
                {headerGroup.headers.map((header) => (
                  <TableHead
                    key={header.id}
                    colSpan={header.colSpan}
                    className={cn('h-8 py-1.5 text-xs font-medium', header.column.columnDef.meta?.className)}
                  >
                    {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow
                  key={row.id}
                  data-state={row.getIsSelected() && 'selected'}
                  className='border-b border-muted/50 h-10'
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell
                      key={cell.id}
                      className={cn('py-1.5 text-xs', cell.column.columnDef.meta?.className)}
                    >
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={columns.length} className='h-24 text-center text-sm text-muted-foreground'>
                  {emptyMessage}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {meta && meta.total > 0 && (
        <RecycleBinPagination meta={meta} onPageChange={onPageChange} onPageSizeChange={onPageSizeChange} />
      )}

      {(onRestore || onPurge) && (
        <RecycleBinBulkActions
          table={table}
          entityName={entityName}
          onRestore={onRestore}
          onPurge={onPurge}
        />
      )}
    </div>
  )
}
