import { useState } from 'react'
import {
  type ColumnFiltersState,
  type PaginationState,
  type SortingState,
  type VisibilityState,
  flexRender,
  getCoreRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useReactTable,
} from '@tanstack/react-table'
import { cn } from '@/lib/utils'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { DataTablePagination, DataTableToolbar } from '@/components/data-table'
import { type Tag } from '../data/schema'
import { tagsColumns } from './tags-columns'

export function TagsTable({ data }: { data: Tag[] }) {
  const [rowSelection, setRowSelection]       = useState({})
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({})
  const [sorting, setSorting]                 = useState<SortingState>([])
  const [columnFilters, setColumnFilters]     = useState<ColumnFiltersState>([])
  const [pagination, setPagination]           = useState<PaginationState>({ pageIndex: 0, pageSize: 10 })

  const table = useReactTable({
    data,
    columns: tagsColumns,
    state: { sorting, pagination, rowSelection, columnFilters, columnVisibility },
    onPaginationChange:      setPagination,
    onColumnFiltersChange:   setColumnFilters,
    onRowSelectionChange:    setRowSelection,
    onSortingChange:         setSorting,
    onColumnVisibilityChange: setColumnVisibility,
    getPaginationRowModel:   getPaginationRowModel(),
    getCoreRowModel:         getCoreRowModel(),
    getFilteredRowModel:     getFilteredRowModel(),
    getSortedRowModel:       getSortedRowModel(),
    getFacetedRowModel:      getFacetedRowModel(),
    getFacetedUniqueValues:  getFacetedUniqueValues(),
  })

  return (
    <div className={cn('max-sm:has-[div[role="toolbar"]]:mb-16', 'flex flex-1 flex-col gap-4')}>
      <DataTableToolbar
        table={table}
        searchPlaceholder='Search tags...'
        searchKey='name'
      />
      <div className='overflow-hidden rounded-md border'>
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id} className='group/row'>
                {headerGroup.headers.map((header) => (
                  <TableHead
                    key={header.id}
                    colSpan={header.colSpan}
                    className={cn(
                      'bg-background group-hover/row:bg-muted',
                      header.column.columnDef.meta?.className,
                      header.column.columnDef.meta?.thClassName
                    )}
                  >
                    {header.isPlaceholder
                      ? null
                      : flexRender(header.column.columnDef.header, header.getContext())}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'} className='group/row'>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell
                      key={cell.id}
                      className={cn(
                        'bg-background group-hover/row:bg-muted group-data-[state=selected]/row:bg-muted',
                        cell.column.columnDef.meta?.className,
                        cell.column.columnDef.meta?.tdClassName
                      )}
                    >
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={tagsColumns.length} className='h-24 text-center'>
                  No tags found.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} className='mt-auto' />
    </div>
  )
}
