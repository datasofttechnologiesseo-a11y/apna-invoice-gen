<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-5 bg-white rounded-lg shadow">
                    <div class="text-xs uppercase text-gray-500">Total invoices</div>
                    <div class="text-2xl font-semibold mt-1">{{ $stats['total'] }}</div>
                </div>
                <div class="p-5 bg-white rounded-lg shadow">
                    <div class="text-xs uppercase text-gray-500">Drafts</div>
                    <div class="text-2xl font-semibold mt-1">{{ $stats['drafts'] }}</div>
                </div>
                <div class="p-5 bg-white rounded-lg shadow">
                    <div class="text-xs uppercase text-gray-500">Outstanding</div>
                    <div class="text-2xl font-semibold mt-1 text-amber-700">₹{{ number_format((float) $stats['outstanding'], 2) }}</div>
                </div>
                <div class="p-5 bg-white rounded-lg shadow">
                    <div class="text-xs uppercase text-gray-500">Paid this month</div>
                    <div class="text-2xl font-semibold mt-1 text-green-700">₹{{ number_format((float) $stats['paid_this_month'], 2) }}</div>
                </div>
            </div>

            <div class="flex gap-3 flex-wrap">
                <a href="{{ route('invoices.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded">+ New invoice</a>
                <a href="{{ route('customers.create') }}" class="px-4 py-2 bg-white border text-gray-700 rounded hover:bg-gray-50">+ New customer</a>
                <a href="{{ route('company.edit') }}" class="px-4 py-2 bg-white border text-gray-700 rounded hover:bg-gray-50">Company profile</a>
            </div>

            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b font-medium">Recent invoices</div>
                @if ($recent->isEmpty())
                    <div class="p-8 text-center text-gray-500">No invoices yet. <a href="{{ route('invoices.create') }}" class="text-indigo-600 hover:underline">Create your first</a>.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 text-left">
                            <tr>
                                <th class="px-4 py-2">Number</th>
                                <th class="px-4 py-2">Date</th>
                                <th class="px-4 py-2">Customer</th>
                                <th class="px-4 py-2 text-right">Total</th>
                                <th class="px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($recent as $inv)
                                <tr>
                                    <td class="px-4 py-2 font-mono">
                                        <a href="{{ route('invoices.show', $inv) }}" class="text-indigo-600 hover:underline">
                                            {{ str_starts_with($inv->invoice_number, 'DRAFT-') ? '—' : $inv->invoice_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2">{{ $inv->invoice_date?->format('d M Y') }}</td>
                                    <td class="px-4 py-2">{{ $inv->customer?->name }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ $inv->currency }} {{ number_format((float) $inv->grand_total, 2) }}</td>
                                    <td class="px-4 py-2"><span class="text-xs px-2 py-0.5 rounded bg-gray-100">{{ ucfirst(str_replace('_',' ', $inv->status)) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
