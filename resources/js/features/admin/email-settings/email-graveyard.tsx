import { useState } from 'react'
import { router } from '@inertiajs/react'
import { RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { EmptyState } from '@/components/empty-state'
import { usePermissions } from '@/hooks/use-permissions'

interface GraveyardEntry {
  id: number
  email: string
  source: 'smtp' | 'bounce'
  status_code?: string | null
  reason?: string | null
  blocked_count: number
  created_at: string
}

const sourceLabels: Record<GraveyardEntry['source'], string> = {
  smtp: 'Rejected while sending',
  bounce: 'Bounced',
}

export function EmailGraveyard({ entries }: { entries: GraveyardEntry[] }) {
  const canRestore = usePermissions().can('restore graveyard emails')
  const [restoringId, setRestoringId] = useState<number | null>(null)

  const restore = (entry: GraveyardEntry) => {
    setRestoringId(entry.id)

    router.delete(route('admin.email-graveyard.restore', entry.id), {
      preserveScroll: true,
      onSuccess: () => toast.success(`${entry.email} can receive email again`),
      onError: () => toast.error('Failed to restore the address'),
      onFinish: () => setRestoringId(null),
    })
  }

  const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  if (!entries || entries.length === 0) {
    return (
      <EmptyState
        bot='cloud'
        state='sleeping'
        title='The graveyard is empty'
        description='Addresses that turn out not to exist are moved here, and no email is sent to them again'
        className='py-12'
      />
    )
  }

  return (
    <div className='rounded-md border'>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Address</TableHead>
            <TableHead>How it was found</TableHead>
            <TableHead>What the mail server said</TableHead>
            <TableHead>Emails stopped</TableHead>
            <TableHead>Added</TableHead>
            <TableHead className='text-right'>Action</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {entries.map((entry) => (
            <TableRow key={entry.id}>
              <TableCell className='font-medium'>{entry.email}</TableCell>
              <TableCell>
                <Badge variant='secondary'>{sourceLabels[entry.source] ?? entry.source}</Badge>
              </TableCell>
              <TableCell className='max-w-xs truncate text-muted-foreground' title={entry.reason ?? undefined}>
                {entry.reason || entry.status_code || '—'}
              </TableCell>
              <TableCell>{entry.blocked_count}</TableCell>
              <TableCell className='text-muted-foreground'>{formatDate(entry.created_at)}</TableCell>
              <TableCell className='text-right'>
                {canRestore && (
                  <Button
                    variant='outline'
                    size='sm'
                    disabled={restoringId === entry.id}
                    onClick={() => restore(entry)}
                  >
                    <RotateCcw className='mr-1 h-3 w-3' />
                    Restore
                  </Button>
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
