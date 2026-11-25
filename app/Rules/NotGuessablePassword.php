<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Refuses passwords built on a common word, or on the person's own name or
 * email. The signup page shows the same check live: keep it in step with
 * resources/js/lib/signup-password.ts.
 */
class NotGuessablePassword implements ValidationRule
{
    private const COMMON = ['password', 'passw0rd', 'qwerty', '123456', 'abc123', '111111', 'letmein', 'welcome', 'admin', 'iloveyou', 'ksadrop'];

    /** @param  array<int, mixed>  $personal  the name and email typed on the form */
    public function __construct(private array $personal = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = Str::lower((string) $value);

        if (Str::contains($password, [...self::COMMON, ...$this->personalWords()])) {
            $fail('Your password must not contain common words, your name or your email.');
        }
    }

    /** @return list<string> */
    private function personalWords(): array
    {
        [$name, $email] = array_pad(array_map(
            fn ($text) => is_string($text) ? Str::lower($text) : '',
            $this->personal,
        ), 2, '');

        $words = [...preg_split('/\s+/u', $name), Str::before($email, '@')];

        // Short pieces ("al", "bin") would reject good passwords by chance.
        return array_values(array_filter($words, fn ($word) => mb_strlen($word) >= 4));
    }
}
