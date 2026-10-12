<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $report->title() }} · {{ $company->name }}</title>
    <style>
        @page { size: A4 {{ $report->landscape() ? 'landscape' : 'portrait' }}; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 16px; background: #fff; }
        .bar { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 12px; }
        .bar button { font: inherit; font-weight: 600; padding: 6px 14px; border-radius: 6px; border: 1px solid #ccc; background: #fff; cursor: pointer; }
        .bar button.primary { background: #167C88; color: #fff; border-color: #167C88; }
        .head { margin-bottom: 10px; border-bottom: 2px solid #167C88; padding-bottom: 8px; }
        .company { font-size: 15px; font-weight: 700; }
        h1 { font-size: 16px; margin: 6px 0 2px; color: #0E2843; }
        .muted { color: #555; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #167C88; color: #fff; text-align: left; padding: 6px; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        table.grid td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.grid tbody tr:nth-child(even) td { background: #f8fafb; }
        table.grid tfoot td { font-weight: 700; border-top: 2px solid #333; background: #eef4f5; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .r { text-align: right; }
        .num { font-variant-numeric: tabular-nums; }
        .empty { text-align: center; color: #777; padding: 20px; }
        .foot { margin-top: 10px; font-size: 9px; color: #888; text-align: center; }
        @media print { .bar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.close()">Close</button>
        <button type="button" class="primary" onclick="window.print()">Print</button>
    </div>
    @include('reports.partials.table')
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
