import { Badge } from '@/components/ui/badge'
import { CheckCircle, XCircle, Clock } from 'lucide-react'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

interface EmailLog {
  id: number
  recipient_email: string
  subject: string
  email_type: string
  status: 'sent' | 'failed' | 'queued'
  error_message?: string
  sent_at?: string
  created_at: string
  user?: {
    name: string
    email: string
  }
}

export function RecentEmailLogs({ logs }: { logs: EmailLog[] }) {
  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'sent':
        return (
          <Badge variant='outline' className='text-xs bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'>
            <CheckCircle className='mr-1 h-3 w-3' />
            Sent
          </Badge>
        )
      case 'failed':
        return (
          <Badge variant='outline' className='text-xs bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'>
            <XCircle className='mr-1 h-3 w-3' />
            Failed
          </Badge>
        )
      case 'queued':
        return (
          <Badge variant='outline' className='text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'>
            <Clock className='mr-1 h-3 w-3' />
            Queued
          </Badge>
        )
      default:
        return <Badge variant='outline'>{status}</Badge>
    }
  }

  const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  if (!logs || logs.length === 0) {
    return (
      <div className='text-center py-12'>
        <p className='text-muted-foreground'>No emails sent yet</p>
        <p className='text-sm text-muted-foreground mt-1'>
          Email logs will appear here once emails are sent
        </p>
      </div>
    )
  }

  return (
    <div className='rounded-md border'>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Recipient</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Subject</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Date</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {logs.map((log) => (
            <TableRow key={log.id}>
              <TableCell>
                <div>
                  <div className='font-medium'>{log.user?.name || 'Unknown'}</div>
                  <div className='text-sm text-muted-foreground'>
                    {log.recipient_email}
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <Badge variant='secondary' className='capitalize'>
                  {log.email_type.replace(/_/g, ' ')}
                </Badge>
              </TableCell>
              <TableCell className='max-w-xs truncate'>
                {log.subject}
              </TableCell>
              <TableCell>{getStatusBadge(log.status)}</TableCell>
              <TableCell className='text-muted-foreground'>
                {log.sent_at ? formatDate(log.sent_at) : formatDate(log.created_at)}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
