/**
 * The password policy of the public signup page, checked live as the person
 * types. The server enforces the same rules (RegisteredUserController and
 * App\Rules\NotGuessablePassword): change both together.
 */
const COMMON = [
  'password',
  'passw0rd',
  'qwerty',
  '123456',
  'abc123',
  '111111',
  'letmein',
  'welcome',
  'admin',
  'iloveyou',
  'ksadrop',
]

export function checkSignupPassword(password: string, name: string, email: string) {
  const lower = password.toLowerCase()
  const personal = [...name.toLowerCase().split(/\s+/), email.toLowerCase().split('@')[0]].filter(
    (word) => word.length >= 4,
  )
  const length = [...password].length

  const rules = [
    { label: '12 to 72 characters', met: length >= 12 && length <= 72 },
    {
      label: 'Uppercase and lowercase letters',
      met: /\p{Ll}/u.test(password) && /\p{Lu}/u.test(password),
    },
    { label: 'At least one number', met: /\p{N}/u.test(password) },
    { label: 'At least one symbol', met: /[\p{Z}\p{S}\p{P}]/u.test(password) },
    {
      label: 'No common words, your name or email',
      met: password !== '' && ![...COMMON, ...personal].some((word) => lower.includes(word)),
    },
  ]

  const valid = rules.every((rule) => rule.met)
  const metCount = rules.filter((rule) => rule.met).length

  // 1-4. "Strong" and above only once every rule is met.
  let score = 0
  if (password) {
    if (valid) score = length >= 16 ? 4 : 3
    else score = metCount >= 4 ? 2 : 1
  }

  return { rules, valid, score, label: ['', 'Weak', 'Fair', 'Strong', 'Very strong'][score] }
}
