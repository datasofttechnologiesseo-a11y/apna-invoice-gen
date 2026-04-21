@php
    $c = $invoice->company;
    $cust = $invoice->customer;
    $balance = (float) $invoice->balance;
    $overdueLabel = $daysPastDue <= 0
        ? 'due today'
        : ($daysPastDue . ' day' . ($daysPastDue > 1 ? 's' : '') . ' overdue');
    $accent = $daysPastDue >= 15 ? '#b91c1c' : ($daysPastDue >= 7 ? '#c2410c' : '#1e3a8a');
@endphp
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Payment reminder — {{ $invoice->invoice_number }}</title></head>
<body style="margin: 0; padding: 0; background: #f4f4f5; font-family: Helvetica, Arial, sans-serif; color: #111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f5; padding: 24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding: 24px 32px; background: {{ $accent }}; color: #fff;">
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;">{{ $c->name }}</div>
                        <div style="font-size: 20px; font-weight: 700; margin-top: 4px;">Payment reminder</div>
                        <div style="font-size: 13px; margin-top: 4px; opacity: 0.9;">
                            Invoice {{ $invoice->invoice_number }} — <strong>{{ $overdueLabel }}</strong>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 28px 32px; font-size: 14px; line-height: 1.6; color: #333;">
                        <p>Hi {{ $cust->name }},</p>
                        @if ($daysPastDue <= 0)
                            <p>This is a friendly reminder that invoice <strong>{{ $invoice->invoice_number }}</strong>
                                @if ($invoice->due_date) is due <strong>today ({{ $invoice->due_date->format('d M Y') }})</strong>@endif.
                                Please arrange the payment at your earliest convenience.</p>
                        @else
                            <p>Our records show that invoice <strong>{{ $invoice->invoice_number }}</strong>
                                @if ($invoice->due_date) was due on <strong>{{ $invoice->due_date->format('d M Y') }}</strong> — it's now <strong>{{ $daysPastDue }} day{{ $daysPastDue > 1 ? 's' : '' }} overdue</strong>@endif.
                                If you've already paid, please ignore this note.</p>
                        @endif

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border: 1px solid #e5e7eb; border-radius: 6px;">
                            <tr>
                                <td style="padding: 14px 18px;">
                                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280;">Balance due</div>
                                    <div style="font-size: 28px; font-weight: 700; color: {{ $accent }}; margin-top: 4px;">&#8377; {{ number_format($balance, 2) }}</div>
                                    <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                                        Invoice total: ₹{{ number_format((float) $invoice->grand_total, 2) }}
                                        @if ((float) $invoice->paid_amount > 0)
                                            · Already paid: ₹{{ number_format((float) $invoice->paid_amount, 2) }}
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <div style="text-align: center; margin: 24px 0;">
                            <a href="{{ $publicUrl }}" style="display: inline-block; padding: 12px 24px; background: {{ $accent }}; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">View invoice online</a>
                        </div>

                        @if ($c->upi_id)
                            <p style="text-align: center; font-size: 13px; color: #6b7280;">
                                Pay via UPI: <strong style="color: #111; font-family: monospace;">{{ $c->upi_id }}</strong>
                            </p>
                        @endif

                        <p style="margin-top: 24px;">Thank you for your business.</p>
                        <p style="margin-top: 16px;">
                            Warm regards,<br>
                            <strong>{{ $c->name }}</strong>
                            @if ($c->phone)<br><span style="color: #6b7280;">{{ $c->phone }}</span>@endif
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
