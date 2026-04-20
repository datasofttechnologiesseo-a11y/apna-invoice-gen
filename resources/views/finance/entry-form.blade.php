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
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
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
