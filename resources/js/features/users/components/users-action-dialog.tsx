'use client'

import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import { celebrate } from '@/lib/confetti'
import { showSubmittedData } from '@/lib/show-submitted-data'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { RoleSelectWithAdd } from './role-select-with-add'
import { roles as defaultRoles } from '../data/data'
import { type User } from '../data/schema'
import { usePermissions } from '@/hooks/use-permissions'
import { type PageProps } from '@/types'
import { type AssignableClient, type ClientAccessMode, ClientAccessField } from './client-access-field'

const formSchema = z.object({
  firstName: z.string().min(1, 'First Name is required.'),
  lastName: z.string().min(1, 'Last Name is required.'),
  username: z.string().min(1, 'Username is required.'),
  phoneNumber: z.string().min(1, 'Phone number is required.'),
  email: z.email({
    error: (iss) => (iss.input === '' ? 'Email is required.' : undefined),
  }),
  // Empty when the person adding the user may not hand out roles.
  role: z.string(),
  isEdit: z.boolean(),
})
type UserForm = z.infer<typeof formSchema>

type UserActionDialogProps = {
  currentRow?: User
  open: boolean
  onOpenChange: (open: boolean) => void
  availableRoles?: string[]
}

export function UsersActionDialog({
  currentRow,
  open,
  onOpenChange,
  availableRoles,
}: UserActionDialogProps) {
  const isEdit = !!currentRow

  // Map available roles from backend to format expected by SelectDropdown
  const roleOptions = availableRoles
    ? availableRoles.map((roleName) => {
        const roleData = defaultRoles.find((r) => r.value === roleName)
        return {
          label: roleData?.label || roleName.charAt(0).toUpperCase() + roleName.slice(1),
          value: roleName,
        }
      })
    : defaultRoles.map(({ label, value }) => ({ label, value }))

  const { can } = usePermissions()
  const canRoles = can('assign user roles')
  const canClients = can('assign client access')
  const { clients = [], canGrantAllClients = true } = usePage<PageProps<{ clients?: AssignableClient[]; canGrantAllClients?: boolean }>>().props

  // Someone limited to their own clients cannot hand out "all".
  const [clientAccess, setClientAccess] = useState<ClientAccessMode>(canGrantAllClients ? 'all' : 'assigned')
  const [clientIds, setClientIds] = useState<number[]>([])
  const [clientError, setClientError] = useState<string>()

  const form = useForm<UserForm>({
    resolver: zodResolver(formSchema),
    defaultValues: isEdit
      ? {
          ...currentRow,
          isEdit,
        }
      : {
          firstName: '',
          lastName: '',
          username: '',
          email: '',
          role: '',
          phoneNumber: '',
          isEdit,
        },
  })

  const onSubmit = (values: UserForm) => {
    if (isEdit) {
      // For edit, just show submitted data as before (role update handled separately)
      form.reset()
      showSubmittedData(values)
      onOpenChange(false)
    } else {
      // For create, submit to backend (password will be auto-generated)
      const name = `${values.firstName} ${values.lastName}`.trim()

      router.post(
        route('team-management.users.store'),
        {
          name,
          email: values.email,
          ...(canRoles && values.role ? { roles: [values.role] } : {}),
          ...(canClients ? { client_access: clientAccess, client_ids: clientIds } : {}),
        },
        {
          onSuccess: () => {
            form.reset()
            onOpenChange(false)
            celebrate()
          },
          onError: (errors) => {
            setClientError(errors.client_access ?? errors.client_ids)
            if (errors.email) form.setError('email', { message: errors.email })
          },
        }
      )
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(state) => {
        form.reset()
        onOpenChange(state)
      }}
    >
      <DialogContent className='sm:max-w-lg max-h-[90vh] flex flex-col p-0'>
        <DialogHeader className='text-start px-6 pt-6 pb-4 flex-shrink-0'>
          <DialogTitle>{isEdit ? 'Edit User' : 'Add New User'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'Update the user here. ' : 'Create new user here. '}
            Click save when you&apos;re done.
          </DialogDescription>
        </DialogHeader>
        <div className='overflow-y-auto px-6 flex-1'>
          <Form {...form}>
            <form
              id='user-form'
              onSubmit={form.handleSubmit(onSubmit)}
              className='space-y-4 pb-4'
            >
              <FormField
                control={form.control}
                name='firstName'
                render={({ field }) => (
                  <FormItem className='grid grid-cols-6 items-center space-y-0 gap-x-4 gap-y-1'>
                    <FormLabel className='col-span-2 text-end'>
                      First Name
                    </FormLabel>
                    <FormControl>
                      <Input
                        placeholder='John'
                        className='col-span-4'
                        autoComplete='off'
                        {...field}
                      />
                    </FormControl>
                    <FormMessage className='col-span-4 col-start-3' />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name='lastName'
                render={({ field }) => (
                  <FormItem className='grid grid-cols-6 items-center space-y-0 gap-x-4 gap-y-1'>
                    <FormLabel className='col-span-2 text-end'>
                      Last Name
                    </FormLabel>
                    <FormControl>
                      <Input
                        placeholder='Doe'
                        className='col-span-4'
                        autoComplete='off'
                        {...field}
                      />
                    </FormControl>
                    <FormMessage className='col-span-4 col-start-3' />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name='username'
                render={({ field }) => (
                  <FormItem className='grid grid-cols-6 items-center space-y-0 gap-x-4 gap-y-1'>
                    <FormLabel className='col-span-2 text-end'>
                      Username
                    </FormLabel>
                    <FormControl>
                      <Input
                        placeholder='john_doe'
                        className='col-span-4'
                        {...field}
                      />
                    </FormControl>
                    <FormMessage className='col-span-4 col-start-3' />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name='email'
                render={({ field }) => (
                  <FormItem className='grid grid-cols-6 items-center space-y-0 gap-x-4 gap-y-1'>
                    <FormLabel className='col-span-2 text-end'>Email</FormLabel>
                    <FormControl>
                      <Input
                        placeholder='john.doe@gmail.com'
                        className='col-span-4'
                        {...field}
                      />
                    </FormControl>
                    <FormMessage className='col-span-4 col-start-3' />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name='phoneNumber'
                render={({ field }) => (
                  <FormItem className='grid grid-cols-6 items-center space-y-0 gap-x-4 gap-y-1'>
                    <FormLabel className='col-span-2 text-end'>
                      Phone Number
                    </FormLabel>
                    <FormControl>
                      <Input
                        placeholder='+123456789'
                        className='col-span-4'
                        {...field}
                      />
                    </FormControl>
                    <FormMessage className='col-span-4 col-start-3' />
                  </FormItem>
                )}
              />
              {canRoles && (
                <FormField
                  control={form.control}
                  name='role'
                  render={({ field }) => (
                    <FormItem className='grid grid-cols-6 items-center space-y-0 gap-x-4 gap-y-1'>
                      <FormLabel className='col-span-2 text-end'>Role</FormLabel>
                      <div className='col-span-4'>
                        <RoleSelectWithAdd
                          value={field.value}
                          onValueChange={field.onChange}
                          placeholder='Select a role'
                          items={roleOptions}
                        />
                      </div>
                      <FormMessage className='col-span-4 col-start-3' />
                    </FormItem>
                  )}
                />
              )}
              {!isEdit && canClients && (
                <ClientAccessField
                  mode={clientAccess}
                  clientIds={clientIds}
                  onChange={(mode, ids) => {
                    setClientAccess(mode)
                    setClientIds(ids)
                  }}
                  clients={clients}
                  canGrantAll={canGrantAllClients}
                  error={clientError}
                />
              )}
              {!isEdit && (
                <div className='col-span-6 rounded-md bg-muted/50 p-4 text-sm text-muted-foreground'>
                  <p className='font-medium text-foreground mb-1'>
                    Auto-generated Password
                  </p>
                  <p>
                    A secure password will be automatically generated for this user.
                    The user will receive an email with their login credentials.
                  </p>
                </div>
              )}
            </form>
          </Form>
        </div>
        <DialogFooter className='px-6 py-4 border-t flex-shrink-0'>
          <Button type='submit' form='user-form'>
            Save changes
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
