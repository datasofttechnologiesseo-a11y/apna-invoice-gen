<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Your verification code</title></head>
<body style="margin: 0; padding: 0; background: #f4f4f5; font-family: Helvetica, Arial, sans-serif; color: #111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f5; padding: 24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <tr>
                    <td style="padding: 24px 32px; background: #1e3a8a; color: #fff;">
                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;">Apna Invoice</div>
                        <div style="font-size: 20px; font-weight: 800; margin-top: 6px;">Verify your mobile number</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 28px 32px; font-size: 15px; line-height: 1.6; color: #333;">
                        <p style="margin-top: 0;">Use this code to finish creating your account:</p>
                        <div style="text-align: center; margin: 24px 0;">
                            <div style="display: inline-block; padding: 14px 28px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #1e3a8a; font-family: monospace;">{{ $code }}</div>
                        </div>
                        <p style="color: #6b7280; font-size: 13px; text-align: center;">This code is valid for {{ $ttl }} minutes. If you didn't request it, you can ignore this email.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 16px 32px; background: #f9fafb; color: #9ca3af; font-size: 11px; text-align: center;">
                        Apna Invoice, by {{ config('legal.operator', 'Datasoft Technologies') }}.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
