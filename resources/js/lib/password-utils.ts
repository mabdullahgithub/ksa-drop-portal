export interface PasswordStrength {
  score: number // 0-4
  label: 'Very Weak' | 'Weak' | 'Fair' | 'Strong' | 'Very Strong'
  color: string
  percentage: number
  suggestions: string[]
}

export function checkPasswordStrength(password: string): PasswordStrength {
  if (!password) {
    return {
      score: 0,
      label: 'Very Weak',
      color: 'bg-red-500',
      percentage: 0,
      suggestions: ['Password is required'],
    }
  }

  let score = 0
  const suggestions: string[] = []

  // Length check
  if (password.length >= 8) score++
  if (password.length >= 12) score++
  else suggestions.push('Use at least 12 characters for better security')

  // Character variety checks
  const hasLowerCase = /[a-z]/.test(password)
  const hasUpperCase = /[A-Z]/.test(password)
  const hasNumbers = /\d/.test(password)
  const hasSpecialChars = /[^A-Za-z0-9]/.test(password)

  if (hasLowerCase && hasUpperCase) score++
  else suggestions.push('Include both uppercase and lowercase letters')

  if (hasNumbers) score++
  else suggestions.push('Add numbers')

  if (hasSpecialChars) score++
  else suggestions.push('Include special characters (!@#$%^&*)')

  // Penalty for common patterns
  const commonPatterns = ['12345', 'qwerty', 'password', 'admin', 'letmein']
  const lowerPassword = password.toLowerCase()
  if (commonPatterns.some((pattern) => lowerPassword.includes(pattern))) {
    score = Math.max(0, score - 2)
    suggestions.push('Avoid common words and patterns')
  }

  // Cap score at 4
  score = Math.min(4, score)

  const labels: PasswordStrength['label'][] = [
    'Very Weak',
    'Weak',
    'Fair',
    'Strong',
    'Very Strong',
  ]
  const colors = [
    'bg-red-500',
    'bg-orange-500',
    'bg-yellow-500',
    'bg-lime-500',
    'bg-green-500',
  ]

  return {
    score,
    label: labels[score],
    color: colors[score],
    percentage: (score / 4) * 100,
    suggestions: score < 4 ? suggestions : [],
  }
}

export function generateStrongPassword(length: number = 16): string {
  const lowercase = 'abcdefghijklmnopqrstuvwxyz'
  const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
  const numbers = '0123456789'
  const special = '!@#$%^&*()_+-=[]{}|;:,.<>?'

  const allChars = lowercase + uppercase + numbers + special

  // Ensure at least one character from each category
  let password = ''
  password += lowercase[Math.floor(Math.random() * lowercase.length)]
  password += uppercase[Math.floor(Math.random() * uppercase.length)]
  password += numbers[Math.floor(Math.random() * numbers.length)]
  password += special[Math.floor(Math.random() * special.length)]

  // Fill the rest randomly
  for (let i = password.length; i < length; i++) {
    password += allChars[Math.floor(Math.random() * allChars.length)]
  }

  // Shuffle the password
  return password
    .split('')
    .sort(() => Math.random() - 0.5)
    .join('')
}

export function isDefaultPassword(password: string): boolean {
  const defaultPatterns = [
    'password',
    'admin',
    '123456',
    'default',
    'changeme',
    'letmein',
    'welcome',
  ]
  const lowerPassword = password.toLowerCase()
  return defaultPatterns.some((pattern) => lowerPassword.includes(pattern))
}
