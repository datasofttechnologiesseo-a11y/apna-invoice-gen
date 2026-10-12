{{-- Shared body for the report PDF and print page. --}}
<div class="head">
    <div class="company">{{ $company->name }}</div>
    <div class="muted">
        {{ collect([$company->address_line1, $company->city, $company->state?->name])->filter()->implode(', ') }}
        @if ($company->gstin) · GSTIN {{ $company->gstin }}@endif
    </div>
    <h1>{{ $report->title() }}</h1>
    <div class="muted">{{ $report->subtitle() }} · Generated {{ now()->format('d M Y, h:i A') }}</div>
</div>

<table class="grid">
    <thead>
        <tr>
            @foreach ($columns as $col)
                <th class="{{ in_array($col['type'] ?? 'text', ['money', 'qty', 'number', 'percent'], true) ? 'r' : '' }}">{{ $col['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($columns as $col)
                    <td class="{{ in_array($col['type'] ?? 'text', ['money', 'qty', 'number', 'percent'], true) ? 'r num' : '' }}">{{ $fmt($col, $row[$col['key']] ?? null) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" class="empty">No records for this selection.</td></tr>
        @endforelse
    </tbody>
    @if ($totals && count($rows))
        <tfoot>
            <tr>
                @foreach ($columns as $i => $col)
                    <td class="{{ array_key_exists($col['key'], $totals) ? 'r num' : '' }}">{{ $i === 0 ? 'Total (' . count($rows) . ')' : (array_key_exists($col['key'], $totals) ? $fmt($col, $totals[$col['key']]) : '') }}</td>
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
<div class="foot">Apna Invoice · {{ $report->title() }} · E. &amp; O.E.</div>
