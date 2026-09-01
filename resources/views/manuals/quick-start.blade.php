<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $appName }} Quick Start</title>
    <style>
        @page { margin: 14mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.55; color: #23262f; margin: 0; }

        .head { border-bottom: 3px solid #0f766e; padding-bottom: 9px; margin-bottom: 14px; }
        .kicker { font-size: 8px; letter-spacing: 2.5px; text-transform: uppercase; color: #c2410c; font-weight: bold; }
        h1 { font-size: 24px; color: #134e4a; margin: 6px 0 3px; letter-spacing: -0.4px; }
        .sub { font-size: 11px; color: #6b7490; }

        table.steps { width: 100%; border-collapse: collapse; }
        table.steps td { vertical-align: top; padding: 0 0 13px; }
        td.n {
            width: 26px; font-size: 15px; font-weight: bold; color: #0f766e;
        }
        .st { font-size: 12px; font-weight: bold; color: #134e4a; margin-bottom: 2px; }
        .sd { font-size: 10.5px; color: #383d4d; }

        table.split { width: 100%; border-collapse: collapse; margin: 4px 0 12px; }
        table.split thead th {
            background: #134e4a; color: #fff; text-align: left;
            padding: 5px 7px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        table.split tbody td { padding: 5px 7px; border-bottom: 1px solid #e3e7f0; font-size: 10px; }

        table.help { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.help td {
            background: #e7f6ef; border-left: 3px solid #047857;
            padding: 8px 10px; font-size: 10px;
        }
        .foot { margin-top: 12px; font-size: 8.5px; color: #6b7490; text-align: center; }
    </style>
</head>
<body>

    <div class="head">
        <div class="kicker">{{ $quick['title'] }}</div>
        <h1>{{ $quick['subtitle'] }}</h1>
        <div class="sub">{{ $appName }} - free GST invoicing for Indian businesses</div>
    </div>

    <table class="steps">
        @foreach ($quick['steps'] as $i => $step)
            <tr>
                <td class="n">{{ $i + 1 }}</td>
                <td>
                    <div class="st">{{ $step['title'] }}</div>
                    <div class="sd">{{ $step['text'] }}</div>
                </td>
            </tr>
        @endforeach
    </table>

    <div class="st" style="margin-bottom:4px;">How the tax is decided</div>
    <table class="split">
        <thead>
            <tr><th>Situation</th><th>Tax applied</th><th>On &#8377;10,000 at 18%</th></tr>
        </thead>
        <tbody>
            <tr><td>Same state</td><td>CGST + SGST</td><td>&#8377;900 + &#8377;900</td></tr>
            <tr><td>Different state</td><td>IGST</td><td>&#8377;1,800</td></tr>
        </tbody>
    </table>

    <table class="help">
        <tr><td>{{ $quick['footer'] }}</td></tr>
    </table>

    <div class="foot">{{ $siteUrl }} &nbsp;&middot;&nbsp; Version {{ $version }}</div>

</body>
</html>
