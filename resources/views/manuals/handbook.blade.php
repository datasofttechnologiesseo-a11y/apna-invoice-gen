<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $appName }} Handbook</title>
    <style>
        @page { margin: 16mm 15mm 18mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.55; color: #23262f; margin: 0; }

        /* ---- cover ---- */
        .cover { text-align: center; padding-top: 55mm; page-break-after: always; }
        .cover .kicker { font-size: 8.5px; letter-spacing: 3px; text-transform: uppercase; color: #c2410c; font-weight: bold; }
        .cover h1 { font-size: 32px; color: #134e4a; margin: 10px 0 0; letter-spacing: -0.5px; }
        .cover .sub { font-size: 12px; color: #6b7490; margin-top: 10px; }
        .cover .rule { width: 60px; height: 3px; background: #f97316; margin: 22px auto; }
        .cover .foot { font-size: 8.5px; color: #6b7490; margin-top: 40mm; line-height: 1.9; }

        /* ---- contents ---- */
        .toc-h { font-size: 16px; color: #134e4a; margin: 0 0 12px; }
        table.toc { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.toc td { padding: 5px 0; border-bottom: 1px solid #edf0f7; font-size: 10.5px; vertical-align: top; }
        table.toc td.n { width: 26px; color: #c2410c; font-weight: bold; }

        /* ---- chapters ---- */
        h2 {
            font-size: 15px; color: #134e4a; margin: 0 0 8px;
            padding-bottom: 5px; border-bottom: 2px solid #0f766e;
        }
        h2 .cn { color: #c2410c; font-size: 11px; }
        .chapter { page-break-before: always; }
        p { margin: 0 0 7px; }
        ul { margin: 0 0 8px; padding-left: 14px; }
        li { margin-bottom: 3px; }

        table.steps { width: 100%; border-collapse: collapse; margin: 2px 0 9px; }
        table.steps td { padding: 3px 0; vertical-align: top; }
        table.steps td.step-n {
            width: 18px; color: #0f766e; font-weight: bold; font-size: 10px;
        }
        table.steps td.step-t { padding-left: 4px; }

        table.note { width: 100%; border-collapse: collapse; margin: 9px 0; }
        table.note td {
            background: #f3f6fc; border-left: 3px solid #0f766e;
            padding: 7px 9px; font-size: 9.5px;
        }
        .note-label {
            font-size: 7.5px; letter-spacing: 1.2px; text-transform: uppercase;
            font-weight: bold; color: #0f766e; margin-bottom: 3px;
        }

        table.data { width: 100%; border-collapse: collapse; margin: 9px 0; }
        table.data thead th {
            background: #134e4a; color: #fff; text-align: left;
            padding: 5px 7px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        table.data tbody td {
            padding: 5px 7px; border-bottom: 1px solid #e3e7f0;
            font-size: 9.5px; vertical-align: top;
        }
        table.data tbody tr:nth-child(even) td { background: #fafbfe; }

        .end { margin-top: 14px; padding-top: 8px; border-top: 1px solid #dfe4f0; font-size: 8.5px; color: #6b7490; }
    </style>
</head>
<body>

    <div class="cover">
        <div class="kicker">User Handbook</div>
        <h1>{{ $appName }}</h1>
        <div class="rule"></div>
        <div class="sub">Free GST invoicing for Indian businesses</div>
        <div class="sub" style="font-size:10px; margin-top:4px;">
            Everything you can do, in plain English - from your first bill to your GST returns.
        </div>
        <div class="foot">
            Version {{ $version }} &nbsp;&middot;&nbsp; Updated {{ $updated }}<br>
            For shops, MSMEs, startups, freelancers and CAs<br>
            {{ $siteUrl }}
        </div>
    </div>

    <div class="toc-h">Contents</div>
    <table class="toc">
        @foreach ($chapters as $i => $chapter)
            <tr>
                <td class="n">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $chapter['title'] }}</td>
            </tr>
        @endforeach
    </table>

    @foreach ($chapters as $i => $chapter)
        <div class="chapter">
            <h2><span class="cn">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span> &nbsp; {{ $chapter['title'] }}</h2>
            @include('manuals._blocks', ['blocks' => $chapter['blocks']])
        </div>
    @endforeach

    <div class="end">
        Need help? Message us on WhatsApp or email {{ $supportEmail }}. Help is free, and asking us to set up your
        first invoice with you on the phone is a normal thing to ask for. &nbsp;&middot;&nbsp; {{ $siteUrl }}
    </div>

</body>
</html>
