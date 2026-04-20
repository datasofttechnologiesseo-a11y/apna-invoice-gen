<x-onboarding-layout step="done">
    <div class="bg-white rounded-2xl shadow-brand ring-1 ring-gray-100 overflow-hidden text-center">
        <div class="relative p-8 md:p-12 bg-gradient-to-br from-money-600 via-money-500 to-brand-600 text-white overflow-hidden">
            <div class="absolute inset-0 bg-grid-soft bg-grid-soft opacity-20"></div>
            <div class="relative">
                <div class="mx-auto w-20 h-20 rounded-full bg-white/20 backdrop-blur ring-4 ring-white/30 flex items-center justify-center">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h1 class="mt-6 font-display text-3xl md:text-4xl font-extrabold">You're all set!</h1>
                <p class="mt-3 text-money-50 max-w-xl mx-auto text-lg">Your business is ready to issue GST-compliant invoices. Let's create your first one.</p>
            </div>
        </div>

        <div class="p-6 md:p-10">
            <div class="grid md:grid-cols-3 gap-4 text-left">
                <div class="p-4 rounded-xl bg-brand-50 ring-1 ring-brand-100">
                    <div class="w-10 h-10 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>
                    </div>
                    <h3 class="mt-3 font-semibold text-gray-900">Business details</h3>
                    <p class="text-sm text-gray-600 mt-0.5">{{ $company->name }}</p>
                    <a href="{{ route('company.edit') }}" class="mt-2 inline-block text-xs text-brand-700 hover:underline font-semibold">Edit →</a>
                </div>
                <div class="p-4 rounded-xl bg-accent-50 ring-1 ring-accent-100">
                    <div class="w-10 h-10 rounded-lg bg-accent-100 text-accent-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h3 class="mt-3 font-semibold text-gray-900">Customer book</h3>
                    <p class="text-sm text-gray-600 mt-0.5">{{ $hasCustomer ? '1 customer added' : 'None yet — add when ready' }}</p>
                    <a href="{{ route('customers.index') }}" class="mt-2 inline-block text-xs text-accent-700 hover:underline font-semibold">Manage →</a>
                </div>
                <div class="p-4 rounded-xl bg-money-50 ring-1 ring-money-100">
                    <div class="w-10 h-10 rounded-lg bg-money-100 text-money-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="mt-3 font-semibold text-gray-900">Ready to invoice</h3>
                    <p class="text-sm text-gray-600 mt-0.5">Bill customers in seconds</p>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                @if ($hasCustomer)
                    <a href="{{ route('invoices.templates') }}" class="inline-flex items-center px-8 py-4 bg-brand-700 hover:bg-brand-800 text-white font-bold rounded-xl shadow-brand transition text-lg">
                        Create first invoice
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M5 12h13"/></svg>
                    </a>
                @else
                    <a href="{{ route('customers.create') }}" class="inline-flex items-center px-8 py-4 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-xl shadow-brand transition text-lg">
                        Add a customer →
                    </a>
                @endif
                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-8 py-4 bg-white border-2 border-gray-200 hover:border-brand-500 text-gray-800 font-semibold rounded-xl transition">
                    Go to dashboard
                </a>
            </div>

            <div class="mt-8 pt-6 border-t text-sm text-gray-500">
                Need help? Visit <a href="/" class="text-brand-700 hover:underline">the FAQ</a> or contact <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer" class="text-brand-700 hover:underline font-medium">Datasoft Technologies</a> support.
            </div>
        </div>
    </div>
</x-onboarding-layout>
