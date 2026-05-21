import { useState } from 'react'
import { toast } from 'sonner'
import { Link, router } from '@inertiajs/react'
import {
  ArrowLeft,
  Pencil,
  Trash2,
  UserCheck,
  UserX,
  Ban,
  MonitorSmartphone,
  Building2,
  MapPin,
  FileText,
  DollarSign,
  Receipt,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Package } from 'lucide-react'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePermissions } from '@/hooks/use-permissions'
import { useClientMutations } from '@/hooks/useClients'
import { ClientProvider } from './components/client-provider'
import { EditClientDialog } from './components/edit-client-dialog'
import { ClientInventoryTab } from './components/client-inventory-tab'
import type { Client } from '@/types/client'

interface ClientDetailPageProps {
  client: Client
}

export function ClientDetailPage({ client }: ClientDetailPageProps) {
  return (
    <ClientProvider>
      <ClientDetailContent client={client} />
    </ClientProvider>
  )
}

function ClientDetailContent({ client }: ClientDetailPageProps) {
  const { can } = usePermissions()
  const { updateStatus, deleteClient, loading } = useClientMutations()
  const [editOpen, setEditOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)

  const statusColorMap: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    inactive: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    suspended: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  }

  const handleStatusUpdate = async (status: string) => {
    const ok = await updateStatus(client.id, status)
    if (ok) {
      toast.success('Client status updated')
      router.reload()
    } else {
      toast.error('Failed to update status')
    }
  }

  const handleDelete = async () => {
    const ok = await deleteClient(client.id)
    if (ok) {
      toast.success('Client deleted')
      router.visit('/client')
    } else {
      toast.error('Failed to delete client')
    }
  }

  const handleImpersonate = () => {
    if (!client.user_id) {
      toast.error('This client has no associated user account.')
      return
    }
    router.post(route('impersonate.start', { client: client.id }))
  }

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ConfigDrawer />
        <ProfileDropdown />
      </Header>

      <Main>
        {/* Back navigation */}
        <div className='mb-4'>
          <Link
            href='/client'
            className='inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors'
          >
            <ArrowLeft className='h-4 w-4' />
            Back to Clients
          </Link>
        </div>

        {/* Page header */}
        <div className='mb-6 flex items-start justify-between gap-4'>
          <div className='flex items-center gap-3 flex-wrap'>
            <code className='rounded bg-muted px-2.5 py-1 text-base font-semibold'>
              {client.client_id}
            </code>
            <h1 className='text-2xl font-bold tracking-tight'>{client.company_name}</h1>
            <Badge variant='outline' className={`capitalize text-xs ${statusColorMap[client.status] ?? ''}`}>
              {client.status}
            </Badge>
            {client.client_types.map((type) => (
              <Badge key={type} variant='outline' className='capitalize text-xs'>
                {type}
              </Badge>
            ))}
          </div>

          <div className='flex shrink-0 items-center gap-2'>
            {can('impersonate client') && client.status === 'active' && (
              <Button
                variant='outline'
                size='sm'
                onClick={handleImpersonate}
                className='text-amber-600 border-amber-300 hover:bg-amber-50 dark:text-amber-400 dark:border-amber-800 dark:hover:bg-amber-950/40'
              >
                <MonitorSmartphone className='mr-2 h-4 w-4' />
                View Portal
              </Button>
            )}

            {can('edit client') && (
              <Button variant='outline' size='sm' onClick={() => setEditOpen(true)}>
                <Pencil className='mr-2 h-4 w-4' />
                Edit
              </Button>
            )}

            {can('edit client') && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant='outline' size='sm'>
                    <UserCheck className='mr-2 h-4 w-4' />
                    Status
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align='end'>
                  <DropdownMenuItem
                    onClick={() => handleStatusUpdate('active')}
                    disabled={client.status === 'active'}
                  >
                    <UserCheck className='mr-2 h-4 w-4' />
                    Activate
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    onClick={() => handleStatusUpdate('inactive')}
                    disabled={client.status === 'inactive'}
                  >
                    <UserX className='mr-2 h-4 w-4' />
                    Deactivate
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    onClick={() => handleStatusUpdate('suspended')}
                    disabled={client.status === 'suspended'}
                  >
                    <Ban className='mr-2 h-4 w-4' />
                    Suspend
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            )}

            {can('delete client') && (
              <Button
                variant='destructive'
                size='sm'
                onClick={() => setDeleteOpen(true)}
              >
                <Trash2 className='mr-2 h-4 w-4' />
                Delete
              </Button>
            )}
          </div>
        </div>

        {/* Portal features */}
        {(client.portal_features || []).length > 0 && (
          <div className='mb-6 flex flex-wrap items-center gap-1.5'>
            <span className='text-xs text-muted-foreground mr-1'>Portal access:</span>
            {client.portal_features.map((f) => (
              <Badge key={f} variant='secondary' className='text-xs capitalize'>{f}</Badge>
            ))}
          </div>
        )}

        {/* Tabs */}
        <Tabs defaultValue='overview'>
          <TabsList>
            <TabsTrigger value='overview'>Overview</TabsTrigger>
            {client.is_fulfilment && (
              <TabsTrigger value='inventory'>
                <Package className='mr-1.5 h-4 w-4' />
                Inventory
              </TabsTrigger>
            )}
          </TabsList>

          <TabsContent value='overview' className='mt-6 space-y-6'>
            {/* Contact */}
            <section>
              <h2 className='font-semibold mb-3 flex items-center gap-2 text-sm uppercase tracking-wide text-muted-foreground'>
                <Building2 className='h-4 w-4' />
                Contact Information
              </h2>
              <div className='grid grid-cols-2 md:grid-cols-4 gap-4 text-sm'>
                <InfoRow label='Contact Person' value={client.contact_person} />
                <InfoRow label='Email' value={client.user?.email} />
                <InfoRow label='Phone' value={client.phone} />
                <InfoRow label='Secondary Phone' value={client.secondary_phone} />
              </div>
            </section>

            <Separator />

            {/* Address */}
            <section>
              <h2 className='font-semibold mb-3 flex items-center gap-2 text-sm uppercase tracking-wide text-muted-foreground'>
                <MapPin className='h-4 w-4' />
                Address
              </h2>
              <div className='grid grid-cols-2 md:grid-cols-4 gap-4 text-sm'>
                <InfoRow label='Address' value={client.address} />
                <InfoRow label='City' value={client.city} />
                <InfoRow label='Country' value={client.country} />
                <InfoRow label='Postal Code' value={client.postal_code} />
              </div>
            </section>

            <Separator />

            {/* Business */}
            <section>
              <h2 className='font-semibold mb-3 flex items-center gap-2 text-sm uppercase tracking-wide text-muted-foreground'>
                <FileText className='h-4 w-4' />
                Business Details
              </h2>
              <div className='grid grid-cols-2 gap-4 text-sm'>
                <InfoRow label='Tax ID (VAT)' value={client.tax_id} />
                <InfoRow label='Commercial Registration' value={client.commercial_registration} />
              </div>
            </section>

            {/* Charges */}
            {client.charges && Object.values(client.charges).some((v) => v != null) && (
              <>
                <Separator />
                <section>
                  <h2 className='font-semibold mb-3 flex items-center gap-2 text-sm uppercase tracking-wide text-muted-foreground'>
                    <Receipt className='h-4 w-4' />
                    Client Charges
                  </h2>
                  <div className='grid grid-cols-2 md:grid-cols-4 gap-4 text-sm'>
                    <InfoRow label='Delivery' value={client.charges.delivery != null ? `SAR ${client.charges.delivery}` : null} />
                    <InfoRow label='Return' value={client.charges.return != null ? `SAR ${client.charges.return}` : null} />
                    <InfoRow label='COD' value={client.charges.cod != null ? `SAR ${client.charges.cod}` : null} />
                    <InfoRow label='Warehousing' value={client.charges.warehousing != null ? `SAR ${client.charges.warehousing}` : null} />
                    <InfoRow label='Call Confirmation' value={client.charges.call_confirmation != null ? `SAR ${client.charges.call_confirmation}` : null} />
                    <InfoRow label='VAT' value={client.charges.vat != null ? `${client.charges.vat}%` : null} />
                    <InfoRow label='Other' value={client.charges.other != null ? `SAR ${client.charges.other}` : null} />
                  </div>
                </section>
              </>
            )}

            {/* Stats */}
            <Separator />
            <section>
              <h2 className='font-semibold mb-3 flex items-center gap-2 text-sm uppercase tracking-wide text-muted-foreground'>
                <DollarSign className='h-4 w-4' />
                Statistics
              </h2>
              <div className='grid grid-cols-2 md:grid-cols-4 gap-4'>
                <StatCard label='Total Orders' value={String(client.orders_count ?? 0)} />
                <StatCard label='Total Revenue' value={`SAR ${(client.total_revenue ?? 0).toLocaleString()}`} />
                <StatCard label='Products' value={String(client.products_count ?? 0)} />
                <StatCard label='Verified Products' value={String(client.verified_products_count ?? 0)} />
              </div>
            </section>

            {client.notes && (
              <>
                <Separator />
                <section>
                  <div className='text-sm font-medium text-muted-foreground mb-1'>Notes</div>
                  <p className='text-sm whitespace-pre-wrap'>{client.notes}</p>
                </section>
              </>
            )}

            {/* Meta */}
            <Separator />
            <div className='flex flex-wrap gap-6 text-xs text-muted-foreground'>
              {client.creator && (
                <span>Created by <strong>{client.creator.name}</strong></span>
              )}
              {client.created_at && (
                <span>Created <strong>{new Date(client.created_at).toLocaleDateString()}</strong></span>
              )}
            </div>
          </TabsContent>

          {client.is_fulfilment && (
            <TabsContent value='inventory' className='mt-6'>
              <ClientInventoryTab client={client} />
            </TabsContent>
          )}
        </Tabs>
      </Main>

      {/* Edit dialog */}
      <EditClientDialog
        client={client}
        open={editOpen}
        onOpenChange={setEditOpen}
        onSuccess={() => router.reload()}
      />

      {/* Delete confirm */}
      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Client</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete <strong>{client.company_name}</strong>? This action cannot be undone. The associated user account will remain but will lose client access.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={loading}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={loading}
              className='bg-destructive text-destructive-foreground hover:bg-destructive/90'
            >
              {loading ? 'Deleting...' : 'Delete'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

function InfoRow({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <div className='text-muted-foreground text-xs mb-0.5'>{label}</div>
      <div className='font-medium'>{value || '—'}</div>
    </div>
  )
}

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <div className='rounded-lg border p-4 text-center'>
      <div className='text-2xl font-bold'>{value}</div>
      <div className='text-xs text-muted-foreground mt-1'>{label}</div>
    </div>
  )
}
