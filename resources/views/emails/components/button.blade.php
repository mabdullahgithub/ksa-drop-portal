<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" bgcolor="#f26722" style="border-radius: 6px; background-color: #f26722;">
                        <!--[if mso]>
                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:40px;v-text-anchor:middle;width:190px;" arcsize="15%" stroke="f" fillcolor="#f26722">
                        <w:anchorlock/>
                        <center style="color:#ffffff;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:14px;font-weight:500;">{{ $slot }}</center>
                        </v:roundrect>
                        <![endif]-->
                        <!--[if !mso]><!-->
                        <a href="{{ $url }}" target="_blank" class="email-button" style="display: inline-block; padding: 11px 28px; background-color: #f26722; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 14px; line-height: 20px; letter-spacing: 0.2px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            {{ $slot }}
                        </a>
                        <!--<![endif]-->
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
