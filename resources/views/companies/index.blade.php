<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Your companies</h2>
            <a href="{{ route('companies.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm">+ Add company</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded">{{ session('status') }}</div>
            @endif
            @if ($errors->has('company'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded">{{ $errors->first('company') }}</div>
            @endif

            <div class="p-4 rounded-lg bg-brand-50 border border-brand-100 text-sm text-brand-900">
                <strong>Active company:</strong> {{ $active->name }}
                <span class="text-brand-700">· New invoices and customers are created under this company.</span>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                @if ($companies->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        No companies yet. <a href="{{ route('companies.create') }}" class="text-brand-700 hover:underline">Add your first →</a>
                    </div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($companies as $company)
                            @php $isActive = $company->id === $active->id; @endphp
                            <li class="p-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 {{ $isActive ? 'bg-brand-50/40' : '' }}">
                                <div class="min-w-0 flex-1 flex items-start gap-4">
                                    @if ($company->logo_path && file_exists(public_path('storage/' . $company->logo_path)))
                                        <img src="{{ asset('storage/' . $company->logo_path) }}" alt="{{ $company->name }} logo" class="h-10 w-auto max-w-[80px] object-contain bg-white ring-1 ring-gray-200 rounded p-1 flex-shrink-0">
                                    @else
                                        <div class="h-10 w-10 rounded bg-brand-100 text-brand-700 font-bold flex items-center justify-center flex-shrink-0">{{ strtoupper(substr($company->name, 0, 1)) }}</div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-display font-bold text-gray-900 truncate">{{ $company->name }}</span>
                                            @if ($isActive)
                                                <span class="px-2 py-0.5 rounded-full bg-brand-700 text-white text-[10px] font-bold uppercase tracking-wider">Active</span>
                                            @endif
                                            @if ($company->gstin)
                                                <span class="text-xs text-gray-500 font-mono">{{ $company->gstin }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 text-sm text-gray-600 truncate">
                                            {{ $company->city ? $company->city . ', ' : '' }}{{ $company->state?->name ?? $company->country }}
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $company->customers_count }} {{ Str::plural('customer', $company->customers_count) }} ·
                                            {{ $company->invoices_count }} {{ Str::plural('invoice', $company->invoices_count) }} ·
                                            Prefix <span class="font-mono">{{ $company->invoice_prefix }}-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                                    @if (! $isActive)
                                        <form method="POST" action="{{ route('companies.switch', $company) }}" class="inline">
                                            @csrf
                                            <button class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-semibold rounded">Switch to this</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('companies.edit', $company) }}" class="px-3 py-1.5 bg-white ring-1 ring-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded">Edit</a>
                                    @if ($company->invoices_count === 0)
                                        <x-confirm-form
                                            :action="route('companies.destroy', $company)"
                                            method="DELETE"
                                            title="Delete {{ $company->name }}?"
                                            message="Its customers will also be removed. This cannot be undone."
                                            confirmLabel="Delete company"
                                            confirmClass="bg-red-600 hover:bg-red-700"
                                            tone="danger">
                                            <button type="button" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-semibold rounded">Delete</button>
                                        </x-confirm-form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
