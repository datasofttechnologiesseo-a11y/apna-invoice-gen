@php
    $c = $invoice->company;
    $balance = (float) $invoice->balance;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice {{ $invoice->invoice_number ?? 'Draft' }}</title>
</head>
<body style="margin: 0; padding: 0; background: #f4f4f5; font-family: Helvetica, Arial, sans-serif; color: #111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f5; padding: 24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding: 24px 32px; background: #1e3a8a; color: #fff;">
                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;">{{ $c->name }}</div>
                        <div style="font-size: 22px; font-weight: 700; margin-top: 4px;">
                            Invoice {{ $invoice->invoice_number ?? 'Draft #' . $invoice->id }}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 28px 32px; font-size: 14px; line-height: 1.6; color: #333;">
                        {!! nl2br(e($bodyText)) !!}

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border: 1px solid #e5e7eb; border-radius: 6px;">
                            <tr>
                                <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Invoice summary</td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 16px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px;">
                                        <tr><td style="padding: 4px 0; color: #6b7280;">Invoice no.</td><td style="padding: 4px 0; text-align: right; font-weight: 600;">{{ $invoice->invoice_number ?? 'Draft #' . $invoice->id }}</td></tr>
                                        <tr><td style="padding: 4px 0; color: #6b7280;">Date</td><td style="padding: 4px 0; text-align: right;">{{ $invoice->invoice_date?->format('d M Y') }}</td></tr>
                                        @if ($invoice->due_date)
                                            <tr><td style="padding: 4px 0; color: #6b7280;">Due</td><td style="padding: 4px 0; text-align: right; font-weight: 600; color: #b91c1c;">{{ $invoice->due_date->format('d M Y') }}</td></tr>
                                        @endif
                                        <tr><td style="padding: 4px 0; color: #6b7280;">Grand total</td><td style="padding: 4px 0; text-align: right; font-weight: 700;">&#8377; {{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
                                        @if ($balance > 0 && (float) $invoice->paid_amount > 0)
                                            <tr><td style="padding: 4px 0; color: #6b7280;">Already paid</td><td style="padding: 4px 0; text-align: right; color: #047857;">&#8377; {{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
                                        @endif
                                        @if ($balance > 0)
                                            <tr><td style="padding: 6px 0; border-top: 1px solid #e5e7eb; color: #b91c1c; font-weight: 600;">Balance due</td><td style="padding: 6px 0; border-top: 1px solid #e5e7eb; text-align: right; font-weight: 700; color: #b91c1c; font-size: 16px;">&#8377; {{ number_format($balance, 2) }}</td></tr>
                                        @else
                                            <tr><td style="padding: 6px 0; border-top: 1px solid #e5e7eb; color: #047857; font-weight: 600;">Status</td><td style="padding: 6px 0; border-top: 1px solid #e5e7eb; text-align: right; font-weight: 700; color: #047857; font-size: 14px;">PAID IN FULL</td></tr>
                                        @endif
                                    </table>
                                </td>
                            </tr>
                        </table>

                        @if ($publicUrl)
                            <div style="text-align: center; margin-top: 24px;">
                                <a href="{{ $publicUrl }}" style="display: inline-block; padding: 12px 24px; background: #1e3a8a; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">View invoice online</a>
                            </div>
                        @endif

                        <p style="margin-top: 24px; font-size: 13px; color: #6b7280;">
                            The invoice PDF is attached for your records.
                            @if ($c->upi_id)
                                You can also pay via UPI at <strong style="color: #111;">{{ $c->upi_id }}</strong>.
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 16px 32px; background: #f9fafb; color: #6b7280; font-size: 11px; text-align: center;">
                        Sent from {{ $c->name }} · Powered by {{ config('app.name') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
