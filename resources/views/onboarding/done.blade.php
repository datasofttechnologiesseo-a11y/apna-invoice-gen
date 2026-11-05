<x-onboarding-layout step="done">
    <div class="bg-white rounded-2xl shadow-brand ring-1 ring-gray-100 overflow-hidden text-center">
        <div class="relative p-8 md:p-12 bg-gradient-to-br from-money-600 via-money-500 to-brand-600 text-white overflow-hidden">
            <div class="absolute inset-0 bg-grid-soft bg-grid-soft opacity-20"></div>
            <div class="relative">
                <div class="mx-auto w-20 h-20 rounded-full bg-white/20 backdrop-blur ring-4 ring-white/30 flex items-center justify-center">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h1 class="mt-6 font-display text-3xl md:text-4xl font-extrabold">{{ $hasCustomer ? 'Setup complete' : 'Business details saved' }}</h1>
                <p class="mt-3 text-money-50 max-w-xl mx-auto text-lg">
                    {{ $hasCustomer ? 'Your business is ready to issue GST invoices.' : 'Add your customer on the invoice form, then issue the invoice.' }}
                </p>
            </div>
        </div>

        <div class="p-6 md:p-10">
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 text-left">
                <div class="p-4 rounded-xl bg-brand-50 ring-1 ring-brand-100">
                    <div class="w-10 h-10 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>
                    </div>
                    <h2 class="mt-3 font-semibold text-gray-900">Business details</h2>
                    <p class="text-sm text-gray-600 mt-0.5">{{ $company->name }}</p>
                    <a href="{{ route('company.edit') }}" class="mt-2 inline-block text-xs text-brand-700 hover:underline font-semibold">Edit →</a>
                </div>
                <div class="p-4 rounded-xl bg-accent-50 ring-1 ring-accent-100">
                    <div class="w-10 h-10 rounded-lg bg-accent-100 text-accent-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h2 class="mt-3 font-semibold text-gray-900">Customers</h2>
                    <p class="text-sm text-gray-600 mt-0.5">{{ $hasCustomer ? '1 customer added' : 'None added yet' }}</p>
                    <a href="{{ route('customers.index') }}" class="mt-2 inline-block text-xs text-accent-700 hover:underline font-semibold">Manage →</a>
                </div>
                <div class="p-4 rounded-xl bg-money-50 ring-1 ring-money-100">
                    <div class="w-10 h-10 rounded-lg bg-money-100 text-money-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h2 class="mt-3 font-semibold text-gray-900">Invoices</h2>
                    <p class="text-sm text-gray-600 mt-0.5">Create, send and track GST invoices</p>
                </div>
                <div class="p-4 rounded-xl bg-accent-50 ring-1 ring-accent-100">
                    <div class="w-10 h-10 rounded-lg bg-accent-100 text-accent-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18M7 16l4-4 3 3 5-7"/></svg>
                    </div>
                    <h2 class="mt-3 font-semibold text-gray-900">Profit &amp; Loss</h2>
                    <p class="text-sm text-gray-600 mt-0.5">Record expenses and cash purchases to see profit</p>
                    <a href="{{ route('finance.index') }}" class="mt-2 inline-block text-xs text-accent-700 hover:underline font-semibold">Open report →</a>
                </div>
            </div>

            {{-- One main step. The customer added in setup comes pre-selected; without
                 one, the invoice form adds a customer without leaving the page. --}}
            <div class="mt-8 flex flex-col items-center gap-3">
                <a href="{{ route('invoices.create', array_filter(['customer_id' => $customer?->id])) }}"
                   class="inline-flex items-center px-8 py-4 bg-brand-700 hover:bg-brand-800 text-white font-bold rounded-xl shadow-brand transition text-lg">
                    Create your first invoice
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M5 12h13"/></svg>
                </a>
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm">
                    @unless ($hasCustomer)
                        <a href="{{ route('customers.create') }}" class="text-brand-700 hover:underline font-medium">Add a customer first</a>
                        <span class="text-gray-300" aria-hidden="true">·</span>
                    @endunless
                    <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900 hover:underline font-medium">Go to dashboard</a>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t text-sm text-gray-500">
                Need help? Read the <a href="{{ route('help') }}" class="text-brand-700 hover:underline font-medium">help guide</a>,
                call or WhatsApp <a href="{{ config('seo.contact.whatsapp_url') }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline font-medium font-mono">{{ config('seo.contact.phone_display') }}</a>,
                or <a href="{{ route('pages.contact') }}" class="text-brand-700 hover:underline font-medium">send us a message</a>.
            </div>
        </div>
    </div>
</x-onboarding-layout>
