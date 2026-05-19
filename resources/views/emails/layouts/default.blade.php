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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 32px 40px;
            text-align: center;
        }

        .email-logo {
            font-size: 28px;
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
            font-size: 24px;
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
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 24px 0;
            transition: transform 0.2s;
        }

        .email-button:hover {
            transform: translateY(-1px);
        }

        .email-divider {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 32px 0;
        }

        .email-info-box {
            background-color: #f9fafb;
            border-left: 4px solid #667eea;
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
            padding: 32px 40px;
            background-color: #f9fafb;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }

        .email-footer-links {
            margin: 16px 0;
        }

        .email-footer-link {
            color: #667eea;
            text-decoration: none;
            margin: 0 12px;
        }

        .email-footer-link:hover {
            text-decoration: underline;
        }

        .email-social-links {
            margin: 20px 0;
        }

        .email-social-link {
            display: inline-block;
            margin: 0 8px;
            color: #6b7280;
            text-decoration: none;
        }

        .text-muted {
            color: #6b7280;
            font-size: 14px;
        }

        .text-small {
            font-size: 13px;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 24px !important;
            }

            .email-header {
                padding: 24px !important;
            }

            .email-footer {
                padding: 24px !important;
            }

            .email-title {
                font-size: 20px !important;
            }

            .email-button {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-content">
            <!-- Header -->
            <div class="email-header">
                <a href="{{ config('app.url') }}" class="email-logo">
                    {{ config('app.name') }}
                </a>
            </div>

            <!-- Body -->
            <div class="email-body">
                @yield('content')
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <p class="text-muted" style="margin: 0 0 16px 0;">
                    This email was sent to {{ $recipient ?? 'you' }} because you have an account with {{ config('app.name') }}.
                </p>

                <div class="email-footer-links">
                    <a href="{{ config('app.url') }}" class="email-footer-link">Home</a>
                    <a href="{{ config('app.url') }}/help-center" class="email-footer-link">Help Center</a>
                    <a href="{{ config('app.url') }}/settings/notifications" class="email-footer-link">Email Preferences</a>
                </div>

                @if(!($isSecurityEmail ?? false))
                <p class="text-muted text-small" style="margin: 16px 0 0 0;">
                    Don't want to receive these emails?
                    <a href="{{ config('app.url') }}/settings/notifications" class="email-footer-link">Update your preferences</a>
                </p>
                @endif

                <p class="text-muted text-small" style="margin: 24px 0 0 0;">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
