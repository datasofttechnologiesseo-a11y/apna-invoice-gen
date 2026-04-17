<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Invoice {{ str_starts_with($invoice->invoice_number, 'DRAFT-') ? '(draft)' : $invoice->invoice_number }}
            </h2>
            <div class="flex items-center gap-2">
                @if ($invoice->isEditable())
                    <a href="{{ route('invoices.edit', $invoice) }}" class="px-3 py-1.5 bg-gray-200 text-gray-800 rounded text-sm hover:bg-gray-300">Edit</a>
                    <form method="POST" action="{{ route('invoices.finalize', $invoice) }}" class="inline" onsubmit="return confirm('Finalize this invoice? It cannot be edited after this.')">
                        @csrf
                        <button class="px-3 py-1.5 bg-brand-700 text-white rounded text-sm hover:bg-brand-800 shadow-sm">Finalize</button>
                    </form>
                @endif
                <a href="{{ route('invoices.pdf', $invoice) }}" class="px-3 py-1.5 bg-gray-800 text-white rounded text-sm hover:bg-gray-900">Download PDF</a>
                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="px-3 py-1.5 bg-white border text-gray-700 rounded text-sm hover:bg-gray-50">Print view</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded">{{ session('status') }}</div>
            @endif

            @if ($invoice->balance > 0 && ! $invoice->isDraft() && $invoice->status !== 'cancelled')
                <form method="POST" action="{{ route('invoices.payments', $invoice) }}" class="bg-white shadow sm:rounded-lg p-4 flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="text-sm text-gray-600">Record a payment ({{ $invoice->currency }})</label>
                        <input type="number" name="amount" step="0.01" min="0.01" max="{{ $invoice->balance }}" required class="mt-1 block w-full border-gray-300 rounded shadow-sm">
                    </div>
                    <button class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Add payment</button>
                </form>
            @endif

            <div class="bg-white shadow sm:rounded-lg">
                @include('invoices.partials.document', ['invoice' => $invoice, 'amountInWords' => $amountInWords])
            </div>
        </div>
    </div>
</x-app-layout>
