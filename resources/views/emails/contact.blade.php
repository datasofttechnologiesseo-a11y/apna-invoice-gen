<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>New contact message</title></head>
<body style="margin: 0; padding: 0; background: #f4f4f5; font-family: Helvetica, Arial, sans-serif; color: #111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f5; padding: 24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #fff; border-radius: 8px; overflow: hidden;">
                <tr>
                    <td style="padding: 20px 28px; background: #1e3a8a; color: #fff;">
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;">{{ config('app.name') }} — Contact form</div>
                        <div style="font-size: 18px; font-weight: 700; margin-top: 4px;">{{ $subjectLine }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 24px 28px; font-size: 14px; line-height: 1.6; color: #333;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 13px; margin-bottom: 20px;">
                            <tr>
                                <td style="padding: 4px 0; color: #6b7280; width: 90px;">From</td>
                                <td style="padding: 4px 0; font-weight: 600;">{{ $fromName }} &lt;{{ $fromEmail }}&gt;</td>
                            </tr>
                            @if ($phone)
                                <tr>
                                    <td style="padding: 4px 0; color: #6b7280;">Phone</td>
                                    <td style="padding: 4px 0;">{{ $phone }}</td>
                                </tr>
                            @endif
                        </table>
                        <div style="padding: 14px 16px; background: #f9fafb; border-left: 3px solid #1e3a8a; white-space: pre-line;">{{ $messageBody }}</div>
                        <p style="margin-top: 20px; font-size: 12px; color: #6b7280;">
                            Reply to this email to respond — we've set Reply-To to the sender's address.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
