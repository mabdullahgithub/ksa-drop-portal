import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { router } from '@inertiajs/react'
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
import { Textarea } from '@/components/ui/textarea'
import { type Tag, PREDEFINED_COLORS } from '../data/schema'
import { cn } from '@/lib/utils'

const formSchema = z.object({
  name:        z.string().min(1, 'Name is required.').max(255),
  color:       z.string().regex(/^#[0-9A-Fa-f]{6}$/, 'Must be a valid hex color.'),
  description: z.string().max(500).optional(),
})

type TagForm = z.infer<typeof formSchema>

type Props = {
  currentRow?: Tag
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function TagsActionDialog({ currentRow, open, onOpenChange }: Props) {
  const isEdit = !!currentRow

  const form = useForm<TagForm>({
    resolver: zodResolver(formSchema),
    defaultValues: isEdit
      ? { name: currentRow.name, color: currentRow.color, description: currentRow.description ?? '' }
      : { name: '', color: '#6366f1', description: '' },
  })

  // Sync form when currentRow changes
  const watchedColor = form.watch('color')

  const onSubmit = (values: TagForm) => {
    const payload = {
      name:        values.name,
      color:       values.color,
      description: values.description || null,
    }

    if (isEdit) {
      router.put(route('tags.update', currentRow.id), payload, {
        onSuccess: () => { form.reset(); onOpenChange(false) },
        onError:   (errors) => {
          if (errors.name) form.setError('name', { message: errors.name })
        },
      })
    } else {
      router.post(route('tags.store'), payload, {
        onSuccess: () => { form.reset(); onOpenChange(false) },
        onError:   (errors) => {
          if (errors.name) form.setError('name', { message: errors.name })
        },
      })
    }
  }

  return (
    <Dialog open={open} onOpenChange={(state) => { form.reset(); onOpenChange(state) }}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Tag' : 'Add New Tag'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'Update the tag details below.' : 'Create a new tag to organize your data.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form id='tag-form' onSubmit={form.handleSubmit(onSubmit)} className='space-y-4'>
            <FormField
              control={form.control}
              name='name'
              render={({ field }) => (
                <FormItem className='grid grid-cols-6 items-center space-y-0 gap-x-4 gap-y-1'>
                  <FormLabel className='col-span-2 text-end'>Name</FormLabel>
                  <FormControl>
                    <Input placeholder='e.g. VIP' className='col-span-4' autoComplete='off' {...field} />
                  </FormControl>
                  <FormMessage className='col-span-4 col-start-3' />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='color'
              render={({ field }) => (
                <FormItem className='grid grid-cols-6 items-start space-y-0 gap-x-4 gap-y-1'>
                  <FormLabel className='col-span-2 text-end pt-2'>Color</FormLabel>
                  <div className='col-span-4 space-y-2'>
                    <div className='flex flex-wrap gap-2'>
                      {PREDEFINED_COLORS.map((c) => (
                        <button
                          key={c.value}
                          type='button'
                          title={c.label}
                          onClick={() => field.onChange(c.value)}
                          className={cn(
                            'h-6 w-6 rounded-full ring-offset-background transition-all',
                            field.value === c.value
                              ? 'ring-2 ring-ring ring-offset-2 scale-110'
                              : 'hover:scale-110'
                          )}
                          style={{ backgroundColor: c.value }}
                        />
                      ))}
                    </div>
                    <p className='text-xs text-muted-foreground'>
                      Pick a preset or click the swatch to choose a custom color.
                    </p>
                    <div className='flex items-center gap-2'>
                      <label
                        className='relative h-8 w-8 flex-shrink-0 cursor-pointer rounded-md border border-input overflow-hidden'
                        title='Click to open color picker'
                      >
                        <span
                          className='absolute inset-0'
                          style={{ backgroundColor: watchedColor }}
                        />
                        <input
                          type='color'
                          value={watchedColor}
                          onChange={(e) => field.onChange(e.target.value)}
                          className='absolute inset-0 h-full w-full cursor-pointer opacity-0'
                        />
                      </label>
                      <Input
                        {...field}
                        placeholder='#6366f1'
                        className='font-mono text-sm'
                        maxLength={7}
                      />
                    </div>
                  </div>
                  <FormMessage className='col-span-4 col-start-3' />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='description'
              render={({ field }) => (
                <FormItem className='grid grid-cols-6 items-start space-y-0 gap-x-4 gap-y-1'>
                  <FormLabel className='col-span-2 text-end pt-2'>Description</FormLabel>
                  <FormControl>
                    <Textarea
                      placeholder='Optional description...'
                      className='col-span-4 resize-none'
                      rows={3}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage className='col-span-4 col-start-3' />
                </FormItem>
              )}
            />
          </form>
        </Form>

        <DialogFooter>
          <Button type='submit' form='tag-form'>
            {isEdit ? 'Save changes' : 'Create tag'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
