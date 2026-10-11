import { useEffect, useState } from 'react'
import { format, formatDistanceToNowStrict, parseISO } from 'date-fns'
import {
  AlertTriangle,
  Ban,
  CheckCircle,
  ChevronLeft,
  ChevronRight,
  Clock,
  RefreshCw,
  Search as SearchIcon,
  XCircle,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { TableCell, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { EmptyState } from '@/components/empty-state'
import { SearchBeam } from '@/components/search-beam'
import type { RequestClient, RequestFilters, RequestMeta } from '@/hooks/useInventoryRequests'
import type { InventoryRequest, InventoryRequestStatus } from '@/types/client'

const STATUS_STYLE: Record<InventoryRequestStatus, { label: string; className: string; icon: React.ElementType }> = {
  pending: { label: 'Pending', className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', icon: Clock },
  approved: { label: 'Approved', className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400', icon: CheckCircle },
  rejected: { label: 'Rejected', className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', icon: XCircle },
  cancelled: { label: 'Cancelled', className: 'bg-muted text-muted-foreground', icon: Ban },
}

export function RequestStatusBadge({ status }: { status: InventoryRequestStatus }) {
  const { label, className, icon: Icon } = STATUS_STYLE[status]

  return (
    <Badge variant='outline' className={`text-xs ${className}`}>
      <Icon className='mr-1 h-3 w-3' />
      {label}
    </Badge>
  )
}

/**
 * When something was asked, and how long it has been waiting: the queue is
 * worked oldest first, so the age is what the eye looks for.
 */
export function RequestedAt({ value, by, waiting }: { value: string | null; by?: string | null; waiting?: boolean }) {
  if (!value) return <span className='text-muted-foreground'>—</span>

  const date = parseISO(value)

  return (
    <div className='text-sm whitespace-nowrap'>
      <span title={format(date, 'PPpp')}>{format(date, 'd MMM yyyy')}</span>
      <div className='text-xs text-muted-foreground'>
        {waiting ? `waiting ${formatDistanceToNowStrict(date)}` : format(date, 'p')}
        {by ? ` · ${by}` : ''}
      </div>
    </div>
  )
}

export function ProductCell({ product }: { product: InventoryRequest['product'] }) {
  if (!product) return <span className='text-muted-foreground'>—</span>

  return (
    <div className='min-w-0'>
      <div className='font-medium'>
        {product.name}
        {product.deleted && <span className='ml-1.5 text-xs font-normal text-muted-foreground'>(deleted)</span>}
      </div>
      <div className='mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground'>
        <code className='rounded bg-muted px-1.5 py-0.5 font-semibold text-foreground'>{product.product_code}</code>
        {product.sku && <span>SKU: {product.sku}</span>}
      </div>
    </div>
  )
}

/** "+200" in green, "−20" in red. */
export function StockChange({ value }: { value: number | null }) {
  if (value === null) return null

  return (
    <span className={value < 0 ? 'font-semibold text-red-600 dark:text-red-400' : 'font-semibold text-green-600 dark:text-green-400'}>
      {value > 0 ? '+' : '−'}
      {Math.abs(value)}
    </span>
  )
}

type ToolbarProps = {
  filters: RequestFilters
  onChange: (changed: Partial<RequestFilters>) => void
  clients: RequestClient[]
  /** value => label; the queue's own statuses. */
  statuses: Record<string, string>
  onRefresh: () => void
  loading: boolean
}

/**
 * The same three filters above every queue: search, client and status.
 */
export function RequestsToolbar({ filters, onChange, clients, statuses, onRefresh, loading }: ToolbarProps) {
  const [search, setSearch] = useState(filters.search)

  // Typing searches after a pause, not on every key.
  useEffect(() => {
    if (search === filters.search) return
    const timer = setTimeout(() => onChange({ search }), 350)
    return () => clearTimeout(timer)
  }, [search, filters.search, onChange])

  return (
    <div className='flex flex-wrap items-center gap-2'>
      <div className='relative'>
        <SearchIcon className='absolute left-2.5 top-2.5 z-10 h-4 w-4 text-muted-foreground' />
        <SearchBeam>
          <Input
            placeholder='Search product, code, SKU or client…'
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className='h-9 w-[240px] pl-8 lg:w-[300px]'
          />
        </SearchBeam>
      </div>

      <Select value={filters.client_id} onValueChange={(value) => onChange({ client_id: value })}>
        <SelectTrigger className='h-9 w-[200px]'>
          <SelectValue placeholder='All clients' />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value='all'>All clients</SelectItem>
          {clients.map((client) => (
            <SelectItem key={client.id} value={String(client.id)}>
              {client.company_name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Select value={filters.status} onValueChange={(value) => onChange({ status: value })}>
        <SelectTrigger className='h-9 w-[150px]'>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {Object.entries(statuses).map(([value, label]) => (
            <SelectItem key={value} value={value}>
              {label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Button variant='outline' size='icon' className='h-9 w-9' onClick={onRefresh} disabled={loading} aria-label='Refresh'>
        <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
      </Button>
    </div>
  )
}

/** The statuses of a stock or details request, for the toolbar. */
export const REQUEST_STATUSES: Record<string, string> = {
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
  all: 'All',
}

type QueueRowsProps = {
  loading: boolean
  error: boolean
  empty: boolean
  columns: number
  /** Whether the queue is showing what is waiting, or the history. */
  waiting: boolean
  onRetry: () => void
  children: React.ReactNode
}

/**
 * A queue's table body: its rows, or what stands in for them while loading,
 * on a failure, or when there is nothing to show.
 */
export function QueueRows({ loading, error, empty, columns, waiting, onRetry, children }: QueueRowsProps) {
  if (loading) {
    return Array.from({ length: 5 }).map((_, row) => (
      <TableRow key={row}>
        {Array.from({ length: columns }).map((_, cell) => (
          <TableCell key={cell}>
            <div className='h-4 w-full animate-pulse rounded bg-muted' />
          </TableCell>
        ))}
      </TableRow>
    ))
  }

  if (error) {
    return (
      <TableRow>
        <TableCell colSpan={columns} className='h-32 text-center'>
          <div className='flex flex-col items-center gap-3'>
            <AlertTriangle className='h-7 w-7 text-muted-foreground' />
            <p className='text-sm text-muted-foreground'>Failed to load requests.</p>
            <Button variant='outline' size='sm' onClick={onRetry}>
              <RefreshCw className='mr-2 h-4 w-4' />
              Try Again
            </Button>
          </div>
        </TableCell>
      </TableRow>
    )
  }

  if (empty) {
    return (
      <TableRow>
        <TableCell colSpan={columns} className='h-24 text-center'>
          <EmptyState
            bot='square'
            state='sleeping'
            title={waiting ? 'Nothing is waiting.' : 'No requests match these filters.'}
            description={waiting ? 'New requests from clients will show up here.' : undefined}
          />
        </TableCell>
      </TableRow>
    )
  }

  return <>{children}</>
}

export function RequestsPager({ meta, onPage }: { meta: RequestMeta | null; onPage: (page: number) => void }) {
  if (!meta || meta.total === 0) return null

  return (
    <div className='flex items-center justify-between gap-2 text-sm text-muted-foreground'>
      <span>
        Showing {meta.from} to {meta.to} of {meta.total}
      </span>
      {meta.last_page > 1 && (
        <div className='flex items-center gap-2'>
          <Button variant='outline' size='icon' className='h-8 w-8' disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)} aria-label='Previous page'>
            <ChevronLeft className='h-4 w-4' />
          </Button>
          <span>
            Page {meta.current_page} of {meta.last_page}
          </span>
          <Button variant='outline' size='icon' className='h-8 w-8' disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)} aria-label='Next page'>
            <ChevronRight className='h-4 w-4' />
          </Button>
        </div>
      )}
    </div>
  )
}

type RejectDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description: React.ReactNode
  /** Resolves when the rejection went through; the dialog then closes. */
  onConfirm: (reason: string) => Promise<void>
}

/**
 * The reason a request is turned down. The client reads it, so it is required.
 */
export function RejectDialog({ open, onOpenChange, title, description, onConfirm }: RejectDialogProps) {
  const [reason, setReason] = useState('')
  const [sending, setSending] = useState(false)

  useEffect(() => {
    if (open) setReason('')
  }, [open])

  const submit = async () => {
    if (sending || !reason.trim()) return
    setSending(true)
    try {
      await onConfirm(reason.trim())
    } finally {
      setSending(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !sending && onOpenChange(next)}>
      <DialogContent className='max-w-md'>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription asChild>
            <div>{description}</div>
          </DialogDescription>
        </DialogHeader>

        <div className='space-y-2'>
          <Label htmlFor='request-reject-reason'>
            Reason <span className='text-destructive'>*</span>
            <span className='ml-1 text-xs font-normal text-muted-foreground'>(the client sees this)</span>
          </Label>
          <Textarea
            id='request-reject-reason'
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder='Explain why this is not approved…'
            rows={3}
            maxLength={1000}
            disabled={sending}
            autoFocus
          />
        </div>

        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)} disabled={sending}>
            Cancel
          </Button>
          <Button variant='destructive' onClick={submit} disabled={sending || !reason.trim()}>
            {sending ? 'Rejecting…' : 'Reject'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
