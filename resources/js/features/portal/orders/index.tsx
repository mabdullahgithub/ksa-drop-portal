import { useState, useRef, useCallback } from 'react'
import { Download, Upload, CheckCircle2, AlertCircle, FileSpreadsheet, X, Package, DollarSign, Clock, TruckIcon, Copy, XCircle, ShoppingBag, PlusCircle, MessageSquare, HelpCircle } from 'lucide-react'
import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Progress } from '@/components/ui/progress'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuCheckboxItem,
} from '@/components/ui/dropdown-menu'
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
import { ShipmentStatusInfoModal } from '@/features/orders/components/shipment-status-info-modal'
import { PortalShipmentStatusCards } from './components/portal-shipment-status-cards'
import { PortalOrdersFilters } from './components/portal-orders-filters'
import { usePortalOrderFilterOptions } from '@/hooks/usePortal'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

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

// ─── stat card config ─────────────────────────────────────────────────────────

interface StatCard {
  label: string
  key: string
  icon: React.ElementType
  iconClass: string
  bgClass: string
  format?: 'number' | 'currency'
}

const STAT_CARDS: StatCard[] = [
  {
    label: 'Total Orders',
    key: 'total_orders',
    icon: ShoppingBag,
    iconClass: 'text-blue-600 dark:text-blue-400',
    bgClass: 'bg-blue-100 dark:bg-blue-900/30',
  },
  {
    label: 'Confirmed',
    key: 'confirmed_orders',
    icon: CheckCircle2,
    iconClass: 'text-green-600 dark:text-green-400',
    bgClass: 'bg-green-100 dark:bg-green-900/30',
  },
  {
    label: 'Fulfilled',
    key: 'fulfilled_orders',
    icon: TruckIcon,
    iconClass: 'text-purple-600 dark:text-purple-400',
    bgClass: 'bg-purple-100 dark:bg-purple-900/30',
  },
  {
    label: 'Pending',
    key: 'pending_orders',
    icon: Clock,
    iconClass: 'text-yellow-600 dark:text-yellow-400',
    bgClass: 'bg-yellow-100 dark:bg-yellow-900/30',
  },
  {
    label: 'Cancelled',
    key: 'cancelled_orders',
    icon: XCircle,
    iconClass: 'text-red-600 dark:text-red-400',
    bgClass: 'bg-red-100 dark:bg-red-900/30',
  },
  {
    label: 'Total Revenue',
    key: 'total_revenue',
    icon: DollarSign,
    iconClass: 'text-emerald-600 dark:text-emerald-400',
    bgClass: 'bg-emerald-100 dark:bg-emerald-900/30',
    format: 'currency',
  },
]

// ─── columns ─────────────────────────────────────────────────────────────────

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
  const [importNote, setImportNote] = useState('')
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
      const data = await importOrders(file, importNote)
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
    setImportNote('')
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

          {/* Import Note */}
          {!result && (
            <div className='space-y-1.5'>
              <Label htmlFor='import-note' className='text-sm font-medium flex items-center gap-1.5'>
                <MessageSquare className='h-3.5 w-3.5 text-muted-foreground' />
                Import Note
                <span className='text-xs text-muted-foreground font-normal'>(optional)</span>
              </Label>
              <Textarea
                id='import-note'
                placeholder='Add a note that will be saved to all imported orders, e.g. "Batch from June campaign"'
                value={importNote}
                onChange={(e) => setImportNote(e.target.value)}
                rows={2}
                maxLength={2000}
                className='resize-none text-sm'
                disabled={uploading}
              />
              {importNote.length > 0 && (
                <p className='text-xs text-muted-foreground text-right'>{importNote.length}/2000</p>
              )}
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

// ─── stat mini cards ──────────────────────────────────────────────────────────

function OrderStatCards({ stats, loading }: { stats: any; loading: boolean }) {
  if (loading) {
    return (
      <div className='grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6'>
        {Array.from({ length: 6 }).map((_, i) => (
          <Card key={i} className='border-muted/50'>
            <CardContent className='p-4 space-y-2'>
              <div className='flex items-center justify-between'>
                <Skeleton className='h-3 w-16' />
                <Skeleton className='h-8 w-8 rounded-lg' />
              </div>
              <Skeleton className='h-6 w-14' />
            </CardContent>
          </Card>
        ))}
      </div>
    )
  }

  if (!stats) return null

  return (
    <div className='grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6'>
      {STAT_CARDS.map((card) => {
        const Icon = card.icon
        const raw = stats[card.key] ?? 0
        const display =
          card.format === 'currency'
            ? `SAR ${Number(raw).toLocaleString(undefined, { maximumFractionDigits: 0 })}`
            : Number(raw).toLocaleString()

        return (
          <Card key={card.key} className='border-muted/50 transition-shadow hover:shadow-sm'>
            <CardContent className='p-4'>
              <div className='flex items-start justify-between gap-2 mb-2'>
                <p className='text-xs font-medium text-muted-foreground leading-tight'>
                  {card.label}
                </p>
                <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${card.bgClass}`}>
                  <Icon className={`h-4 w-4 ${card.iconClass}`} />
                </div>
              </div>
              <p className='text-lg font-bold tabular-nums leading-none'>{display}</p>
            </CardContent>
          </Card>
        )
      })}
    </div>
  )
}

// ─── create order dialog ─────────────────────────────────────────────────────

function CreateOrderDialog({
  open,
  onOpenChange,
  onSuccess,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}) {
  const { createOrder } = usePortalOrderMutations()
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const [form, setForm] = useState({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    notes: '',
    shipping_address1: '',
    shipping_city: '',
    shipping_country: 'SA',
    total: '',
    payment_method: '',
    lineitem_name: '',
    lineitem_quantity: '1',
    lineitem_price: '',
    lineitem_sku: '',
  })

  const set = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
    setErrors((prev) => { const e = { ...prev }; delete e[field]; return e })
  }

  const handleClose = () => {
    if (saving) return
    setForm({
      customer_name: '',
      customer_phone: '',
      customer_email: '',
      notes: '',
      shipping_address1: '',
      shipping_city: '',
      shipping_country: 'SA',
      total: '',
      payment_method: '',
      lineitem_name: '',
      lineitem_quantity: '1',
      lineitem_price: '',
      lineitem_sku: '',
    })
    setErrors({})
    onOpenChange(false)
  }

  const handleSubmit = async () => {
    const errs: Record<string, string> = {}
    if (!form.customer_name.trim()) errs.customer_name = 'Customer name is required.'
    if (!form.customer_phone.trim()) errs.customer_phone = 'Phone number is required.'
    if (Object.keys(errs).length) { setErrors(errs); return }

    setSaving(true)
    try {
      const payload: Record<string, unknown> = {
        customer_name: form.customer_name.trim(),
        customer_phone: form.customer_phone.trim(),
        customer_email: form.customer_email.trim() || undefined,
        notes: form.notes.trim() || undefined,
        shipping_address1: form.shipping_address1.trim() || undefined,
        shipping_city: form.shipping_city.trim() || undefined,
        shipping_country: form.shipping_country.trim() || undefined,
        total: form.total ? parseFloat(form.total) : undefined,
        payment_method: form.payment_method.trim() || undefined,
        lineitem_name: form.lineitem_name.trim() || undefined,
        lineitem_quantity: form.lineitem_name.trim() ? parseInt(form.lineitem_quantity || '1') : undefined,
        lineitem_price: form.lineitem_price ? parseFloat(form.lineitem_price) : undefined,
        lineitem_sku: form.lineitem_sku.trim() || undefined,
      }
      const result = await createOrder(payload)
      if (result?.success) {
        onSuccess?.()
        handleClose()
      } else if (result?.errors) {
        // Map laravel validation errors
        const mapped: Record<string, string> = {}
        Object.entries(result.errors as Record<string, string[]>).forEach(([k, v]) => { mapped[k] = v[0] })
        setErrors(mapped)
      } else {
        setErrors({ _global: result?.message || 'Failed to create order. Please try again.' })
      }
    } finally {
      setSaving(false)
    }
  }

  const field = (label: string, id: string, props: React.InputHTMLAttributes<HTMLInputElement> & { error?: string }) => {
    const { error, ...inputProps } = props
    return (
      <div className='space-y-1'>
        <Label htmlFor={id} className='text-xs font-medium'>{label}</Label>
        <Input id={id} className='h-8 text-sm' {...inputProps} />
        {error && <p className='text-xs text-destructive'>{error}</p>}
      </div>
    )
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className='sm:max-w-[560px] max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle className='flex items-center gap-2'>
            <PlusCircle className='h-5 w-5 text-primary' />
            Create Order
          </DialogTitle>
          <DialogDescription>
            Enter the order details below. Customer name and phone are required; all other fields are optional.
          </DialogDescription>
        </DialogHeader>

        {errors._global && (
          <Alert variant='destructive'>
            <AlertCircle className='h-4 w-4' />
            <AlertDescription>{errors._global}</AlertDescription>
          </Alert>
        )}

        <div className='space-y-4'>
          {/* Customer */}
          <div>
            <p className='text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2'>Customer</p>
            <div className='grid grid-cols-2 gap-3'>
              {field('Name *', 'co-name', {
                placeholder: 'Full name',
                value: form.customer_name,
                onChange: (e) => set('customer_name', e.target.value),
                error: errors.customer_name,
              })}
              {field('Phone *', 'co-phone', {
                placeholder: '+966 5XX XXX XXXX',
                value: form.customer_phone,
                onChange: (e) => set('customer_phone', e.target.value),
                error: errors.customer_phone,
              })}
            </div>
            <div className='mt-3'>
              {field('Email', 'co-email', {
                type: 'email',
                placeholder: 'customer@example.com',
                value: form.customer_email,
                onChange: (e) => set('customer_email', e.target.value),
                error: errors.customer_email,
              })}
            </div>
          </div>

          {/* Description / Note */}
          <div className='space-y-1'>
            <Label htmlFor='co-notes' className='text-xs font-medium flex items-center gap-1.5'>
              <MessageSquare className='h-3.5 w-3.5 text-muted-foreground' />
              Description / Note
            </Label>
            <Textarea
              id='co-notes'
              placeholder='Any relevant notes, special instructions, or a description for this order…'
              value={form.notes}
              onChange={(e) => set('notes', e.target.value)}
              rows={3}
              maxLength={5000}
              className='resize-none text-sm'
            />
            {form.notes.length > 0 && (
              <p className='text-xs text-muted-foreground text-right'>{form.notes.length}/5000</p>
            )}
          </div>

          {/* Shipping */}
          <div>
            <p className='text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2'>Shipping Address</p>
            <div className='space-y-3'>
              {field('Address', 'co-addr', {
                placeholder: 'Street address',
                value: form.shipping_address1,
                onChange: (e) => set('shipping_address1', e.target.value),
              })}
              <div className='grid grid-cols-2 gap-3'>
                {field('City', 'co-city', {
                  placeholder: 'City',
                  value: form.shipping_city,
                  onChange: (e) => set('shipping_city', e.target.value),
                })}
                {field('Country', 'co-country', {
                  placeholder: 'SA',
                  maxLength: 2,
                  value: form.shipping_country,
                  onChange: (e) => set('shipping_country', e.target.value.toUpperCase()),
                })}
              </div>
            </div>
          </div>

          {/* Order value */}
          <div>
            <p className='text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2'>Order Value</p>
            <div className='grid grid-cols-2 gap-3'>
              {field('Total (SAR)', 'co-total', {
                type: 'number',
                min: '0',
                step: '0.01',
                placeholder: '0.00',
                value: form.total,
                onChange: (e) => set('total', e.target.value),
              })}
              {field('Payment Method', 'co-payment', {
                placeholder: 'Cash on Delivery',
                value: form.payment_method,
                onChange: (e) => set('payment_method', e.target.value),
              })}
            </div>
          </div>

          {/* Line item */}
          <div>
            <p className='text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2'>Product / Line Item <span className='normal-case font-normal'>(optional)</span></p>
            <div className='space-y-3'>
              {field('Product Name', 'co-item-name', {
                placeholder: 'e.g. T-Shirt – Blue / L',
                value: form.lineitem_name,
                onChange: (e) => set('lineitem_name', e.target.value),
              })}
              <div className='grid grid-cols-3 gap-3'>
                {field('Qty', 'co-item-qty', {
                  type: 'number',
                  min: '1',
                  value: form.lineitem_quantity,
                  onChange: (e) => set('lineitem_quantity', e.target.value),
                })}
                {field('Price (SAR)', 'co-item-price', {
                  type: 'number',
                  min: '0',
                  step: '0.01',
                  placeholder: '0.00',
                  value: form.lineitem_price,
                  onChange: (e) => set('lineitem_price', e.target.value),
                })}
                {field('SKU', 'co-item-sku', {
                  placeholder: 'SKU-001',
                  value: form.lineitem_sku,
                  onChange: (e) => set('lineitem_sku', e.target.value),
                })}
              </div>
            </div>
          </div>
        </div>

        <div className='flex justify-end gap-2 pt-2'>
          <Button variant='outline' onClick={handleClose} disabled={saving}>Cancel</Button>
          <Button onClick={handleSubmit} disabled={saving}>
            {saving ? 'Creating…' : 'Create Order'}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

// ─── main component ───────────────────────────────────────────────────────────

export function PortalOrders() {
  const [search, setSearch] = useState('')
  const [importDialogOpen, setImportDialogOpen] = useState(false)
  const [createDialogOpen, setCreateDialogOpen] = useState(false)
  const [statusInfoModalOpen, setStatusInfoModalOpen] = useState(false)

  const { orders, meta, loading, filters, updateFilters, refresh } = usePortalOrders({
    per_page: 15,
    sort_by: 'created_at',
    sort_order: 'desc',
    has_shipment: false,
  })
  const { exportOrders } = usePortalOrderMutations()
  const { data: dashboardData } = usePortalDashboard()
  const { options: filterOptions, loading: filterOptionsLoading } = usePortalOrderFilterOptions()

  const table = useReactTable({
    data: orders,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: meta?.last_page ?? 0,
  })

  const activeTab = filters.has_shipment === true ? 'assigned' : filters.has_shipment === false ? 'unassigned' : 'unassigned'

  const handleSearch = (value: string) => {
    setSearch(value)
    updateFilters({ search: value, page: 1 })
  }

  const handleTabChange = (tab: 'unassigned' | 'assigned') => {
    updateFilters({
      has_shipment: tab === 'assigned',
      page: 1,
    })
  }

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        {/* Page header */}
        <div className='mb-4 flex items-center justify-between gap-4 flex-wrap'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>My Orders</h1>
            <p className='text-muted-foreground'>Track and manage all your orders</p>
          </div>
          <div className='flex items-center gap-2'>
            <Button
              size='sm'
              onClick={() => setCreateDialogOpen(true)}
            >
              <PlusCircle className='mr-2 h-4 w-4' />
              Create Order
            </Button>
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

        {/* Shipment Status Distribution */}
        <div className='mb-8'>
          <div className='flex items-center justify-between mb-3'>
            <h3 className='text-sm font-semibold'>Shipment Status Distribution</h3>
            <button
              onClick={() => setStatusInfoModalOpen(true)}
              className='inline-flex items-center gap-1.5 rounded-md border border-muted px-2.5 py-1.5 text-xs font-medium text-muted-foreground hover:bg-muted/50 transition-colors'
            >
              <HelpCircle className='h-3.5 w-3.5' />
              Info
            </button>
          </div>
          <PortalShipmentStatusCards
            onStatusClick={(status) => {
              updateFilters({ shipment_status: [status], has_shipment: true, page: 1 })
            }}
          />
        </div>

        {/* Tabs with counts + filters + search */}
        <div className='flex gap-2 border-b border-muted/50 mb-4'>
          <button
            onClick={() => handleTabChange('unassigned')}
            className={`px-4 py-2 text-sm font-medium transition-colors ${
              activeTab === 'unassigned'
                ? 'border-b-2 border-primary text-primary -mb-px'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            All Orders
            {dashboardData?.stats?.total_orders != null && (
              <span className='ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium tabular-nums'>
                {dashboardData.stats.total_orders}
              </span>
            )}
          </button>
          <button
            onClick={() => handleTabChange('assigned')}
            className={`px-4 py-2 text-sm font-medium transition-colors ${
              activeTab === 'assigned'
                ? 'border-b-2 border-primary text-primary -mb-px'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            Assigned to Courier
            {dashboardData?.stats?.assigned_orders != null && (
              <span className='ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium tabular-nums'>
                {dashboardData.stats.assigned_orders}
              </span>
            )}
          </button>
        </div>

        {/* Filters */}
        {filterOptionsLoading ? (
          <div className='h-9 flex items-center'>
            <Skeleton className='h-8 w-96' />
          </div>
        ) : (
          <PortalOrdersFilters
            filters={filters}
            onFiltersChange={updateFilters}
            filterOptions={filterOptions}
          />
        )}

        {/* Orders table section */}
        <div className='space-y-3'>

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
                    <TableCell colSpan={columns.length} className='h-32 text-center'>
                      <div className='flex flex-col items-center gap-2 text-muted-foreground'>
                        <Package className='h-8 w-8 text-muted-foreground/30' />
                        <p className='text-sm'>
                          {activeTab === 'assigned'
                            ? 'No orders assigned to courier found.'
                            : 'No orders found.'}
                        </p>
                      </div>
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
      <CreateOrderDialog
        open={createDialogOpen}
        onOpenChange={setCreateDialogOpen}
        onSuccess={refresh}
      />
      <ShipmentStatusInfoModal
        open={statusInfoModalOpen}
        onOpenChange={setStatusInfoModalOpen}
      />
    </>
  )
}
