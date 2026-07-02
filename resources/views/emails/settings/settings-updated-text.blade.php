{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {{ $user->name }},

Your {{ $settingType ?? 'account' }} settings were updated on {{ now()->format('F j, Y \a\t g:i A') }}.

@if(isset($changes) && !empty($changes))
Changes made:
@foreach($changes as $field => $change)
@if(is_array($change) && isset($change['from']) && isset($change['to']))
  {{ ucfirst(str_replace('_', ' ', $field)) }}: {{ $change['from'] }} → {{ $change['to'] }}
@else
  {{ ucfirst(str_replace('_', ' ', $field)) }}: {{ is_string($change) ? $change : json_encode($change) }}
@endif
@endforeach

@endif
If you did not make these changes, please contact our support team.

View your settings:
{{ rtrim(config('app.url'), '/') }}/settings

---
This email was sent to {{ $recipient ?? 'you' }} because your account settings changed.
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
