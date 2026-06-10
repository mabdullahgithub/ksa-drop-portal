import { Head, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Tags as TagsFeature } from '@/features/tags'
import { type Tag } from '@/features/tags/data/schema'
import { type PageProps } from '@/types'

type Props = PageProps<{ tags: Tag[] }>

export default function Tags() {
  const { tags } = usePage<Props>().props

  return (
    <AuthenticatedLayout>
      <Head title='Tags' />
      <TagsFeature tags={tags} />
    </AuthenticatedLayout>
  )
}
