@php
    $c = $quotation->company;
    $number = $quotation->quote_number ?: 'Draft #' . $quotation->id;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Quotation {{ $number }}</title>
</head>
<body style="margin: 0; padding: 0; background: #f4f4f5; font-family: Helvetica, Arial, sans-serif; color: #111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f5; padding: 24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding: 24px 32px; background: #1f3061; color: #fff;">
                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;">{{ $c->name }}</div>
                        <div style="font-size: 22px; font-weight: 700; margin-top: 4px;">
                            Quotation {{ $number }}
                        </div>
                        <div style="font-size: 11px; opacity: 0.85; margin-top: 4px;">Price proposal · Not a tax invoice</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 28px 32px; font-size: 14px; line-height: 1.6; color: #333;">
                        {!! nl2br(e($bodyText)) !!}

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border: 1px solid #e5e7eb; border-radius: 6px;">
                            <tr>
                                <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Quotation summary</td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 16px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px;">
                                        <tr><td style="padding: 4px 0; color: #6b7280;">Quote no.</td><td style="padding: 4px 0; text-align: right; font-weight: 600;">{{ $number }}</td></tr>
                                        <tr><td style="padding: 4px 0; color: #6b7280;">Date</td><td style="padding: 4px 0; text-align: right;">{{ $quotation->quote_date?->format('d M Y') }}</td></tr>
                                        @if ($quotation->valid_until)
                                            <tr><td style="padding: 4px 0; color: #6b7280;">Valid until</td><td style="padding: 4px 0; text-align: right; font-weight: 600;">{{ $quotation->valid_until->format('d M Y') }}</td></tr>
                                        @endif
                                        <tr><td style="padding: 6px 0; border-top: 1px solid #e5e7eb; color: #6b7280; font-weight: 600;">Grand total</td><td style="padding: 6px 0; border-top: 1px solid #e5e7eb; text-align: right; font-weight: 700; font-size: 16px;">&#8377; {{ inr($quotation->grand_total) }}</td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        @if ($publicUrl)
                            <div style="text-align: center; margin-top: 24px;">
                                <a href="{{ $publicUrl }}" style="display: inline-block; padding: 12px 24px; background: #1f3061; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">View quotation online</a>
                            </div>
                        @endif

                        <p style="margin-top: 24px; font-size: 13px; color: #6b7280;">
                            The quotation PDF is attached for your records. This is a price proposal - no GST is collected at this stage. A formal tax invoice will be issued on order confirmation.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 16px 32px; background: #f9fafb; color: #6b7280; font-size: 11px; text-align: center;">
                        Sent from {{ $c->name }} · Powered by <a href="{{ url('/') }}?utm_source=quotation_email&utm_medium=email&utm_campaign=powered_by" style="color: #4b5563; font-weight: bold; text-decoration: underline;">{{ config('app.name') }}</a>
                        <br><span style="color: #9ca3af;">Free GST invoicing for Indian businesses - <a href="{{ route('register') }}?utm_source=quotation_email&utm_medium=email&utm_campaign=powered_by" style="color: #9ca3af; text-decoration: underline;">create yours free</a></span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
