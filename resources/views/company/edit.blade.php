<x-app-layout title="Company settings">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
                {{ $company->exists ? 'Edit ' . $company->name : 'New company' }}
            </h1>
            <a href="{{ route('companies.index') }}" class="text-sm text-gray-500 hover:text-gray-700">All companies</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-money-50 border border-money-200 text-money-800 rounded-lg text-sm">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}" enctype="multipart/form-data"
                  class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                @csrf
                @if ($company->exists) @method('PATCH') @endif
                @if (request('from'))
                    <input type="hidden" name="from" value="{{ request('from') }}">
                @endif
                <input type="hidden" name="default_currency" value="INR">

                @if ($errors->any())
                    <div class="m-6 mb-0 p-4 rounded-lg bg-danger-50 border border-danger-200 text-danger-800 text-sm" role="alert">
                        <div class="font-semibold mb-1">Please fix the following before saving:</div>
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                {{-- Business identity --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Business identity</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Appears on your invoice letterhead and drives GST place-of-supply detection.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="name" value="Business name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $company->name)" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="gstin" value="GSTIN (15-char)" />
                            <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('gstin', $company->gstin)" maxlength="15" />
                            <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="pan" value="PAN" />
                            <x-text-input id="pan" name="pan" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('pan', $company->pan)" maxlength="10" placeholder="AABCU9603R" />
                            <x-input-error :messages="$errors->get('pan')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="inline-flex items-start gap-2 cursor-pointer">
                                <input type="hidden" name="composition_dealer" value="0">
                                <input type="checkbox" id="composition_dealer" name="composition_dealer" value="1"
                                       class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600"
                                       @checked(old('composition_dealer', $company->composition_dealer))>
                                <span class="text-sm">
                                    <span class="font-semibold text-gray-900">Registered under Composition Scheme</span>
                                    <span class="block text-xs text-gray-500 mt-0.5">Section 10 of CGST Act. When enabled, your invoices print as "Bill of Supply" with the mandatory "composition taxable person, not eligible to collect tax on supplies" declaration.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </section>

                {{-- Contact --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Contact</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Shown on the invoice so customers can reach you.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $company->email)" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" class="mt-1 block w-full" :value="old('phone', $company->phone)" placeholder="+91 98200 00000" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="website" value="Website" />
                            <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $company->website)" placeholder="https://..." />
                            <x-input-error :messages="$errors->get('website')" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Registered address --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Registered address</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Your state sets the GST origin for every invoice.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="address_line1" value="Address line 1" />
                            <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $company->address_line1)" />
                        </div>
                        <div>
                            <x-input-label for="address_line2" value="Address line 2" />
                            <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $company->address_line2)" />
                        </div>
                        <div>
                            <x-input-label for="city" value="City" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $company->city)" />
                        </div>
                        <div>
                            <x-input-label for="state_id" value="State *" />
                            <select id="state_id" name="state_id" required class="mt-1 block w-full border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm">
                                <option value="">- Select -</option>
                                @foreach ($states as $state)
                                    <option value="{{ $state->id }}" @selected(old('state_id', $company->state_id) == $state->id)>{{ $state->name }} ({{ $state->gst_code }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('state_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="postal_code" value="Postal code" />
                            <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $company->postal_code)" />
                        </div>
                        <div>
                            <x-input-label for="country" value="Country" />
                            <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $company->country)" />
                        </div>
                    </div>
                </section>

                {{-- Branding --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Branding</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Your logo and signature print on every invoice PDF.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="logo" value="Logo (PNG/JPG, ≤ 2 MB)" />
                            @if ($company->logo_path)
                                <img src="{{ Storage::url($company->logo_path) }}" alt="Logo" class="mt-2 h-16 border rounded bg-white p-1">
                            @endif
                            <input id="logo" name="logo" type="file" accept="image/*" class="mt-1 block w-full text-sm">
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="signature" value="Signature image (optional, ≤ 1 MB)" />
                            @if ($company->signature_path)
                                <img src="{{ Storage::url($company->signature_path) }}" alt="Signature" class="mt-2 h-12 border rounded bg-white p-1">
                            @endif
                            <input id="signature" name="signature" type="file" accept="image/*" class="mt-1 block w-full text-sm">
                            <x-input-error :messages="$errors->get('signature')" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Invoice numbering --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Invoice numbering</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Set a GST-friendly, financial-year series for your invoices.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="invoice_prefix" value="Invoice prefix *" />
                            <x-text-input id="invoice_prefix" name="invoice_prefix" type="text" class="mt-1 block w-full" :value="old('invoice_prefix', $company->invoice_prefix)" required />
                            <p class="text-xs text-gray-500 mt-1">Used only when no format template is set (below).</p>
                        </div>
                        <div>
                            <x-input-label for="invoice_number_padding" value="Number padding (digits) *" />
                            <x-text-input id="invoice_number_padding" name="invoice_number_padding" type="number" min="1" max="8" class="mt-1 block w-full" :value="old('invoice_number_padding', $company->invoice_number_padding)" required />
                            <p class="text-xs text-gray-500 mt-1">0001 vs 00001 etc. Applied to {N} placeholder.</p>
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="invoice_number_format" value="Invoice number format (recommended for GST: FY-reset)" />
                            <x-text-input id="invoice_number_format" name="invoice_number_format" type="text" class="mt-1 block w-full font-mono" :value="old('invoice_number_format', $company->invoice_number_format)" placeholder="INV/{FY}/{N}" />
                            <p class="text-xs text-gray-500 mt-1">
                                Tokens: <code class="bg-gray-100 text-gray-700 px-1 rounded">{FY}</code> → 2025-26 ·
                                <code class="bg-gray-100 text-gray-700 px-1 rounded">{FY_SHORT}</code> → 25-26 ·
                                <code class="bg-gray-100 text-gray-700 px-1 rounded">{YYYY}</code> → 2025 ·
                                <code class="bg-gray-100 text-gray-700 px-1 rounded">{N}</code> → 0001 (sequence).
                                Counter auto-resets on 1 April. Leave blank to use the simple <code class="bg-gray-100 text-gray-700 px-1 rounded">prefix-0001</code> format.
                            </p>
                        </div>
                    </div>

                    @php
                        $currentCounter = (int) ($company->invoice_counter ?? 0);
                        $nextSequenceNumber = $currentCounter + 1;
                        $nextPreview = $company->exists ? $company->nextInvoiceNumber() : '';
                    @endphp
                    <div class="mt-5 p-4 bg-accent-50 border border-accent-200 rounded-lg">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-accent-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-gray-900 text-sm">Continuing a series from another tool?</div>
                                <p class="mt-0.5 text-xs text-gray-600">
                                    If you already issued invoices on another tool this financial year, set your next number here so the series stays unbroken. Skip if you're starting fresh.
                                </p>
                                <div class="mt-3">
                                    <x-input-label for="next_invoice_number" value="Next invoice sequence (number only)" class="text-xs" />
                                    <x-text-input id="next_invoice_number" name="next_invoice_number" type="number" min="1" class="mt-1 block w-full sm:w-64 font-mono" :value="old('next_invoice_number')" placeholder="{{ $nextSequenceNumber }}" />
                                    <p class="mt-1 text-[11px] text-gray-500">
                                        Currently set to <strong class="font-mono">{{ $nextSequenceNumber }}</strong>. Enter a higher number to skip ahead.
                                        @if ($company->exists && $nextPreview)
                                            Preview: <strong class="font-mono">{{ $nextPreview }}</strong>
                                        @endif
                                    </p>
                                    <x-input-error :messages="$errors->get('next_invoice_number')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Invoice defaults --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Invoice defaults</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Boilerplate that prints at the foot of every invoice.</p>
                    <div class="mt-5 space-y-5">
                        <div>
                            <x-input-label for="default_terms" value="Default terms & conditions" />
                            <textarea id="default_terms" name="default_terms" rows="4" class="mt-1 block w-full border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm" placeholder="1. Payment due within 30 days.&#10;2. Late payment attracts 2% monthly interest.">{{ old('default_terms', $company->default_terms) }}</textarea>
                        </div>
                        <div>
                            <x-input-label for="declaration" value="Declaration" />
                            <textarea id="declaration" name="declaration" rows="2" class="mt-1 block w-full border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm" placeholder="We declare that this invoice shows the actual price of the goods/services described and that all particulars are true and correct.">{{ old('declaration', $company->declaration) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Standard legal declaration required by CBIC for tax invoices.</p>
                        </div>
                    </div>
                </section>

                {{-- Bank / payment --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Bank &amp; payment details</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Printed on the invoice PDF so customers know how to pay.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="bank_name" value="Bank name" />
                            <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" :value="old('bank_name', $company->bank_name)" placeholder="HDFC Bank" />
                        </div>
                        <div>
                            <x-input-label for="bank_branch" value="Branch" />
                            <x-text-input id="bank_branch" name="bank_branch" type="text" class="mt-1 block w-full" :value="old('bank_branch', $company->bank_branch)" placeholder="Powai" />
                        </div>
                        <div>
                            <x-input-label for="bank_account_number" value="Account number" />
                            <x-text-input id="bank_account_number" name="bank_account_number" type="text" class="mt-1 block w-full font-mono" :value="old('bank_account_number', $company->bank_account_number)" />
                        </div>
                        <div>
                            <x-input-label for="bank_ifsc" value="IFSC code" />
                            <x-text-input id="bank_ifsc" name="bank_ifsc" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('bank_ifsc', $company->bank_ifsc)" maxlength="11" placeholder="HDFC0001234" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="upi_id" value="UPI ID (optional)" />
                            <x-text-input id="upi_id" name="upi_id" type="text" class="mt-1 block w-full font-mono" :value="old('upi_id', $company->upi_id)" placeholder="yourname@okhdfcbank" />
                            <p class="mt-1 text-xs text-gray-500">Customers can scan-and-pay from the invoice PDF.</p>
                        </div>
                    </div>
                </section>

                {{-- Financial-year lock --}}
                <section class="p-6 sm:p-8">
                    <h3 class="text-sm font-bold text-gray-900">Financial-year lock</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Protect closed-year books from accidental edits.</p>
                    <div class="mt-5">
                        <x-input-label for="books_locked_until" value="Lock books up to (optional)" />
                        <x-text-input id="books_locked_until" name="books_locked_until" type="date" class="mt-1 block w-full sm:w-64"
                                      :value="old('books_locked_until', $company->books_locked_until?->toDateString())" />
                        <p class="text-xs text-gray-500 mt-1">
                            After your CA closes a financial year, set this to <strong>31-Mar-YYYY</strong>. No invoice, expense, or cash sale dated on or before this date can be added, edited, or deleted. <span class="text-accent-700">Leave empty to keep all entries editable.</span>
                        </p>
                        <x-input-error :messages="$errors->get('books_locked_until')" class="mt-2" />
                    </div>
                </section>

                {{-- Action bar --}}
                <div class="px-6 sm:px-8 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-800">Back to dashboard</a>
                    <x-primary-button>{{ __('Save profile') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
