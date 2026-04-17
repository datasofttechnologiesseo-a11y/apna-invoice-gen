<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Invoices') }}</h2>
            <a href="{{ route('invoices.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-md">+ New invoice</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow sm:rounded-lg">
                <form method="GET" class="p-4 border-b flex flex-wrap gap-3 items-center">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search number" class="border-gray-300 rounded-md shadow-sm">
                    <select name="status" class="border-gray-300 rounded-md shadow-sm" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach (['draft','final','partially_paid','paid','cancelled'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                    <button class="px-3 py-1.5 bg-brand-700 text-white rounded text-sm hover:bg-brand-800">Filter</button>
                    @if (request('search') || request('status'))
                        <a href="{{ route('invoices.index') }}" class="text-gray-500 text-sm">clear</a>
                    @endif
                </form>

                @if ($invoices->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        No invoices. <a href="{{ route('invoices.create') }}" class="text-brand-600 hover:underline">Create your first</a>.
                    </div>
                @else
                    <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Number</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Balance</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($invoices as $inv)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-sm">
                                        @if (str_starts_with($inv->invoice_number, 'DRAFT-'))
                                            <span class="text-gray-400">—</span>
                                        @else
                                            {{ $inv->invoice_number }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $inv->invoice_date?->format('d M Y') }}</td>
                                    <td class="px-4 py-3">{{ $inv->customer?->name }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ $inv->currency }} {{ number_format((float) $inv->grand_total, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $inv->balance, 2) }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $colors = [
                                                'draft' => 'bg-gray-100 text-gray-700',
                                                'final' => 'bg-blue-100 text-blue-800',
                                                'partially_paid' => 'bg-amber-100 text-amber-800',
                                                'paid' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                            ];
                                        @endphp
                                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $colors[$inv->status] ?? 'bg-gray-100' }}">{{ ucfirst(str_replace('_',' ',$inv->status)) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm space-x-2">
                                        <a href="{{ route('invoices.show', $inv) }}" class="text-brand-600 hover:underline">View</a>
                                        <a href="{{ route('invoices.pdf', $inv) }}" class="text-gray-600 hover:underline">PDF</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    <div class="p-4">{{ $invoices->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
