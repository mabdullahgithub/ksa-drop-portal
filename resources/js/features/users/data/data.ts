import { Shield, UserCheck, Users, CreditCard } from 'lucide-react'
import { type UserStatus } from './schema'

export const callTypes = new Map<UserStatus, string>([
  ['active', 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'],
  ['inactive', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'],
  ['invited', 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'],
  ['suspended', 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'],
])

export const roles = [
  {
    label: 'Superadmin',
    value: 'superadmin',
    icon: Shield,
  },
  {
    label: 'Admin',
    value: 'admin',
    icon: UserCheck,
  },
  {
    label: 'Manager',
    value: 'manager',
    icon: Users,
  },
  {
    label: 'Cashier',
    value: 'cashier',
    icon: CreditCard,
  },
] as const
