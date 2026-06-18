import { useState, useEffect } from 'react'
import {
  type ColumnFiltersState,
  type SortingState,
  type Updater,
  type VisibilityState,
  flexRender,
  getCoreRowModel,
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
import { type Order } from '@/types/order'
import { DataTableBulkActions } from './data-table-bulk-actions'
import { ordersColumns as columns } from './orders-columns'
import { OrdersPagination } from './orders-pagination'
import { OrdersTableSkeleton } from './orders-skeleton'

type OrdersTableProps = {
  data: Order[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
    from: number
    to: number
  } | null
  loading?: boolean
  onRefresh?: () => void
  onPageChange?: (page: number) => void
  onPageSizeChange?: (pageSize: number) => void
  onSortChange?: (sortBy: string, sortOrder: 'asc' | 'desc') => void
  onTableReady?: (table: any) => void
}

export function OrdersTable({
  data,
  meta,
  loading,
  onPageChange,
  onPageSizeChange,
  onSortChange,
  onTableReady,
}: OrdersTableProps) {
  const [rowSelection, setRowSelection] = useState({})
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({
    shipment: false,
    shipping_country: false,
    payment_method: false,
    customer_email: false,
    fulfillment_status: false,
    financial_status: false,
  })
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])

  const handleSortingChange = (updater: Updater<SortingState>) => {
    const newSorting = typeof updater === 'function' ? updater(sorting) : updater
    setSorting(newSorting)
    if (onSortChange && newSorting.length > 0) {
      onSortChange(newSorting[0].id, newSorting[0].desc ? 'desc' : 'asc')
    } else if (onSortChange && newSorting.length === 0) {
      onSortChange('created_at', 'desc')
    }
  }

  const table = useReactTable({
    data,
    columns,
    state: {
      sorting,
      columnVisibility,
      rowSelection,
      columnFilters,
    },
    enableRowSelection: true,
    onRowSelectionChange: setRowSelection,
    onSortingChange: handleSortingChange,
    onColumnVisibilityChange: setColumnVisibility,
    onColumnFiltersChange: setColumnFilters,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    manualSorting: true,
    pageCount: meta?.last_page || 0,
  })

  // Notify parent when table is ready
  useEffect(() => {
    if (onTableReady) {
      onTableReady(table)
    }
  }, [table, onTableReady])

  if (loading) {
    return <OrdersTableSkeleton />
  }

  return (
    <div className='space-y-3'>
      {/* Table */}
      <div className='overflow-hidden rounded-md border border-muted/50'>
        <Table className='min-w-xl'>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id} className='border-b border-muted/50 hover:bg-transparent'>
                {headerGroup.headers.map((header) => {
                  return (
                    <TableHead
                      key={header.id}
                      colSpan={header.colSpan}
                      className={cn(
                        'h-8 py-1.5 text-xs font-medium',
                        header.column.columnDef.meta?.className,
                        header.column.columnDef.meta?.thClassName
                      )}
                    >
                      {header.isPlaceholder
                        ? null
                        : flexRender(
                            header.column.columnDef.header,
                            header.getContext()
                          )}
                    </TableHead>
                  )
                })}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow
                  key={row.id}
                  data-state={row.getIsSelected() && 'selected'}
                  className='border-b border-muted/50 h-9'
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell
                      key={cell.id}
                      className={cn(
                        'py-1.5 text-xs',
                        cell.column.columnDef.meta?.className,
                        cell.column.columnDef.meta?.tdClassName
                      )}
                    >
                      {flexRender(
                        cell.column.columnDef.cell,
                        cell.getContext()
                      )}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell
                  colSpan={columns.length}
                  className='h-24 text-center'
                >
                  No orders found.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      {meta && (
        <OrdersPagination
          meta={meta}
          onPageChange={onPageChange!}
          onPageSizeChange={onPageSizeChange}
        />
      )}

      <DataTableBulkActions table={table} />
    </div>
  )
}
