<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
                {{ $expense->exists ? 'Edit expense' : 'Add expense' }}
            </h2>
            <a href="{{ route('finance.expenses') }}" class="text-sm text-gray-500 hover:text-gray-700">← All expenses</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('finance.partials.tabs')

            @if (session('error'))
                <div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">🔒 {{ session('error') }}</div>
            @endif

            @unless ($expense->exists)
                {{-- ─── Chooser: which kind of expense are you recording? ─── --}}
                <div>
                    <div class="mb-3">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">How was this paid?</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Choose the option that matches the bill you have. Both update your P&amp;L the same way.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        {{-- Card 1: Standard Expense (selected/default) --}}
                        <div class="relative bg-white border-2 border-brand-600 rounded-xl p-5 shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-11 h-11 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h2m3 0h5m-1-9h2a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h2"/>
                                        <rect x="8" y="4" width="8" height="4" rx="1" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900">Standard Expense</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Bank · UPI · Card · Cheque</p>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 mt-3">
                                Use this when you have a <strong>proper bill or tax invoice</strong> from the vendor — paid via bank transfer, UPI, card, or cheque.
                            </p>
                            <ul class="mt-3 space-y-1 text-xs text-gray-600">
                                <li class="flex items-start gap-1.5"><span class="text-emerald-600 font-bold">✓</span><span>GST input credit (ITC) claimable if vendor is GST-registered</span></li>
                                <li class="flex items-start gap-1.5"><span class="text-emerald-600 font-bold">✓</span><span>Just fill amount, date &amp; category — fast entry</span></li>
                                <li class="flex items-start gap-1.5"><span class="text-emerald-600 font-bold">✓</span><span>Best for office rent, salaries, SaaS, utilities</span></li>
                            </ul>
                            <div class="mt-4 text-xs text-brand-700 font-semibold inline-flex items-center gap-1">
                                ↓ Continue with the form below
                            </div>
                        </div>

                        {{-- Card 2: Cash Memo --}}
                        <a href="{{ route('finance.cash-memos.create') }}" class="group relative bg-white border-2 border-gray-200 hover:border-amber-500 hover:shadow-md transition-all rounded-xl p-5 shadow-sm block">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-11 h-11 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900 group-hover:text-amber-700">Cash Memo <span class="text-[10px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded uppercase tracking-wider align-middle ml-1">Generates voucher</span></h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Cash purchase without proper bill</p>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 mt-3">
                                Use this when paying <strong>cash to a small or unregistered vendor</strong> who doesn't issue a tax invoice. Common for petty cash, repairs, stationery, food, etc.
                            </p>
                            <ul class="mt-3 space-y-1 text-xs text-gray-600">
                                <li class="flex items-start gap-1.5"><span class="text-amber-600 font-bold">✓</span><span>Generates a printable A4 Cash Memo as your purchase voucher</span></li>
                                <li class="flex items-start gap-1.5"><span class="text-amber-600 font-bold">✓</span><span>Auto-creates the matching expense entry — no double work</span></li>
                                <li class="flex items-start gap-1.5"><span class="text-amber-600 font-bold">✓</span><span>FY-based memo number (e.g. CM/26-27/0001) for proper records</span></li>
                            </ul>
                            <div class="mt-4 text-xs text-amber-700 font-semibold inline-flex items-center gap-1 group-hover:underline">
                                Create Cash Memo →
                            </div>
                        </a>

                    </div>
                </div>
            @endunless

            <div class="p-6 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ $expense->exists ? route('finance.expenses.update', $expense) : route('finance.expenses.store') }}" class="space-y-6">
                    @csrf
                    @if ($expense->exists) @method('PATCH') @endif

                    @if ($errors->any())
                        <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                            <div class="font-semibold mb-1">Please fix the following:</div>
                            <ul class="list-disc pl-5 space-y-0.5">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="entry_date" value="Date *" />
                            <x-text-input id="entry_date" name="entry_date" type="date" class="mt-1 block w-full"
                                          :value="old('entry_date', $expense->entry_date?->toDateString() ?? now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('entry_date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="category" value="Category *" />
                            <select id="category" name="category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                                <option value="">— Select —</option>
                                @foreach (config('expense_categories') as $key => $cfg)
                                    <option value="{{ $key }}" @selected(old('category', $expense->category) === $key) title="{{ $cfg['desc'] }}">{{ $cfg['label'] }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="description" value="Description *" />
                            <x-text-input id="description" name="description" type="text" class="mt-1 block w-full"
                                          :value="old('description', $expense->description)"
                                          placeholder="E.g. July office rent, AWS subscription May, Flight BOM→DEL" required maxlength="255" />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="vendor_name" value="Vendor / paid to" />
                            <x-text-input id="vendor_name" name="vendor_name" type="text" class="mt-1 block w-full"
                                          :value="old('vendor_name', $expense->vendor_name)" maxlength="120"
                                          placeholder="E.g. Urban Properties LLP" />
                            <x-input-error :messages="$errors->get('vendor_name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="payment_method" value="Payment method" />
                            <select id="payment_method" name="payment_method" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (['bank' => 'Bank transfer / NEFT', 'upi' => 'UPI', 'card' => 'Card', 'cash' => 'Cash', 'cheque' => 'Cheque', 'other' => 'Other'] as $v => $label)
                                    <option value="{{ $v }}" @selected(old('payment_method', $expense->payment_method ?? 'bank') === $v)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="amount" value="Amount (excl. GST) *" />
                            <div class="mt-1 relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">₹</span>
                                <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="block w-full pl-8"
                                              :value="old('amount', $expense->amount)" required />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">The taxable value — what it actually cost you, before GST.</p>
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="gst_amount" value="GST paid (Input Tax Credit)" />
                            <div class="mt-1 relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">₹</span>
                                <x-text-input id="gst_amount" name="gst_amount" type="number" step="0.01" min="0" class="block w-full pl-8"
                                              :value="old('gst_amount', $expense->gst_amount)" placeholder="0.00" />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Enter only if you have a valid tax invoice from the vendor. You can claim this back on GSTR-3B.</p>
                            <x-input-error :messages="$errors->get('gst_amount')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="reference_number" value="Reference number" />
                            <x-text-input id="reference_number" name="reference_number" type="text" class="mt-1 block w-full font-mono"
                                          :value="old('reference_number', $expense->reference_number)" maxlength="50"
                                          placeholder="Cheque no. / UPI ref / Txn ID" />
                        </div>
                        <div></div>

                        <div class="md:col-span-2">
                            <x-input-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $expense->notes) }}</textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <a href="{{ route('finance.expenses') }}" class="text-sm text-gray-500 hover:underline">← Cancel</a>
                        <div class="flex items-center gap-3">
                            @if ($expense->exists)
                                <form method="POST" action="{{ route('finance.expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-3 py-2 text-red-600 hover:bg-red-50 rounded text-sm font-semibold">Delete</button>
                                </form>
                            @endif
                            <x-primary-button>{{ $expense->exists ? 'Save changes' : 'Add expense' }}</x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
