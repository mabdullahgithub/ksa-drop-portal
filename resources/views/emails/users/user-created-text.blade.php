{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {{ $admin->name }},

A new user account has been added to your {{ config('app.name') }} workspace.

User details:
  Name:       {{ $createdUser->name }}
  Email:      {{ $createdUser->email }}
@if(isset($role))
  Role:       {{ $role }}
@endif
  Added by:   {{ $createdBy->name }}
  Added on:   {{ now()->format('F j, Y \a\t g:i A') }}

View user details:
{{ $userUrl ?? rtrim(config('app.url'), '/') . '/team-management/users' }}

---
This email was sent to {{ $recipient ?? 'you' }} because you are an administrator.
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
