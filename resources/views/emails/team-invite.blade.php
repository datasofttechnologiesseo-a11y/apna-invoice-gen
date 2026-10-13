<!DOCTYPE html>
<html lang="en-IN">
<head><meta charset="utf-8"><title>Invitation to join {{ $company }}</title></head>
<body style="margin: 0; padding: 0; background: #f4f4f5; font-family: Helvetica, Arial, sans-serif; color: #111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f5; padding: 24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <tr>
                    <td style="padding: 24px 32px; background: #167c88; color: #fff;">
                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;">Apna Invoice</div>
                        <div style="font-size: 20px; font-weight: 800; margin-top: 6px;">Join {{ $company }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 28px 32px; font-size: 15px; line-height: 1.6; color: #333;">
                        <p style="margin-top: 0;">{{ $inviter }} has invited you to work in <strong>{{ $company }}</strong> on Apna Invoice as <strong>{{ $role }}</strong>.</p>
                        <p style="color: #555; font-size: 14px;">{{ $roleHelp }}</p>
                        <div style="text-align: center; margin: 28px 0;">
                            <a href="{{ $url }}" style="display: inline-block; padding: 12px 28px; background: #167c88; color: #fff; border-radius: 8px; font-weight: 700; text-decoration: none;">Accept invitation</a>
                        </div>
                        <p style="color: #6b7280; font-size: 13px;">New to Apna Invoice? Create an account with this email address, then open the link again. If you were not expecting this invitation, ignore this email.</p>
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
