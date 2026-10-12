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
                <div class="p-4 bg-money-50 border border-money-200 text-money-800 rounded-lg text-sm" role="status">{{ session('status') }}</div>
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
                        <div class="font-semibold mb-1">Correct the following before saving:</div>
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                {{-- Business identity --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Business identity</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Printed on the invoice letterhead.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="name" value="Business name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $company->name)" required autocomplete="organization" />
                            <x-input-error :messages="$errors->get('name')" for="name" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="gstin" value="GSTIN" />
                            <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('gstin', $company->gstin)" maxlength="15" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="27AAACT2727Q1ZW" />
                            <x-input-error :messages="$errors->get('gstin')" for="gstin" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="pan" value="PAN" />
                            <x-text-input id="pan" name="pan" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('pan', $company->pan)" maxlength="10" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="AABCU9603R" />
                            <x-input-error :messages="$errors->get('pan')" for="pan" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="inline-flex items-start gap-2 cursor-pointer">
                                <input type="hidden" name="composition_dealer" value="0">
                                <input type="checkbox" id="composition_dealer" name="composition_dealer" value="1" aria-describedby="composition_dealer-hint"
                                       class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600"
                                       @checked(old('composition_dealer', $company->composition_dealer))>
                                <span class="text-sm">
                                    <span class="font-semibold text-gray-900">Registered under Composition Scheme</span>
                                    <span id="composition_dealer-hint" class="block text-xs text-gray-500 mt-0.5">Section 10, CGST Act. Invoices print as "Bill of Supply" with the required composition declaration.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </section>

                {{-- Contact --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Contact</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Printed on invoices.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $company->email)" autocomplete="email" />
                            <x-input-error :messages="$errors->get('email')" for="email" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" class="mt-1 block w-full" :value="old('phone', $company->phone)" placeholder="+91 98200 00000" />
                            <x-input-error :messages="$errors->get('phone')" for="phone" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="website" value="Website" />
                            <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $company->website)" autocomplete="url" placeholder="https://..." />
                            <x-input-error :messages="$errors->get('website')" for="website" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Registered address --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Registered address</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Your state decides whether CGST and SGST or IGST applies.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="address_line1" value="Address line 1" />
                            <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $company->address_line1)" autocomplete="address-line1" />
                        </div>
                        <div>
                            <x-input-label for="address_line2" value="Address line 2" />
                            <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $company->address_line2)" autocomplete="address-line2" />
                        </div>
                        <div>
                            <x-input-label for="city" value="City" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $company->city)" autocomplete="address-level2" />
                        </div>
                        <div>
                            <x-input-label for="state_id" value="State *" />
                            <select id="state_id" name="state_id" required class="mt-1 block w-full border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm">
                                <option value="">Select state</option>
                                @foreach ($states as $state)
                                    <option value="{{ $state->id }}" @selected(old('state_id', $company->state_id) == $state->id)>{{ $state->name }} ({{ $state->gst_code }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('state_id')" for="state_id" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="postal_code" value="PIN code" />
                            <x-text-input id="postal_code" name="postal_code" type="text" inputmode="numeric" maxlength="6" autocomplete="postal-code" class="mt-1 block w-full" :value="old('postal_code', $company->postal_code)" placeholder="400076" />
                        </div>
                        <div>
                            <x-input-label for="country" value="Country" />
                            <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $company->country)" autocomplete="country-name" />
                        </div>
                    </div>
                </section>

                {{-- Branding --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Branding</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Printed on invoice PDFs.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="logo" value="Logo (PNG or JPG, max 2 MB)" />
                            @if ($company->logo_path)
                                <img src="{{ Storage::url($company->logo_path) }}" alt="Current logo" class="mt-2 h-16 border rounded bg-white p-1">
                            @endif
                            <input id="logo" name="logo" type="file" accept="image/*" class="mt-1 block w-full text-sm">
                            <x-input-error :messages="$errors->get('logo')" for="logo" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="signature" value="Signature image (optional, max 1 MB)" />
                            @if ($company->signature_path)
                                <img src="{{ Storage::url($company->signature_path) }}" alt="Current signature" class="mt-2 h-12 border rounded bg-white p-1">
                            @endif
                            <input id="signature" name="signature" type="file" accept="image/*" class="mt-1 block w-full text-sm">
                            <x-input-error :messages="$errors->get('signature')" for="signature" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Invoice numbering --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Invoice numbering</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Number series for new invoices.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="invoice_prefix" value="Invoice prefix *" />
                            <x-text-input id="invoice_prefix" name="invoice_prefix" type="text" class="mt-1 block w-full" :value="old('invoice_prefix', $company->invoice_prefix)" required aria-describedby="invoice_prefix-hint" />
                            <p id="invoice_prefix-hint" class="text-xs text-gray-500 mt-1">Used when the number format below is blank.</p>
                        </div>
                        <div>
                            <x-input-label for="invoice_number_padding" value="Number padding (digits) *" />
                            <x-text-input id="invoice_number_padding" name="invoice_number_padding" type="number" min="1" max="8" inputmode="numeric" class="mt-1 block w-full" :value="old('invoice_number_padding', $company->invoice_number_padding)" required aria-describedby="invoice_number_padding-hint" />
                            <p id="invoice_number_padding-hint" class="text-xs text-gray-500 mt-1">Digits in the running number. 4 gives 0001.</p>
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="invoice_number_format" value="Invoice number format" />
                            <x-text-input id="invoice_number_format" name="invoice_number_format" type="text" class="mt-1 block w-full font-mono" :value="old('invoice_number_format', $company->invoice_number_format)" placeholder="INV/{FY}/{N}" aria-describedby="invoice_number_format-hint" />
                            @php
                                // Rule 46(b): warn (never block) when the series a company already
                                // uses would print more than 16 characters or other symbols.
                                $numberPreview = \App\Http\Controllers\CompanyController::previewInvoiceNumber(
                                    (string) ($company->invoice_prefix ?? 'INV'),
                                    $company->invoice_number_format,
                                    (int) ($company->invoice_number_padding ?? 4),
                                );
                                $numberTooLong = strlen($numberPreview) > 16 || ! preg_match('/^[A-Za-z0-9\/-]+$/', $numberPreview);
                            @endphp
                            @if ($numberTooLong)
                                <p class="mt-2 text-xs text-accent-800 bg-accent-50 border border-accent-200 rounded px-2 py-1.5">
                                    ⚠ Your current series prints <span class="font-mono">{{ $numberPreview }}</span> ({{ strlen($numberPreview) }} characters).
                                    Rule 46(b) allows up to 16 characters, using only letters, digits, "-" and "/".
                                    Shorten the prefix or format. Invoices already issued are not affected.
                                </p>
                            @endif
                            @error('invoice_number_format')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                            <p id="invoice_number_format-hint" class="text-xs text-gray-500 mt-1">
                                Tokens: <code class="bg-gray-100 text-gray-700 px-1 rounded">{FY}</code> → 2025-26 ·
                                <code class="bg-gray-100 text-gray-700 px-1 rounded">{FY_SHORT}</code> → 25-26 ·
                                <code class="bg-gray-100 text-gray-700 px-1 rounded">{YYYY}</code> → 2025 ·
                                <code class="bg-gray-100 text-gray-700 px-1 rounded">{N}</code> → 0001 (running number).
                                Numbering restarts on 1 April. Leave blank to use <code class="bg-gray-100 text-gray-700 px-1 rounded">prefix-0001</code>.
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
                            <svg class="w-5 h-5 text-accent-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-gray-900 text-sm">Continuing a series from other software?</div>
                                <p class="mt-0.5 text-xs text-gray-600">
                                    If you issued invoices elsewhere this financial year, enter the next number to keep the series continuous.
                                </p>
                                <div class="mt-3">
                                    <x-input-label for="next_invoice_number" value="Next running number (digits only)" class="text-xs" />
                                    <x-text-input id="next_invoice_number" name="next_invoice_number" type="number" min="1" inputmode="numeric" class="mt-1 block w-full sm:w-64 font-mono" :value="old('next_invoice_number')" placeholder="{{ $nextSequenceNumber }}" aria-describedby="next_invoice_number-hint" />
                                    <p id="next_invoice_number-hint" class="mt-1 text-[11px] text-gray-500">
                                        Currently <strong class="font-mono">{{ $nextSequenceNumber }}</strong>. Enter a higher number to skip ahead.
                                        @if ($company->exists && $nextPreview)
                                            Preview: <strong class="font-mono">{{ $nextPreview }}</strong>
                                        @endif
                                    </p>
                                    <x-input-error :messages="$errors->get('next_invoice_number')" for="next_invoice_number" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Invoice defaults --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Invoice defaults</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Printed at the foot of every invoice.</p>
                    <div class="mt-5 space-y-5">
                        <div>
                            <x-input-label for="default_terms" value="Default terms & conditions" />
                            <textarea id="default_terms" name="default_terms" rows="4" class="mt-1 block w-full border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm" placeholder="1. Payment due within 30 days.&#10;2. Late payment attracts 2% monthly interest.">{{ old('default_terms', $company->default_terms) }}</textarea>
                        </div>
                        <div>
                            <x-input-label for="declaration" value="Declaration" />
                            <textarea id="declaration" name="declaration" rows="2" class="mt-1 block w-full border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm" aria-describedby="declaration-hint" placeholder="We declare that this invoice shows the actual price of the goods/services described and that all particulars are true and correct.">{{ old('declaration', $company->declaration) }}</textarea>
                            <p id="declaration-hint" class="mt-1 text-xs text-gray-500">Standard declaration printed on tax invoices.</p>
                        </div>
                    </div>
                </section>

                {{-- Bank / payment --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Bank &amp; payment details</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Printed on invoice PDFs.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <x-input-label for="bank_account_name" value="Payee name (as in bank)" />
                            <x-text-input id="bank_account_name" name="bank_account_name" type="text" class="mt-1 block w-full" :value="old('bank_account_name', $company->bank_account_name)" maxlength="120" :placeholder="$company->name" aria-describedby="bank_account_name-hint" />
                            <p id="bank_account_name-hint" class="mt-1 text-xs text-gray-500">Name on the bank account, used for cheques and NEFT. Leave blank to omit it from invoices.</p>
                            <x-input-error :messages="$errors->get('bank_account_name')" for="bank_account_name" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="bank_name" value="Bank name" />
                            <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" :value="old('bank_name', $company->bank_name)" placeholder="HDFC Bank" />
                        </div>
                        <div>
                            <x-input-label for="bank_branch" value="Branch" />
                            <x-text-input id="bank_branch" name="bank_branch" type="text" class="mt-1 block w-full" :value="old('bank_branch', $company->bank_branch)" placeholder="Powai" />
                        </div>
                        <div>
                            <x-input-label for="bank_account_type" value="Account type" />
                            <select id="bank_account_type" name="bank_account_type"
                                    class="mt-1 block w-full border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm">
                                @php $accountType = old('bank_account_type', $company->bank_account_type); @endphp
                                <option value="">Not specified</option>
                                <option value="current" @selected($accountType === 'current')>Current</option>
                                <option value="savings" @selected($accountType === 'savings')>Savings</option>
                            </select>
                            <x-input-error :messages="$errors->get('bank_account_type')" for="bank_account_type" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="bank_account_number" value="Account number" />
                            <x-text-input id="bank_account_number" name="bank_account_number" type="text" class="mt-1 block w-full font-mono" :value="old('bank_account_number', $company->bank_account_number)" autocomplete="off" spellcheck="false" />
                        </div>
                        <div>
                            <x-input-label for="bank_ifsc" value="IFSC code" />
                            <x-text-input id="bank_ifsc" name="bank_ifsc" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('bank_ifsc', $company->bank_ifsc)" maxlength="11" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="HDFC0001234" />
                        </div>
                        <div>
                            <x-input-label for="upi_id" value="UPI ID (optional)" />
                            <x-text-input id="upi_id" name="upi_id" type="text" class="mt-1 block w-full font-mono" :value="old('upi_id', $company->upi_id)" autocapitalize="none" autocomplete="off" spellcheck="false" placeholder="yourname@okhdfcbank" aria-describedby="upi_id-hint" />
                            <p id="upi_id-hint" class="mt-1 text-xs text-gray-500">Invoice PDFs show a UPI QR code for payment.</p>
                        </div>
                    </div>
                </section>

                {{-- Exports and SEZ --}}
                <section class="p-6 sm:p-8">
                    <h2 class="text-sm font-bold text-gray-900">Exports &amp; SEZ supplies</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Needed only for exports or SEZ supplies without IGST.</p>
                    <div class="mt-5">
                        <x-input-label for="lut_number" value="LUT / bond reference number (optional)" />
                        <x-text-input id="lut_number" name="lut_number" type="text" maxlength="40" class="mt-1 block w-full sm:w-80"
                                      :value="old('lut_number', $company->lut_number)" placeholder="e.g. AD270326012345X" autocapitalize="characters" spellcheck="false" aria-describedby="lut_number-hint" />
                        <p id="lut_number-hint" class="text-xs text-gray-500 mt-1">
                            ARN of your Letter of Undertaking (Form RFD-11) for this financial year. Printed on export and SEZ
                            invoices billed <strong>under LUT</strong>. Renew it every April.
                        </p>
                        <x-input-error :messages="$errors->get('lut_number')" for="lut_number" class="mt-2" />
                    </div>
                </section>

                {{-- Financial-year lock --}}
                <section class="p-6 sm:p-8">
                    <h2 class="text-sm font-bold text-gray-900">Financial-year lock</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Prevent changes to closed financial years.</p>
                    <div class="mt-5">
                        <x-input-label for="books_locked_until" value="Lock books up to (optional)" />
                        <x-text-input id="books_locked_until" name="books_locked_until" type="date" class="mt-1 block w-full sm:w-64"
                                      :value="old('books_locked_until', $company->books_locked_until?->toDateString())" aria-describedby="books_locked_until-hint" />
                        <p id="books_locked_until-hint" class="text-xs text-gray-500 mt-1">
                            Usually <strong>31 March</strong> of the year your CA has closed. Invoices, expenses and cash memos dated on or before this date cannot be added, edited or deleted. <span class="text-accent-700">Leave blank to keep all entries editable.</span>
                        </p>
                        <x-input-error :messages="$errors->get('books_locked_until')" for="books_locked_until" class="mt-2" />
                    </div>
                </section>

                {{-- Action bar --}}
                <div class="px-6 sm:px-8 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-800">Back to dashboard</a>
                    <x-primary-button>{{ __('Save company') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
