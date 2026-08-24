import { useState, useEffect, useCallback } from 'react'
import { usePage } from '@inertiajs/react'
import { Search, X, LayoutGrid, List, Package, Eye, Download, Truck, RotateCcw, Banknote, Warehouse, Phone, Receipt, Tag, Info } from 'lucide-react'
import {
  type SortingState, type Updater,
  flexRender, getCoreRowModel, useReactTable, type ColumnDef,
} from '@tanstack/react-table'
import { format } from 'date-fns'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { cn } from '@/lib/utils'
import { DataTableColumnHeader } from '@/components/data-table'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search as SearchBar } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { InventoryPagination } from '@/features/inventory/components/inventory-pagination'
import { usePortalProducts, usePortalProductFilterOptions } from '@/hooks/usePortal'
import type { Product } from '@/types/product'
import { PortalProductDetailsDialog } from './product-details-dialog'

// ─── helpers ────────────────────────────────────────────────────────────────

function triggerDownload(url: string) {
  const link = document.createElement('a')
  link.href = url
  link.rel = 'noopener'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

function downloadProductsCsv(params: Record<string, string | undefined>) {
  const search = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value) search.append(key, value)
  })
  triggerDownload(`/portal/api/products/download?${search}`)
}

const stockColor = (qty: number) => {
  if (qty === 0) return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
  if (qty <= 5)  return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
  return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
}

// ─── columns ─────────────────────────────────────────────────────────────────

function buildColumns(onView: (p: Product) => void, onDownload: (p: Product) => void): ColumnDef<Product>[] {
  return [
    {
      id: 'image',
      header: () => <span className='text-xs font-medium'>Image</span>,
      cell: ({ row }) => {
        const img = row.original.primary_image
        return img ? (
          <div className='h-9 w-9 shrink-0 overflow-hidden rounded-md border border-muted/50 bg-muted/20'>
            <img src={img} alt={row.original.title} className='h-full w-full object-cover' />
          </div>
        ) : (
          <div className='flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-dashed border-muted-foreground/30 bg-muted/20'>
            <span className='text-[8px] font-medium text-muted-foreground/40'>IMG</span>
          </div>
        )
      },
      enableSorting: false,
    },
    {
      accessorKey: 'title',
      header: ({ column }) => <DataTableColumnHeader column={column} title='Product' />,
      meta: { className: 'ps-1 max-w-0 w-2/5', tdClassName: 'ps-4' },
      cell: ({ row }) => {
        const p = row.original
        return (
          <div className='flex min-w-0 flex-col gap-0'>
            <span className='truncate text-sm font-medium'>{p.title}</span>
            {p.variant_sku && (
              <span className='truncate text-xs text-muted-foreground'>SKU: {p.variant_sku}</span>
            )}
          </div>
        )
      },
      enableSorting: true,
    },
    {
      accessorKey: 'vendor',
      header: ({ column }) => <DataTableColumnHeader column={column} title='Vendor' />,
      meta: { className: 'ps-1', tdClassName: 'ps-4' },
      cell: ({ row }) => (
        <span className='block max-w-[120px] truncate text-sm text-muted-foreground'>
          {row.getValue('vendor') || '—'}
        </span>
      ),
    },
    {
      accessorKey: 'type',
      header: ({ column }) => <DataTableColumnHeader column={column} title='Type' />,
      meta: { className: 'ps-1', tdClassName: 'ps-4' },
      cell: ({ row }) => {
        const type = row.getValue('type') as string | null
        return type ? (
          <Badge variant='outline' className='h-5 px-1.5 py-0 text-xs capitalize'>{type}</Badge>
        ) : (
          <span className='text-sm text-muted-foreground'>—</span>
        )
      },
    },
    {
      accessorKey: 'variant_price',
      header: ({ column }) => <DataTableColumnHeader column={column} title='Price' />,
      meta: { className: 'ps-1', tdClassName: 'ps-4' },
      cell: ({ row }) => {
        const price   = row.original.variant_price
        const compare = row.original.variant_compare_at_price
        return (
          <div className='flex flex-col gap-0'>
            <span className='text-sm font-semibold'>
              {price ? `SAR ${parseFloat(price).toFixed(2)}` : '—'}
            </span>
            {compare && parseFloat(compare) > 0 && parseFloat(compare) !== parseFloat(price || '0') && (
              <span className='text-xs text-muted-foreground line-through'>
                SAR {parseFloat(compare).toFixed(2)}
              </span>
            )}
          </div>
        )
      },
      enableSorting: true,
    },
    {
      accessorKey: 'variant_inventory_qty',
      header: ({ column }) => <DataTableColumnHeader column={column} title='Stock' />,
      meta: { className: 'ps-1', tdClassName: 'ps-4' },
      cell: ({ row }) => {
        const qty = row.getValue('variant_inventory_qty') as number
        return (
          <Badge variant='outline' className={`h-5 px-1.5 py-0 text-xs font-medium tabular-nums ${stockColor(qty)}`}>
            {qty}
          </Badge>
        )
      },
      enableSorting: true,
    },
    {
      accessorKey: 'created_at',
      header: ({ column }) => <DataTableColumnHeader column={column} title='Added' />,
      meta: { className: 'ps-1', tdClassName: 'ps-4' },
      cell: ({ row }) => (
        <span className='text-sm text-muted-foreground'>
          {format(new Date(row.getValue('created_at')), 'MMM dd, yyyy')}
        </span>
      ),
      enableSorting: true,
    },
    {
      id: 'actions',
      cell: ({ row }) => (
        <div className='flex items-center gap-1'>
          <Button
            variant='ghost'
            size='icon'
            className='h-7 w-7'
            onClick={() => onView(row.original)}
            title='View product'
          >
            <Eye className='h-3.5 w-3.5' />
          </Button>
          <Button
            variant='ghost'
            size='icon'
            className='h-7 w-7'
            onClick={() => onDownload(row.original)}
            title='Download for Shopify import'
          >
            <Download className='h-3.5 w-3.5' />
          </Button>
        </div>
      ),
    },
  ]
}

// ─── skeletons ────────────────────────────────────────────────────────────────

function TableSkeleton() {
  return (
    <div className='space-y-3'>
      <div className='overflow-hidden rounded-md border border-muted/50'>
        <Table className='min-w-xl'>
          <TableHeader>
            <TableRow className='border-b border-muted/50 hover:bg-transparent'>
              {[40, 200, 120, 90, 80, 70, 80, 36].map((w, i) => (
                <TableHead key={i} className='h-8 py-1.5'>
                  <Skeleton className={`h-3 w-[${w}px]`} />
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {Array.from({ length: 10 }).map((_, i) => (
              <TableRow key={i} className='border-b border-muted/50 h-10'>
                <TableCell className='py-1.5'><Skeleton className='h-8 w-8 rounded' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-44' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-24' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-5 w-16 rounded-full' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-20' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-5 w-12 rounded-full' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-4 w-16' /></TableCell>
                <TableCell className='py-1.5'><Skeleton className='h-7 w-7 rounded' /></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      <div className='flex items-center justify-between'>
        <Skeleton className='h-4 w-40' />
        <div className='flex items-center gap-2'>
          <Skeleton className='h-8 w-20' />
          <Skeleton className='h-4 w-20' />
          <Skeleton className='h-8 w-24' />
        </div>
      </div>
    </div>
  )
}

function CardsSkeleton() {
  return (
    <div className='grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'>
      {Array.from({ length: 10 }).map((_, i) => (
        <Card key={i} className='overflow-hidden border-muted/50'>
          <Skeleton className='h-48 w-full rounded-none' />
          <CardContent className='space-y-2 p-3'>
            <Skeleton className='h-4 w-full' />
            <Skeleton className='h-3 w-20' />
            <div className='flex items-center justify-between'>
              <Skeleton className='h-4 w-16' />
              <Skeleton className='h-5 w-14 rounded-full' />
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

// ─── table view ──────────────────────────────────────────────────────────────

interface TableViewProps {
  data: Product[]
  meta: any
  loading: boolean
  onPageChange: (p: number) => void
  onPageSizeChange: (s: number) => void
  onSortChange: (by: string, order: 'asc' | 'desc') => void
  onView: (p: Product) => void
  onDownload: (p: Product) => void
}

function ProductTableView({ data, meta, loading, onPageChange, onPageSizeChange, onSortChange, onView, onDownload }: TableViewProps) {
  const [sorting, setSorting] = useState<SortingState>([])
  const columns = buildColumns(onView, onDownload)

  const handleSortingChange = (updater: Updater<SortingState>) => {
    const next = typeof updater === 'function' ? updater(sorting) : updater
    setSorting(next)
    if (next.length > 0) onSortChange(next[0].id, next[0].desc ? 'desc' : 'asc')
    else onSortChange('created_at', 'desc')
  }

  const table = useReactTable({
    data,
    columns,
    state: { sorting },
    onSortingChange: handleSortingChange,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    manualSorting: true,
    pageCount: meta?.last_page || 0,
  })

  if (loading) return <TableSkeleton />

  return (
    <div className='space-y-3'>
      <div className='overflow-hidden rounded-md border border-muted/50'>
        <Table className='min-w-xl'>
          <TableHeader>
            {table.getHeaderGroups().map((hg) => (
              <TableRow key={hg.id} className='border-b border-muted/50 hover:bg-transparent'>
                {hg.headers.map((header) => (
                  <TableHead
                    key={header.id}
                    colSpan={header.colSpan}
                    className={cn('h-8 py-1.5 text-xs font-medium', header.column.columnDef.meta?.className, header.column.columnDef.meta?.thClassName)}
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
                  className='group cursor-pointer border-b border-muted/50 h-10 hover:bg-muted/30'
                  onClick={() => onView(row.original)}
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell
                      key={cell.id}
                      className={cn('py-1.5 text-xs', cell.column.columnDef.meta?.className, cell.column.columnDef.meta?.tdClassName)}
                      onClick={cell.column.id === 'actions' ? (e) => e.stopPropagation() : undefined}
                    >
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={columns.length} className='h-32 text-center text-sm text-muted-foreground'>
                  <div className='flex flex-col items-center gap-2'>
                    <Package className='h-8 w-8 text-muted-foreground/30' />
                    No products available.
                  </div>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {meta && (
        <InventoryPagination
          meta={meta}
          onPageChange={onPageChange}
          onPageSizeChange={onPageSizeChange}
        />
      )}
    </div>
  )
}

// ─── card view ───────────────────────────────────────────────────────────────

interface CardViewProps {
  data: Product[]
  meta: any
  loading: boolean
  onPageChange: (p: number) => void
  onPageSizeChange: (s: number) => void
  onView: (p: Product) => void
  onDownload: (p: Product) => void
}

function ProductCardView({ data, meta, loading, onPageChange, onPageSizeChange, onView, onDownload }: CardViewProps) {
  if (loading) return <CardsSkeleton />

  return (
    <div className='space-y-4'>
      {data.length === 0 ? (
        <div className='flex flex-col items-center justify-center py-16 text-center'>
          <Package className='mb-3 h-12 w-12 text-muted-foreground/30' />
          <p className='text-sm font-medium text-muted-foreground'>No products available</p>
        </div>
      ) : (
        <div className='grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'>
          {data.map((product) => (
            <Card
              key={product.id}
              className='group cursor-pointer overflow-hidden border-muted/50 transition-all duration-200 hover:border-muted hover:shadow-md'
              onClick={() => onView(product)}
            >
              <div className='relative aspect-square overflow-hidden bg-muted/20'>
                {product.primary_image ? (
                  <img
                    src={product.primary_image}
                    alt={product.title}
                    className='h-full w-full object-cover transition-transform duration-300 group-hover:scale-105'
                  />
                ) : (
                  <div className='flex h-full items-center justify-center'>
                    <Package className='h-10 w-10 text-muted-foreground/20' />
                  </div>
                )}

                {/* Stock badge */}
                <div className='absolute top-2 right-2'>
                  <Badge
                    variant='outline'
                    className={`h-5 px-1.5 py-0 text-[10px] font-medium tabular-nums shadow-sm ${stockColor(product.variant_inventory_qty)}`}
                  >
                    {product.variant_inventory_qty} in stock
                  </Badge>
                </div>

                {/* Quick view overlay */}
                <div className='absolute inset-0 flex items-center justify-center gap-1.5 bg-black/0 opacity-0 transition-all duration-200 group-hover:bg-black/15 group-hover:opacity-100'>
                  <Button
                    size='sm'
                    variant='secondary'
                    className='h-8 gap-1.5 text-xs shadow-md'
                    onClick={(e) => { e.stopPropagation(); onView(product) }}
                  >
                    <Eye className='h-3.5 w-3.5' />
                    View Details
                  </Button>
                  <Button
                    size='icon'
                    variant='secondary'
                    className='h-8 w-8 shadow-md'
                    onClick={(e) => { e.stopPropagation(); onDownload(product) }}
                    title='Download for Shopify import'
                  >
                    <Download className='h-3.5 w-3.5' />
                  </Button>
                </div>
              </div>

              <CardContent className='space-y-1.5 p-3'>
                <div>
                  <p className='line-clamp-2 text-xs font-semibold leading-tight'>{product.title}</p>
                  {product.vendor && (
                    <p className='mt-0.5 text-[10px] text-muted-foreground'>{product.vendor}</p>
                  )}
                </div>

                <div className='flex items-end justify-between gap-1'>
                  <div className='flex flex-col'>
                    <span className='text-xs font-bold'>
                      {product.variant_price ? `SAR ${parseFloat(product.variant_price).toFixed(2)}` : '—'}
                    </span>
                    {product.variant_compare_at_price &&
                      parseFloat(product.variant_compare_at_price) > parseFloat(product.variant_price || '0') && (
                      <span className='text-[10px] text-muted-foreground line-through'>
                        SAR {parseFloat(product.variant_compare_at_price).toFixed(2)}
                      </span>
                    )}
                  </div>
                  {product.type && (
                    <Badge variant='outline' className='h-4 px-1.5 py-0 text-[9px] capitalize'>
                      {product.type}
                    </Badge>
                  )}
                </div>

                {product.variant_sku && (
                  <p className='truncate font-mono text-[9px] text-muted-foreground/60'>
                    {product.variant_sku}
                  </p>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {meta && (
        <InventoryPagination
          meta={meta}
          onPageChange={onPageChange}
          onPageSizeChange={onPageSizeChange}
        />
      )}
    </div>
  )
}

// ─── charges strip ───────────────────────────────────────────────────────────

const CHARGE_META: { key: string; label: string; icon: React.ReactNode; prefix?: string; suffix?: string }[] = [
  { key: 'delivery',         label: 'Delivery',         icon: <Truck       className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'return',           label: 'Return',           icon: <RotateCcw   className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'cod',              label: 'COD',              icon: <Banknote    className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'warehousing',      label: 'Warehousing',      icon: <Warehouse   className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'call_confirmation',label: 'Call Confirm',     icon: <Phone       className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'vat',              label: 'VAT',              icon: <Receipt     className='h-4 w-4' />, suffix: '%'   },
  { key: 'other',            label: 'Other',            icon: <Tag         className='h-4 w-4' />, prefix: 'SAR' },
]

const CHARGES_ACK_KEY = 'charges_alert_acknowledged'

function ChargesStrip({ charges }: { charges: Record<string, number | null> | null }) {
  const [hidden, setHidden] = useState(() => localStorage.getItem(CHARGES_ACK_KEY) === '1')

  const acknowledge = () => {
    localStorage.setItem(CHARGES_ACK_KEY, '1')
    setHidden(true)
  }

  const remindLater = () => setHidden(true)

  return (
    <div className='space-y-3'>
      <div className='grid grid-cols-4 gap-3 sm:grid-cols-7'>
        {CHARGE_META.map((m) => {
          const raw = charges?.[m.key]
          const val = raw !== null && raw !== undefined ? raw : 0
          const display = m.prefix
            ? `${m.prefix} ${val.toFixed(2)}`
            : `${val % 1 === 0 ? val : val.toFixed(2)}${m.suffix ?? ''}`
          return (
            <div
              key={m.key}
              className='flex flex-col justify-between rounded-lg border border-muted/60 bg-card p-3 shadow-sm'
            >
              <div className='flex items-center justify-between mb-2'>
                <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground leading-none'>
                  {m.label}
                </p>
                <span className='text-muted-foreground/60 shrink-0'>{m.icon}</span>
              </div>
              <p className='text-sm font-bold tabular-nums'>{display}</p>
            </div>
          )
        })}
      </div>

      {!hidden && (
        <div className='flex flex-wrap items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2.5 text-blue-800 dark:border-blue-800/60 dark:bg-blue-950/40 dark:text-blue-300'>
          <Info className='mt-0.5 h-4 w-4 shrink-0' />
          <p className='flex-1 text-xs leading-relaxed min-w-0'>
            These are the charges applied to your account for each order. They will be deducted from your payouts automatically. Contact your account manager if you have any questions.
          </p>
          <div className='flex shrink-0 items-center gap-2'>
            <button
              onClick={remindLater}
              className='rounded-md border border-blue-300 px-2.5 py-1 text-xs font-medium hover:bg-blue-100 dark:border-blue-700 dark:hover:bg-blue-900/50 transition-colors'
            >
              Remind me again
            </button>
            <button
              onClick={acknowledge}
              className='rounded-md bg-blue-700 px-2.5 py-1 text-xs font-medium text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition-colors'
            >
              Acknowledged
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

// ─── filters bar ─────────────────────────────────────────────────────────────

interface FiltersProps {
  filters: Record<string, any>
  onFiltersChange: (f: Record<string, any>) => void
  viewMode: 'table' | 'card'
  onViewModeChange: (m: 'table' | 'card') => void
  onDownloadAll: () => void
}

function ProductFilters({ filters, onFiltersChange, viewMode, onViewModeChange, onDownloadAll }: FiltersProps) {
  const { options } = usePortalProductFilterOptions()
  const [searchInput, setSearchInput] = useState(filters.search || '')

  useEffect(() => {
    const t = setTimeout(() => {
      if (searchInput !== filters.search) onFiltersChange({ search: searchInput, page: 1 })
    }, 400)
    return () => clearTimeout(t)
  }, [searchInput])

  const hasActiveFilters = filters.search || filters.vendor || filters.type

  const clearFilters = () => {
    setSearchInput('')
    onFiltersChange({ search: '', vendor: undefined, type: undefined, page: 1 })
  }

  return (
    <div className='flex flex-wrap items-center gap-2'>
      <div className='relative min-w-[180px] flex-1'>
        <Search className='absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground' />
        <Input
          placeholder='Search products, SKU, vendor...'
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          className='h-9 pl-8 text-sm'
        />
      </div>

      {options?.vendors && options.vendors.length > 0 && (
        <Select
          value={filters.vendor || 'all'}
          onValueChange={(v) => onFiltersChange({ vendor: v === 'all' ? undefined : v, page: 1 })}
        >
          <SelectTrigger className='h-9 w-[130px] shrink-0'>
            <SelectValue placeholder='Vendor' />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>All Vendors</SelectItem>
            {options.vendors.filter((v) => v.value && v.value !== '').map((v) => (
              <SelectItem key={v.value} value={v.value}>{v.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      {options?.types && options.types.length > 0 && (
        <Select
          value={filters.type || 'all'}
          onValueChange={(v) => onFiltersChange({ type: v === 'all' ? undefined : v, page: 1 })}
        >
          <SelectTrigger className='h-9 w-[120px] shrink-0'>
            <SelectValue placeholder='Type' />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>All Types</SelectItem>
            {options.types.filter((t) => t.value && t.value !== '').map((t) => (
              <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      {hasActiveFilters && (
        <Button variant='ghost' size='icon' onClick={clearFilters} className='h-9 w-9 shrink-0' title='Clear filters'>
          <X className='h-4 w-4' />
        </Button>
      )}

      <Button
        variant='outline'
        size='sm'
        className='ml-auto h-9 gap-1.5 shrink-0 text-xs'
        onClick={onDownloadAll}
        title='Download the current product list as a Shopify-importable CSV'
      >
        <Download className='h-3.5 w-3.5' />
        Download CSV
      </Button>

      <div className='flex shrink-0 items-center overflow-hidden rounded-md border border-muted/70'>
        <Button
          variant={viewMode === 'table' ? 'secondary' : 'ghost'}
          size='sm'
          className='h-9 rounded-none px-2.5'
          onClick={() => onViewModeChange('table')}
          title='List view'
        >
          <List className='h-4 w-4' />
        </Button>
        <Button
          variant={viewMode === 'card' ? 'secondary' : 'ghost'}
          size='sm'
          className='h-9 rounded-none px-2.5'
          onClick={() => onViewModeChange('card')}
          title='Card view'
        >
          <LayoutGrid className='h-4 w-4' />
        </Button>
      </div>
    </div>
  )
}

// ─── main component ───────────────────────────────────────────────────────────

export function PortalProducts() {
  const [viewMode, setViewMode] = useState<'table' | 'card'>('card')
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const { auth } = usePage().props as any
  const charges = auth?.client?.charges ?? null
  const { products, meta, loading, error, filters, updateFilters } = usePortalProducts()

  const handleView = (p: Product) => {
    setSelectedProduct(p)
    setDialogOpen(true)
  }

  const handleDownload = (p: Product) => downloadProductsCsv({ ids: String(p.id) })

  const handleDownloadAll = () => downloadProductsCsv({
    search: filters.search,
    vendor: filters.vendor,
    type: filters.type,
  })

  // ── Forbidden / error state ────────────────────────────────────────────────
  if (!loading && error) {
    return (
      <>
        <Header fixed>
          <SearchBar className='me-auto' />
          <ThemeSwitch />
          <NotificationsDropdown />
          <ProfileDropdown />
        </Header>

        <Main className='flex flex-1 flex-col items-center justify-center'>
          <div className='flex max-w-sm flex-col items-center gap-4 text-center'>
            <div className='flex h-16 w-16 items-center justify-center rounded-full bg-muted/50'>
              <Package className='h-8 w-8 text-muted-foreground/40' />
            </div>
            <div>
              <h2 className='text-xl font-semibold tracking-tight'>
                {error.forbidden ? 'Products Not Available' : 'Something Went Wrong'}
              </h2>
              <p className='mt-1.5 text-sm text-muted-foreground'>
                {error.forbidden
                  ? 'The Products feature is not enabled for your account. Please contact your account manager to get access.'
                  : (error.message ?? 'An unexpected error occurred. Please try refreshing the page.')}
              </p>
            </div>
          </div>
        </Main>
      </>
    )
  }

  return (
    <>
      <Header fixed>
        <SearchBar className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main className='flex flex-1 flex-col gap-4'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>Products</h2>
            <p className='text-sm text-muted-foreground'>
              Browse all available products for dropshipping.
            </p>
          </div>
          {meta && (
            <p className='text-sm text-muted-foreground'>
              {meta.total.toLocaleString()} product{meta.total !== 1 ? 's' : ''} available
            </p>
          )}
        </div>

        <ChargesStrip charges={charges} />

        <ProductFilters
          filters={filters}
          onFiltersChange={updateFilters}
          viewMode={viewMode}
          onViewModeChange={setViewMode}
          onDownloadAll={handleDownloadAll}
        />

        {viewMode === 'table' ? (
          <ProductTableView
            data={products}
            meta={meta}
            loading={loading}
            onView={handleView}
            onDownload={handleDownload}
            onPageChange={(page) => updateFilters({ page })}
            onPageSizeChange={(per_page) => updateFilters({ per_page, page: 1 })}
            onSortChange={(sort_by, sort_order) => updateFilters({ sort_by, sort_order, page: 1 })}
          />
        ) : (
          <ProductCardView
            data={products}
            meta={meta}
            loading={loading}
            onView={handleView}
            onDownload={handleDownload}
            onPageChange={(page) => updateFilters({ page })}
            onPageSizeChange={(per_page) => updateFilters({ per_page, page: 1 })}
          />
        )}
      </Main>

      <PortalProductDetailsDialog
        product={selectedProduct}
        open={dialogOpen}
        onOpenChange={setDialogOpen}
      />
    </>
  )
}

