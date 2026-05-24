@php
    $classes = match($type ?? 'info') {
        'info' => 'background-color: #f0f9ff; border-left: 4px solid #0ea5e9;',
        'warning' => 'background-color: #fef3c7; border-left: 4px solid #f59e0b;',
        'success' => 'background-color: #f0fdf4; border-left: 4px solid #22c55e;',
        'danger' => 'background-color: #fef2f2; border-left: 4px solid #ef4444;',
        default => 'background-color: #f9fafb; border-left: 4px solid #374151;',
    };
@endphp

<div style="{{ $classes }} padding: 16px 20px; margin: 24px 0; border-radius: 4px;">
    {{ $slot }}
</div>
