<!DOCTYPE html>
<html lang="en-IN">
<head><meta charset="utf-8"><title>Your data backup</title></head>
<body style="margin: 0; padding: 0; background: #f4f4f5; font-family: Helvetica, Arial, sans-serif; color: #111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f5; padding: 24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding: 24px 32px; background: #167c88; color: #fff;">
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;">{{ config('app.name') }}</div>
                        <div style="font-size: 22px; font-weight: 700; margin-top: 4px;">Your data backup</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 28px 32px; font-size: 14px; line-height: 1.6; color: #333;">
                        <p>Hi {{ $user->name }},</p>
                        <p>Your data backup of {{ $generatedAt->format('d M Y, h:i A') }} is attached as a ZIP file. It contains CSV files of your companies, customers, products, invoices, payments and expenses.</p>
                        <p style="background: #f9fafb; border-left: 4px solid #167c88; padding: 12px 14px; margin: 20px 0; font-size: 13px;">
                            <strong>Keep this file secure.</strong> It contains customer GSTINs and payment history.
                        </p>
                        <p>You can download a backup at any time from the <strong>Backups</strong> page in {{ config('app.name') }}.</p>
                        <p style="margin-top: 24px;">Regards,<br>{{ config('app.name') }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 16px 32px; background: #f9fafb; color: #6b7280; font-size: 11px; text-align: center;">
                        You received this because you requested a backup or have weekly backup turned on.
                        Change this on the Backups page.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
