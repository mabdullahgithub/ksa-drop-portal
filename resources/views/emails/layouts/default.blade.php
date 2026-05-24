<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>{{ $subject ?? config('app.name') }}</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }

        body {
            margin: 0;
            padding: 0;
            width: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 40px 0;
        }

        .email-content {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
        }

        .email-header {
            background-color: #111827;
            padding: 28px 40px;
            text-align: center;
        }

        .email-logo {
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .email-body {
            padding: 40px;
            color: #374151;
            font-size: 16px;
            line-height: 1.6;
        }

        .email-title {
            font-size: 22px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 16px 0;
            line-height: 1.3;
        }

        .email-text {
            margin: 0 0 16px 0;
            color: #4b5563;
        }

        .email-button {
            display: inline-block;
            padding: 13px 28px;
            background-color: #111827;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            margin: 24px 0;
        }

        .email-divider {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 32px 0;
        }

        .email-info-box {
            background-color: #f9fafb;
            border-left: 4px solid #374151;
            padding: 16px 20px;
            margin: 24px 0;
            border-radius: 4px;
        }

        .email-warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            margin: 24px 0;
            border-radius: 4px;
        }

        .email-footer {
            padding: 24px 40px;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }

        .text-muted {
            color: #9ca3af;
            font-size: 13px;
        }

        .text-small {
            font-size: 13px;
        }

        @media only screen and (max-width: 600px) {
            .email-body { padding: 24px !important; }
            .email-header { padding: 20px 24px !important; }
            .email-footer { padding: 20px 24px !important; }
            .email-title { font-size: 20px !important; }
            .email-button { display: block !important; width: 100% !important; text-align: center !important; box-sizing: border-box !important; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-content">
            <div class="email-header">
                <a href="{{ config('app.url') }}" class="email-logo">
                    {{ config('app.name') }}
                </a>
            </div>

            <div class="email-body">
                @yield('content')
            </div>

            <div class="email-footer">
                <p class="text-muted" style="margin: 0 0 8px 0;">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
                <p class="text-muted" style="margin: 0 0 8px 0;">
                    This email was sent to {{ $recipient ?? 'you' }} because an action was taken on your account.
                </p>
                <p class="text-muted" style="margin: 0;">
                    Kingdom of Saudi Arabia
                </p>
            </div>
        </div>
    </div>
</body>
</html>
