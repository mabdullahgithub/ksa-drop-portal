import { useState } from 'react'
import {
  type ColumnFiltersState,
  type SortingState,
  type VisibilityState,
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { Client } from '@/types/client'
import { clientColumns } from './client-columns'
import { ClientPagination } from './client-pagination'

interface ClientTableProps {
  data: Client[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
  } | null
  loading: boolean
  onPageChange: (page: number) => void
  onPageSizeChange: (size: number) => void
  onSortChange: (sortBy: string, sortOrder: 'asc' | 'desc') => void
}

export function ClientTable({
  data,
  meta,
  loading,
  onPageChange,
  onPageSizeChange,
  onSortChange,
}: ClientTableProps) {
  const [rowSelection, setRowSelection] = useState({})
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({})
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])

  const handleSortingChange = (updater: any) => {
    const newSorting = typeof updater === 'function' ? updater(sorting) : updater
    setSorting(newSorting)
    if (newSorting.length > 0) {
      onSortChange(newSorting[0].id, newSorting[0].desc ? 'desc' : 'asc')
    }
  }

  const table = useReactTable({
    data,
    columns: clientColumns,
    state: { sorting, columnVisibility, rowSelection, columnFilters },
    enableRowSelection: true,
    onRowSelectionChange: setRowSelection,
    onSortingChange: handleSortingChange,
    onColumnFiltersChange: setColumnFilters,
    onColumnVisibilityChange: setColumnVisibility,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    manualSorting: true,
    pageCount: meta?.last_page || 0,
  })

  return (
    <div className='space-y-3'>
      <div className='overflow-hidden rounded-md border border-muted/50'>
        <Table containerClassName='overflow-visible'>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id}>
                {headerGroup.headers.map((header) => (
                  <TableHead key={header.id}>
                    {header.isPlaceholder
                      ? null
                      : flexRender(header.column.columnDef.header, header.getContext())}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {loading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <TableRow key={i}>
                  {clientColumns.map((_, j) => (
                    <TableCell key={j}>
                      <div className='h-4 w-full animate-pulse rounded bg-muted' />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={clientColumns.length} className='h-24 text-center'>
                  No clients found.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
      {meta && (
        <ClientPagination
          meta={meta}
          onPageChange={onPageChange}
          onPageSizeChange={onPageSizeChange}
        />
      )}
    </div>
  )
}
