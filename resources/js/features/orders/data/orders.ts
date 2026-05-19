import { faker } from '@faker-js/faker'

faker.seed(54321)

export const orders = Array.from({ length: 100 }, () => {
  const statuses = [
    'pending',
    'processing',
    'shipped',
    'delivered',
    'canceled',
    'refunded',
  ] as const
  const labels = ['wholesale', 'retail'] as const
  const priorities = ['low', 'medium', 'high', 'urgent'] as const

  return {
    id: `ORD-${faker.number.int({ min: 10000, max: 99999 })}`,
    title: faker.commerce.productName(),
    status: faker.helpers.arrayElement(statuses),
    label: faker.helpers.arrayElement(labels),
    priority: faker.helpers.arrayElement(priorities),
    customer: faker.person.fullName(),
    total: parseFloat(faker.commerce.price({ min: 10, max: 500 })),
    createdAt: faker.date.past(),
    updatedAt: faker.date.recent(),
  }
})
