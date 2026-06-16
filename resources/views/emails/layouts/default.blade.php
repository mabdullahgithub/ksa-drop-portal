<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>{{ $subject ?? config('app.name') }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        table { border-collapse: collapse !important; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; display: block; }
        a { color: #f26722; }

        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #eef0f3;
        }

        .email-bg { background-color: #eef0f3; }

        .email-card {
            width: 600px;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(17, 24, 39, 0.08);
        }

        .email-accent-bar {
            height: 5px;
            line-height: 5px;
            font-size: 0;
            background: #f26722;
            background: linear-gradient(90deg, #f9a826 0%, #f26722 55%, #e2540c 100%);
        }

        .email-header {
            padding: 32px 40px 24px 40px;
            text-align: center;
            background-color: #ffffff;
        }

        .email-body {
            padding: 8px 40px 40px 40px;
            color: #374151;
            font-size: 16px;
            line-height: 1.45;
        }

        .email-title {
            font-size: 23px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 18px 0;
            line-height: 1.3;
            letter-spacing: -0.3px;
        }

        .email-text {
            margin: 0 0 12px 0;
            color: #4b5563;
            font-size: 16px;
            line-height: 1.45;
        }

        .email-button {
            display: inline-block;
            padding: 14px 32px;
            background-color: #f26722;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.2px;
            margin: 24px 0;
        }

        .email-divider {
            border: 0;
            border-top: 1px solid #ececf0;
            margin: 32px 0;
        }

        .email-info-box {
            background-color: #fff7f0;
            border-left: 4px solid #f26722;
            padding: 16px 20px;
            margin: 24px 0;
            border-radius: 6px;
        }

        .email-warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            margin: 24px 0;
            border-radius: 6px;
        }

        .email-footer {
            padding: 28px 40px 32px 40px;
            background-color: #f7f8fa;
            border-top: 1px solid #ececf0;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
            line-height: 1.6;
        }

        .text-muted { color: #9ca3af; font-size: 13px; }
        .text-small { font-size: 13px; }

        ul { color: #4b5563; }

        @media only screen and (max-width: 620px) {
            .email-card { width: 100% !important; border-radius: 0 !important; }
            .email-body { padding: 8px 24px 28px 24px !important; }
            .email-header { padding: 28px 24px 20px 24px !important; }
            .email-footer { padding: 24px 24px !important; }
            .email-title { font-size: 21px !important; }
            .email-button { display: block !important; width: 100% !important; text-align: center !important; box-sizing: border-box !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#eef0f3;">
    @isset($preheader)
        <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#eef0f3; opacity:0;">
            {{ $preheader }}&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;
        </div>
    @endisset

    <table role="presentation" class="email-bg" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef0f3;">
        <tr>
            <td align="center" style="padding: 40px 12px;">
                <!--[if mso]>
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td>
                <![endif]-->
                <table role="presentation" class="email-card" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden;">
                    <!-- Accent bar -->
                    <tr>
                        <td class="email-accent-bar" style="height:5px; line-height:5px; font-size:0; background-color:#f26722;">&nbsp;</td>
                    </tr>

                    <!-- Header / Logo -->
                    <tr>
                        <td class="email-header" align="center" style="padding:32px 40px 24px 40px; background-color:#ffffff; text-align:center;">
                            <a href="{{ config('app.url') }}" target="_blank" style="text-decoration:none;">
                                <img src="{{ isset($message) ? $message->embed(public_path('images/email/logo.png')) : config('app.url') . '/images/email/logo.png' }}"
                                     width="150" height="38" alt="{{ config('app.name') }}"
                                     style="width:150px; max-width:150px; height:38px; margin:0 auto;">
                            </a>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td class="email-body" style="padding:8px 40px 40px 40px; color:#374151; font-size:16px; line-height:1.45;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="email-footer" align="center" style="padding:28px 40px 32px 40px; background-color:#f7f8fa; border-top:1px solid #ececf0; text-align:center; font-size:13px; color:#9ca3af;">
                            <p style="margin:0 0 6px 0; font-size:14px; font-weight:600; color:#6b7280;">
                                {{ config('app.name') }}
                            </p>
                            <p class="text-muted" style="margin:0 0 8px 0; color:#9ca3af; font-size:13px;">
                                Kingdom of Saudi Arabia
                            </p>
                            <p class="text-muted" style="margin:0 0 12px 0; color:#9ca3af; font-size:12px; line-height:1.6;">
                                This email was sent to {{ $recipient ?? 'you' }} because an action was taken on your account.
                            </p>
                            <p class="text-muted" style="margin:0; color:#b3b8c2; font-size:12px;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                </td></tr></table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
