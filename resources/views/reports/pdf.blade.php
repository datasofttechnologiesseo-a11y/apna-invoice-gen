<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    <style>
        @page { margin: 18px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; }
        .head { margin-bottom: 8px; border-bottom: 1.5px solid #167C88; padding-bottom: 6px; }
        .company { font-size: 12px; font-weight: bold; }
        h1 { font-size: 13px; margin: 4px 0 2px; color: #0E2843; }
        .muted { color: #555; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #167C88; color: #fff; text-align: left; padding: 4px 4px; font-size: 8px; text-transform: uppercase; }
        table.grid td { padding: 3px 4px; border-bottom: 0.5px solid #ddd; vertical-align: top; }
        table.grid tbody tr:nth-child(even) td { background: #f6f8f9; }
        table.grid tfoot td { font-weight: bold; border-top: 1px solid #333; background: #eef4f5; }
        .r { text-align: right; }
        .num { font-family: DejaVu Sans Mono, monospace; }
        .empty { text-align: center; color: #777; padding: 14px; }
        .foot { margin-top: 8px; font-size: 7px; color: #888; text-align: center; }
    </style>
</head>
<body>
    @include('reports.partials.table')
</body>
</html>
