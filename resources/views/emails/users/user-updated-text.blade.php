{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {{ $admin->name }},

A user account in your {{ config('app.name') }} workspace has been updated.

User:       {{ $updatedUser->name }} ({{ $updatedUser->email }})
Updated by: {{ $updatedBy->name }}
Date:       {{ now()->format('F j, Y \a\t g:i A') }}

@if(isset($changes) && !empty($changes))
Changes made:
@foreach($changes as $field => $change)
  {{ ucfirst(str_replace('_', ' ', $field)) }}: {{ $change['from'] ?? 'N/A' }} → {{ $change['to'] ?? 'N/A' }}
@endforeach

@endif
View user details:
{{ $userUrl ?? rtrim(config('app.url'), '/') . '/team-management/users' }}

---
This email was sent to {{ $recipient ?? 'you' }} because you are an administrator.
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
