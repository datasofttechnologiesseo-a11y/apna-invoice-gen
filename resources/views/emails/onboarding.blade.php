@php
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ')->trim();
    $brand = '#0f766e';
@endphp
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $heading }}</title></head>
<body style="margin: 0; padding: 0; background: #f4f4f5; font-family: Helvetica, Arial, sans-serif; color: #111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f5; padding: 24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <tr>
                    <td style="padding: 24px 32px; background: {{ $brand }}; color: #fff;">
                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.85;">Apna Invoice</div>
                        <div style="font-size: 22px; font-weight: 800; margin-top: 6px;">{{ $heading }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 28px 32px; font-size: 15px; line-height: 1.65; color: #333;">
                        <p style="margin-top: 0;">Hi{{ $firstName ? ' ' . $firstName : '' }},</p>
                        @foreach ($lines as $line)
                            <p>{{ $line }}</p>
                        @endforeach

                        <div style="text-align: center; margin: 28px 0 8px;">
                            <a href="{{ $ctaUrl }}" style="display: inline-block; padding: 13px 28px; background: {{ $brand }}; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px;">{{ $ctaLabel }}</a>
                        </div>

                        <p style="margin-top: 28px; color: #6b7280; font-size: 13px;">
                            Need help? Just reply to this email and we will get back to you.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 16px 32px; background: #f9fafb; color: #9ca3af; font-size: 11px; text-align: center; line-height: 1.5;">
                        You are receiving this because you created an Apna Invoice account.<br>
                        Apna Invoice, by {{ config('legal.operator', 'Datasoft Technologies') }}.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
