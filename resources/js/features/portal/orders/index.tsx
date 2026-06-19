import { useState, useRef, useCallback } from 'react'
import { Search as SearchIcon, Download, Upload, CheckCircle2, AlertCircle, FileSpreadsheet, X, Package, DollarSign, Clock, TruckIcon, Copy } from 'lucide-react'
import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Progress } from '@/components/ui/progress'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalOrders, usePortalOrderMutations, usePortalDashboard } from '@/hooks/usePortal'
import { OrdersPagination } from '@/features/orders/components/orders-pagination'
import { Card, CardContent } from '@/components/ui/card'

const MAX_FILE_SIZE_MB = 10
const ALLOWED_MIME = ['text/csv', 'application/vnd.ms-excel', 'application/csv']

type ImportResult = {
  success: boolean
  message: string
  imported?: number
  duplicates?: number
  invalid?: number
  total?: number
  has_errors?: boolean
  errors?: Array<{ row: number; order_number?: string; reason: string; details: string }>
}

const columns: ColumnDef<any>[] = [
  {
    accessorKey: 'order_number',
    header: 'Order #',
    cell: ({ row }) => <span className='font-medium'>{row.getValue('order_number')}</span>,
  },
  {
    accessorKey: 'customer_name',
    header: 'Customer',
    cell: ({ row }) => <span>{row.getValue('customer_name') || '—'}</span>,
  },
  {
    accessorKey: 'fulfillment_status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('fulfillment_status') as string
      const colorMap: Record<string, string> = {
        fulfilled: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        unfulfilled: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
      }
      return (
        <Badge variant='outline' className={`text-xs capitalize ${colorMap[status] || ''}`}>
          {status}
        </Badge>
      )
    },
  },
  {
    accessorKey: 'financial_status',
    header: 'Payment',
    cell: ({ row }) => {
      const status = row.getValue('financial_status') as string
      const colorMap: Record<string, string> = {
        paid: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        refunded: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        partially_refunded: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
      }
      return (
        <Badge variant='outline' className={`text-xs capitalize ${colorMap[status] || ''}`}>
          {status?.replace(/_/g, ' ')}
        </Badge>
      )
    },
  },
  {
    accessorKey: 'total',
    header: 'Total',
    cell: ({ row }) => (
      <span className='font-medium'>
        SAR {parseFloat(row.getValue('total') || '0').toLocaleString()}
      </span>
    ),
  },
  {
    id: 'tracking',
    header: 'Tracking',
    cell: ({ row }) => {
      const shipment = row.original.latest_shipment
      if (!shipment) return <span className='text-muted-foreground'>—</span>

      const colorMap: Record<string, string> = {
        gray: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
        orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
      }

      const copyTracking = () => {
        if (shipment.tracking_number) {
          navigator.clipboard.writeText(shipment.tracking_number)
        }
      }

      return (
        <div className='flex flex-col gap-1'>
          <Badge variant='outline' className={`w-fit text-xs ${colorMap[shipment.status_color] || ''}`}>
            {shipment.status_label}
          </Badge>
          {shipment.tracking_number && (
            <div className='flex items-center gap-1'>
              <a
                href={`/track?q=${encodeURIComponent(shipment.tracking_number)}`}
                target='_blank'
                rel='noopener noreferrer'
                className='font-mono text-xs text-primary hover:underline'
              >
                {shipment.tracking_number}
              </a>
              <button
                type='button'
                onClick={copyTracking}
                className='text-muted-foreground hover:text-foreground'
                title='Copy tracking number'
              >
                <Copy className='h-3 w-3' />
              </button>
            </div>
          )}
        </div>
      )
    },
  },
  {
    accessorKey: 'created_at',
    header: 'Date',
    cell: ({ row }) => {
      const val = row.getValue('created_at') as string
      if (!val) return <span>—</span>
      try {
        return <span>{new Date(val).toLocaleDateString()}</span>
      } catch {
        return <span>—</span>
      }
    },
  },
]

function validateFile(file: File): string | null {
  const isCSV =
    file.name.toLowerCase().endsWith('.csv') ||
    ALLOWED_MIME.includes(file.type) ||
    file.type === ''
  if (!isCSV) return 'Only CSV files are supported.'
  if (file.size === 0) return 'The selected file is empty.'
  if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024)
    return `File size must be under ${MAX_FILE_SIZE_MB}MB. Selected file is ${(file.size / 1024 / 1024).toFixed(1)}MB.`
  return null
}

function PortalOrdersImportDialog({
  open,
  onOpenChange,
  onSuccess,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}) {
  const { importOrders, downloadOrdersTemplate } = usePortalOrderMutations()
  const [file, setFile] = useState<File | null>(null)
  const [fileError, setFileError] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)
  const [progress, setProgress] = useState(0)
  const [isDragging, setIsDragging] = useState(false)
  const [result, setResult] = useState<ImportResult | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const dragCounter = useRef(0)

  const selectFile = useCallback((f: File) => {
    const err = validateFile(f)
    if (err) {
      setFileError(err)
      setFile(null)
    } else {
      setFileError(null)
      setFile(f)
      setResult(null)
    }
  }, [])

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selected = e.target.files?.[0]
    if (selected) selectFile(selected)
    // reset so the same file can be re-selected after removal
    e.target.value = ''
  }

  const handleDragEnter = (e: React.DragEvent) => {
    e.preventDefault()
    dragCounter.current++
    setIsDragging(true)
  }

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault()
    dragCounter.current--
    if (dragCounter.current === 0) setIsDragging(false)
  }

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault()
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    dragCounter.current = 0
    setIsDragging(false)
    const dropped = e.dataTransfer.files[0]
    if (dropped) selectFile(dropped)
  }

  const handleRemoveFile = () => {
    setFile(null)
    setFileError(null)
    setResult(null)
  }

  const handleImport = async () => {
    if (!file) return

    setUploading(true)
    setProgress(0)
    setResult(null)

    const interval = setInterval(
      () => setProgress((p) => Math.min(p + 8, 88)),
      250
    )

    try {
      const data = await importOrders(file)
      clearInterval(interval)
      setProgress(100)

      if (data?.success) {
        setResult(data as ImportResult)
        onSuccess?.()
      } else {
        setResult({
          success: false,
          message: data?.message || 'Import failed. Please try again.',
        })
      }
    } catch (error) {
      clearInterval(interval)
      setProgress(0)
      setResult({
        success: false,
        message:
          error instanceof Error
            ? error.message
            : 'An unexpected error occurred. Please try again.',
      })
    } finally {
      setUploading(false)
    }
  }

  const handleReset = () => {
    setFile(null)
    setFileError(null)
    setResult(null)
    setProgress(0)
    setIsDragging(false)
    dragCounter.current = 0
  }

  const handleClose = () => {
    if (uploading) return
    handleReset()
    onOpenChange(false)
  }

  const showDropzone = !file && !result
  const showFileCard = !!file && !result

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className='sm:max-w-[500px]'>
        <DialogHeader>
          <DialogTitle>Import Orders from CSV</DialogTitle>
          <DialogDescription>
            Download the template, fill in your orders following its format, then
            upload the completed CSV. <span className='font-medium text-foreground'>Customer name and phone are
            required</span> for every order; other columns are optional. All orders
            will be linked to your account.
          </DialogDescription>
        </DialogHeader>

        <div className='space-y-4'>
          {/* Template download */}
          {!result && (
            <div className='flex items-center justify-between gap-3 rounded-lg border bg-muted/40 p-3'>
              <div className='flex items-start gap-2'>
                <FileSpreadsheet className='h-4 w-4 text-muted-foreground mt-0.5 shrink-0' />
                <p className='text-xs text-muted-foreground'>
                  Not sure about the format? Download the template with sample data
                  and the required columns.
                </p>
              </div>
              <Button
                variant='outline'
                size='sm'
                type='button'
                className='shrink-0'
                onClick={() => downloadOrdersTemplate()}
              >
                <Download className='mr-2 h-4 w-4' />
                Template
              </Button>
            </div>
          )}

          {/* Drop zone */}
          {showDropzone && (
            <div
              onDragEnter={handleDragEnter}
              onDragLeave={handleDragLeave}
              onDragOver={handleDragOver}
              onDrop={handleDrop}
              onClick={() => fileInputRef.current?.click()}
              className={`flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-12 text-center transition-colors cursor-pointer select-none ${
                isDragging
                  ? 'border-primary bg-primary/5'
                  : fileError
                  ? 'border-destructive/50 bg-destructive/5'
                  : 'border-muted-foreground/25 hover:border-muted-foreground/50'
              }`}
            >
              <FileSpreadsheet
                className={`mb-4 h-12 w-12 ${
                  isDragging
                    ? 'text-primary'
                    : fileError
                    ? 'text-destructive'
                    : 'text-muted-foreground'
                }`}
              />
              <div className='mb-4'>
                <p className='text-sm font-medium'>
                  {isDragging ? 'Drop your CSV file here' : 'Drag & drop or click to select'}
                </p>
                <p className='text-xs text-muted-foreground mt-1'>
                  CSV files only • Maximum {MAX_FILE_SIZE_MB}MB
                </p>
              </div>
              <Button
                variant='outline'
                size='sm'
                type='button'
                onClick={(e) => {
                  e.stopPropagation()
                  fileInputRef.current?.click()
                }}
              >
                <Upload className='mr-2 h-4 w-4' />
                Choose File
              </Button>
              <input
                ref={fileInputRef}
                type='file'
                accept='.csv,text/csv'
                onChange={handleFileChange}
                className='hidden'
              />
            </div>
          )}

          {/* File validation error */}
          {fileError && !file && (
            <Alert variant='destructive'>
              <AlertCircle className='h-4 w-4' />
              <AlertDescription>{fileError}</AlertDescription>
            </Alert>
          )}

          {/* Selected file card */}
          {showFileCard && (
            <div className='rounded-lg border bg-muted/50 p-4'>
              <div className='flex items-start justify-between'>
                <div className='flex items-start gap-3'>
                  <FileSpreadsheet className='h-5 w-5 text-muted-foreground mt-0.5 shrink-0' />
                  <div className='flex-1 min-w-0'>
                    <p className='text-sm font-medium truncate'>{file.name}</p>
                    <p className='text-xs text-muted-foreground'>
                      {(file.size / 1024).toFixed(1)} KB
                    </p>
                  </div>
                </div>
                {!uploading && (
                  <Button
                    variant='ghost'
                    size='icon'
                    onClick={handleRemoveFile}
                    className='h-7 w-7 shrink-0 -mt-0.5 -mr-1'
                  >
                    <X className='h-4 w-4' />
                  </Button>
                )}
              </div>

              {uploading && (
                <div className='mt-4 space-y-2'>
                  <Progress value={progress} className='h-1.5' />
                  <p className='text-xs text-center text-muted-foreground'>
                    Importing orders… {progress}%
                  </p>
                </div>
              )}
            </div>
          )}

          {/* Result */}
          {result && (
            <div className='space-y-3'>
              <Alert variant={result.success ? 'default' : 'destructive'}>
                {result.success ? (
                  <CheckCircle2 className='h-4 w-4' />
                ) : (
                  <AlertCircle className='h-4 w-4' />
                )}
                <AlertDescription>
                  <p className='font-medium'>{result.message}</p>
                  {result.success && result.imported !== undefined && (
                    <div className='mt-3 text-sm space-y-2'>
                      {(result.imported ?? 0) > 0 && (
                        <div className='flex justify-between'>
                          <span className='text-muted-foreground'>✓ Imported:</span>
                          <span className='font-medium text-green-600 dark:text-green-400'>
                            {result.imported}
                          </span>
                        </div>
                      )}
                      {(result.duplicates ?? 0) > 0 && (
                        <div className='flex justify-between'>
                          <span className='text-muted-foreground'>⊘ Duplicates skipped:</span>
                          <span className='font-medium text-blue-600 dark:text-blue-400'>
                            {result.duplicates}
                          </span>
                        </div>
                      )}
                      {(result.invalid ?? 0) > 0 && (
                        <div className='flex justify-between'>
                          <span className='text-muted-foreground'>⚠ Invalid rows:</span>
                          <span className='font-medium text-orange-600 dark:text-orange-400'>
                            {result.invalid}
                          </span>
                        </div>
                      )}
                      <div className='flex justify-between border-t pt-2'>
                        <span className='text-muted-foreground'>Total rows processed:</span>
                        <span className='font-medium'>{result.total}</span>
                      </div>
                    </div>
                  )}
                </AlertDescription>
              </Alert>

              {result.has_errors && result.errors && result.errors.length > 0 && (
                <div className='rounded-lg border bg-muted/50 p-3 max-h-40 overflow-y-auto'>
                  <p className='text-sm font-medium mb-2'>
                    Issues found ({result.errors.length}):
                  </p>
                  <div className='space-y-2'>
                    {result.errors.slice(0, 10).map((err, i) => (
                      <div key={i} className='text-xs space-y-0.5'>
                        <div className='flex items-center gap-2'>
                          <span className='font-medium'>Row {err.row}:</span>
                          {err.order_number && (
                            <span className='text-muted-foreground'>({err.order_number})</span>
                          )}
                        </div>
                        <p className='text-muted-foreground'>
                          {err.reason} — {err.details}
                        </p>
                      </div>
                    ))}
                    {result.errors.length > 10 && (
                      <p className='text-xs text-muted-foreground italic'>
                        … and {result.errors.length - 10} more issues
                      </p>
                    )}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Actions */}
          <div className='flex justify-end gap-2'>
            {result ? (
              <>
                <Button variant='outline' onClick={handleReset}>
                  Import Another
                </Button>
                <Button onClick={handleClose}>Close</Button>
              </>
            ) : (
              <>
                <Button variant='outline' onClick={handleClose} disabled={uploading}>
                  Cancel
                </Button>
                {file && (
                  <Button onClick={handleImport} disabled={uploading}>
                    {uploading ? 'Importing…' : 'Import Orders'}
                  </Button>
                )}
              </>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}

export function PortalOrders() {
  const { orders, meta, loading, filters, updateFilters, refresh } = usePortalOrders()
  const { exportOrders } = usePortalOrderMutations()
  const { data: dashboardData } = usePortalDashboard()
  const [search, setSearch] = useState('')
  const [importDialogOpen, setImportDialogOpen] = useState(false)

  const table = useReactTable({
    data: orders,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: meta?.last_page ?? 0,
  })

  const handleSearch = (value: string) => {
    setSearch(value)
    updateFilters({ search: value, page: 1 })
  }

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main fixed>
        <div className='mb-4 flex items-center justify-between gap-4 flex-wrap'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>My Orders</h1>
            <p className='text-muted-foreground'>Track and manage all your orders</p>
          </div>
          <div className='flex items-center gap-2'>
            <Button
              variant='outline'
              size='sm'
              onClick={() => setImportDialogOpen(true)}
            >
              <Upload className='mr-2 h-4 w-4' />
              Import CSV
            </Button>
            <Button
              variant='outline'
              size='sm'
              onClick={() => exportOrders(filters)}
            >
              <Download className='mr-2 h-4 w-4' />
              Export CSV
            </Button>
          </div>
        </div>

        {dashboardData?.stats && (
          <div className='mb-4 grid gap-4 grid-cols-2 lg:grid-cols-4'>
            <Card>
              <CardContent className='flex items-center gap-3 p-4'>
                <div className='flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30'>
                  <Package className='h-5 w-5 text-blue-600 dark:text-blue-400' />
                </div>
                <div>
                  <p className='text-sm text-muted-foreground'>Total Orders</p>
                  <p className='text-xl font-bold'>{dashboardData.stats.total_orders}</p>
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className='flex items-center gap-3 p-4'>
                <div className='flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30'>
                  <DollarSign className='h-5 w-5 text-green-600 dark:text-green-400' />
                </div>
                <div>
                  <p className='text-sm text-muted-foreground'>Total Revenue</p>
                  <p className='text-xl font-bold'>SAR {dashboardData.stats.total_revenue?.toLocaleString()}</p>
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className='flex items-center gap-3 p-4'>
                <div className='flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/30'>
                  <Clock className='h-5 w-5 text-yellow-600 dark:text-yellow-400' />
                </div>
                <div>
                  <p className='text-sm text-muted-foreground'>Pending</p>
                  <p className='text-xl font-bold'>{dashboardData.stats.pending_orders}</p>
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className='flex items-center gap-3 p-4'>
                <div className='flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30'>
                  <TruckIcon className='h-5 w-5 text-purple-600 dark:text-purple-400' />
                </div>
                <div>
                  <p className='text-sm text-muted-foreground'>Fulfilled</p>
                  <p className='text-xl font-bold'>{(dashboardData.stats.total_orders ?? 0) - (dashboardData.stats.pending_orders ?? 0)}</p>
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        <div className='space-y-4'>
          <div className='relative max-w-sm'>
            <SearchIcon className='absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground' />
            <Input
              placeholder='Search orders…'
              value={search}
              onChange={(e) => handleSearch(e.target.value)}
              className='pl-8'
            />
          </div>

          <div className='overflow-hidden rounded-md border'>
            <Table>
              <TableHeader>
                {table.getHeaderGroups().map((hg) => (
                  <TableRow key={hg.id}>
                    {hg.headers.map((h) => (
                      <TableHead key={h.id}>
                        {h.isPlaceholder
                          ? null
                          : flexRender(h.column.columnDef.header, h.getContext())}
                      </TableHead>
                    ))}
                  </TableRow>
                ))}
              </TableHeader>
              <TableBody>
                {loading ? (
                  Array.from({ length: 5 }).map((_, i) => (
                    <TableRow key={i}>
                      {columns.map((_, j) => (
                        <TableCell key={j}>
                          <div className='h-4 w-full animate-pulse rounded bg-muted' />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : table.getRowModel().rows.length ? (
                  table.getRowModel().rows.map((row) => (
                    <TableRow key={row.id}>
                      {row.getVisibleCells().map((cell) => (
                        <TableCell key={cell.id}>
                          {flexRender(cell.column.columnDef.cell, cell.getContext())}
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={columns.length} className='h-24 text-center'>
                      No orders found.
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>

          {meta && meta.last_page > 1 && (
            <OrdersPagination
              meta={meta}
              onPageChange={(page) => updateFilters({ page })}
              onPageSizeChange={(perPage) => updateFilters({ per_page: perPage, page: 1 })}
            />
          )}
        </div>
      </Main>

      <PortalOrdersImportDialog
        open={importDialogOpen}
        onOpenChange={setImportDialogOpen}
        onSuccess={refresh}
      />
    </>
  )
}
