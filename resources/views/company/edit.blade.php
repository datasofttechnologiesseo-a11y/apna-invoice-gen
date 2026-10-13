<x-app-layout title="Company settings">
    <x-slot name="header">
        @if ($company->exists)
            <x-breadcrumbs :items="[
                ['label' => 'All settings', 'href' => route('settings.index')],
                ['label' => 'Company settings'],
            ]" />
        @else
            <x-breadcrumbs :items="[
                ['label' => 'Companies', 'href' => route('companies.index')],
                ['label' => 'New company'],
            ]" />
        @endif
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
                    {{ $company->exists ? 'Company settings' : 'New company' }}
                </h1>
                @if ($company->exists)
                    <p class="text-sm text-gray-500 mt-1 truncate">{{ $company->name }}</p>
                @endif
            </div>
            <a href="{{ route('companies.index') }}" class="hidden sm:inline-flex items-center h-10 px-4 rounded-md text-sm font-semibold text-gray-800 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 whitespace-nowrap">All companies</a>
        </div>
    </x-slot>

    @php
        // Every field with a validation error gets its message under the
        // field; the banner at the top only says that something needs fixing.
        $err = fn (string $field) => $errors->has($field) ? $field . '-error' : null;
        $logoUrl = $company->logo_path ? \Illuminate\Support\Facades\Storage::url($company->logo_path) : null;
        $signatureUrl = $company->signature_path ? \Illuminate\Support\Facades\Storage::url($company->signature_path) : null;
        $section = 'p-5 sm:p-8 border-b border-gray-100';
        $select = 'mt-1 block w-full h-10 border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm';
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('error') && session('status'))
                <x-flash :message="session('status')" type="success" />
            @endif
            <x-flash />

            <form method="POST" action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}" enctype="multipart/form-data"
                  class="bg-white rounded-xl ring-1 ring-gray-200">
                @csrf
                @if ($company->exists) @method('PATCH') @endif
                @if (request('from'))
                    <input type="hidden" name="from" value="{{ request('from') }}">
                @endif
                <input type="hidden" name="default_currency" value="INR">

                @if ($errors->any())
                    <div class="m-5 sm:m-6 mb-0 p-3 rounded-lg bg-danger-50 border border-danger-200 text-danger-800 text-sm" role="alert">
                        Nothing was saved. Check the {{ \Illuminate\Support\Str::plural('field', count($errors->keys())) }} marked below.
                    </div>
                @endif

                {{-- Business identity --}}
                <section class="{{ $section }}">
                    <h2 class="text-base font-semibold text-gray-900">Business identity</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Printed on the invoice letterhead.</p>
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
                                    <span class="font-semibold text-gray-900">Registered under the composition scheme</span>
                                    <span id="composition_dealer-hint" class="block text-xs text-gray-500 mt-0.5">Section 10, CGST Act. Invoices print as "Bill of Supply" with the required composition declaration.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </section>

                {{-- Contact --}}
                <section class="{{ $section }}">
                    <h2 class="text-base font-semibold text-gray-900">Contact</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Printed on invoices.</p>
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
                <section class="{{ $section }}">
                    <h2 class="text-base font-semibold text-gray-900">Registered address</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Your state decides whether CGST and SGST or IGST applies.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="address_line1" value="Address line 1" />
                            <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $company->address_line1)" autocomplete="address-line1" />
                            <x-input-error :messages="$errors->get('address_line1')" for="address_line1" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="address_line2" value="Address line 2" />
                            <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $company->address_line2)" autocomplete="address-line2" />
                            <x-input-error :messages="$errors->get('address_line2')" for="address_line2" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="city" value="City" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $company->city)" autocomplete="address-level2" />
                            <x-input-error :messages="$errors->get('city')" for="city" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="state_id" value="State *" />
                            <select id="state_id" name="state_id" required class="{{ $select }}" @if ($err('state_id')) aria-invalid="true" aria-describedby="state_id-error" @endif>
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
                            <x-input-error :messages="$errors->get('postal_code')" for="postal_code" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="country" value="Country" />
                            <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $company->country)" autocomplete="country-name" />
                            <x-input-error :messages="$errors->get('country')" for="country" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Branding --}}
                <section class="{{ $section }}">
                    <h2 class="text-base font-semibold text-gray-900">Logo and signature</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Printed on invoice PDFs. Changes apply when you save.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ([
                            ['field' => 'logo', 'label' => 'Logo', 'url' => $logoUrl, 'max' => 2048, 'maxLabel' => '2 MB', 'box' => 'h-24'],
                            ['field' => 'signature', 'label' => 'Signature', 'url' => $signatureUrl, 'max' => 1024, 'maxLabel' => '1 MB', 'box' => 'h-20'],
                        ] as $img)
                            <div x-data="imagePicker(@js($img['url']), {{ $img['max'] }})">
                                <span class="block font-medium text-sm text-gray-700" id="{{ $img['field'] }}-label">{{ $img['label'] }}</span>
                                <div class="mt-1.5 flex items-start gap-4">
                                    <div class="w-36 {{ $img['box'] }} shrink-0 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden">
                                        <img x-show="shown()" x-cloak :src="preview" alt="{{ $img['label'] }} preview" class="max-h-full max-w-full object-contain p-1.5">
                                        <span x-show="! shown()" class="px-2 text-center text-xs text-gray-500" x-text="removing ? 'Will be removed' : 'None yet'">{{ $img['url'] ? '' : 'None yet' }}</span>
                                    </div>
                                    <div class="min-w-0 flex flex-col items-start gap-2">
                                        <input id="{{ $img['field'] }}" name="{{ $img['field'] }}" type="file" accept=".png,.jpg,.jpeg,.webp" class="peer sr-only"
                                               x-ref="file" @change="pick($event)"
                                               aria-labelledby="{{ $img['field'] }}-label" aria-describedby="{{ $img['field'] }}-hint{{ $err($img['field']) ? ' ' . $img['field'] . '-error' : '' }}">
                                        <label for="{{ $img['field'] }}" class="inline-flex items-center gap-1.5 h-10 px-4 rounded-md text-sm font-semibold text-gray-800 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 cursor-pointer peer-focus-visible:ring-2 peer-focus-visible:ring-brand-600">
                                            <svg class="w-4 h-4 text-gray-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                            <span x-text="(current || picked) ? 'Replace' : 'Choose file'">{{ $img['url'] ? 'Replace' : 'Choose file' }}</span>
                                        </label>
                                        <span x-show="picked" x-cloak class="text-xs text-gray-600 break-all" x-text="'New: ' + fileName + '. Save to apply.'"></span>
                                        <button type="button" x-show="picked" x-cloak @click="undoPick()" class="text-sm text-gray-600 hover:text-gray-900 underline">Keep the current one</button>

                                        <button type="button" x-show="current && ! picked && ! removing && ! confirming" @click="confirming = true"
                                                class="text-sm font-semibold text-danger-600 hover:text-danger-700 {{ $img['url'] ? '' : 'hidden' }}">Remove</button>
                                        <div x-show="confirming" x-cloak class="text-sm text-gray-700" role="alert">
                                            Remove the {{ strtolower($img['label']) }} from your invoices?
                                            <span class="mt-1.5 flex gap-2">
                                                <button type="button" @click="removing = true; confirming = false" class="inline-flex items-center h-9 px-3 rounded-md bg-danger-600 hover:bg-danger-700 text-white text-sm font-semibold">Remove</button>
                                                <button type="button" @click="confirming = false" class="inline-flex items-center h-9 px-3 rounded-md bg-white ring-1 ring-inset ring-gray-300 text-gray-800 text-sm font-semibold hover:bg-gray-50">Cancel</button>
                                            </span>
                                        </div>
                                        <span x-show="removing" x-cloak class="text-xs text-danger-700">
                                            Removed when you save. <button type="button" @click="removing = false" class="underline font-semibold">Undo</button>
                                        </span>
                                        <input type="hidden" name="remove_{{ $img['field'] }}" :value="removing ? 1 : 0" value="0">
                                    </div>
                                </div>
                                <p id="{{ $img['field'] }}-hint" class="mt-2 text-xs text-gray-500">PNG, JPG or WebP, up to {{ $img['maxLabel'] }}.</p>
                                <p x-show="problem" x-cloak class="mt-1 text-sm text-danger-600" x-text="problem"></p>
                                <x-input-error :messages="$errors->get($img['field'])" :for="$img['field']" class="mt-1" />
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Invoice numbering --}}
                <section class="{{ $section }}">
                    <h2 class="text-base font-semibold text-gray-900">Invoice numbering</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Number series for new invoices.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="invoice_prefix" value="Invoice prefix *" />
                            <x-text-input id="invoice_prefix" name="invoice_prefix" type="text" class="mt-1 block w-full" :value="old('invoice_prefix', $company->invoice_prefix)" required aria-describedby="invoice_prefix-hint" />
                            <p id="invoice_prefix-hint" class="text-xs text-gray-500 mt-1">Used when the number format below is blank.</p>
                            <x-input-error :messages="$errors->get('invoice_prefix')" for="invoice_prefix" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="invoice_number_padding" value="Number padding (digits) *" />
                            <x-text-input id="invoice_number_padding" name="invoice_number_padding" type="number" min="1" max="8" inputmode="numeric" class="mt-1 block w-full" :value="old('invoice_number_padding', $company->invoice_number_padding)" required aria-describedby="invoice_number_padding-hint" />
                            <p id="invoice_number_padding-hint" class="text-xs text-gray-500 mt-1">Digits in the running number. 4 gives 0001.</p>
                            <x-input-error :messages="$errors->get('invoice_number_padding')" for="invoice_number_padding" class="mt-2" />
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
                                <p class="mt-2 text-sm text-accent-800 bg-accent-50 border border-accent-200 rounded-md px-3 py-2">
                                    Your current series prints <span class="font-mono">{{ $numberPreview }}</span> ({{ strlen($numberPreview) }} characters).
                                    Rule 46(b) allows up to 16 characters, using only letters, digits, "-" and "/".
                                    Shorten the prefix or format. Invoices already issued are not affected.
                                </p>
                            @endif
                            <x-input-error :messages="$errors->get('invoice_number_format')" for="invoice_number_format" class="mt-2" />
                            @php $fyStart = now()->month >= 4 ? now()->year : now()->year - 1; @endphp
                            <p id="invoice_number_format-hint" class="text-xs text-gray-500 mt-1">
                                Tokens: <code class="bg-gray-100 text-gray-700 px-1 rounded">{FY}</code> → {{ $fyStart }}-{{ substr($fyStart + 1, 2) }} ·
                                <code class="bg-gray-100 text-gray-700 px-1 rounded">{FY_SHORT}</code> → {{ substr($fyStart, 2) }}-{{ substr($fyStart + 1, 2) }} ·
                                <code class="bg-gray-100 text-gray-700 px-1 rounded">{YYYY}</code> → {{ now()->year }} ·
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
                    <div class="mt-5 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                        <div class="font-semibold text-gray-900 text-sm">Continuing a series from other software?</div>
                        <p class="mt-0.5 text-sm text-gray-600">
                            If you issued invoices elsewhere this financial year, enter the next number to keep the series continuous.
                        </p>
                        <div class="mt-3">
                            <x-input-label for="next_invoice_number" value="Next running number (digits only)" />
                            <x-text-input id="next_invoice_number" name="next_invoice_number" type="number" min="1" inputmode="numeric" class="mt-1 block w-full sm:w-64 font-mono" :value="old('next_invoice_number')" placeholder="{{ $nextSequenceNumber }}" aria-describedby="next_invoice_number-hint" />
                            <p id="next_invoice_number-hint" class="mt-1 text-xs text-gray-500">
                                Currently <strong class="font-mono">{{ $nextSequenceNumber }}</strong>. Enter a higher number to skip ahead.
                                @if ($company->exists && $nextPreview)
                                    Preview: <strong class="font-mono">{{ $nextPreview }}</strong>
                                @endif
                            </p>
                            <x-input-error :messages="$errors->get('next_invoice_number')" for="next_invoice_number" class="mt-1" />
                        </div>
                    </div>
                </section>

                {{-- Invoice defaults --}}
                <section class="{{ $section }}">
                    <h2 class="text-base font-semibold text-gray-900">Invoice defaults</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Printed at the foot of every invoice.</p>
                    <div class="mt-5 space-y-5">
                        <div>
                            <x-input-label for="default_terms" value="Default terms and conditions" />
                            <textarea id="default_terms" name="default_terms" rows="4" class="mt-1 block w-full border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm" placeholder="1. Payment due within 30 days.&#10;2. Late payment attracts 2% monthly interest." @if ($err('default_terms')) aria-invalid="true" aria-describedby="default_terms-error" @endif>{{ old('default_terms', $company->default_terms) }}</textarea>
                            <x-input-error :messages="$errors->get('default_terms')" for="default_terms" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="declaration" value="Declaration" />
                            <textarea id="declaration" name="declaration" rows="2" class="mt-1 block w-full border-gray-300 focus:border-brand-600 focus:ring-brand-600 rounded-md shadow-sm" aria-describedby="declaration-hint{{ $err('declaration') ? ' declaration-error' : '' }}" placeholder="We declare that this invoice shows the actual price of the goods/services described and that all particulars are true and correct.">{{ old('declaration', $company->declaration) }}</textarea>
                            <p id="declaration-hint" class="mt-1 text-xs text-gray-500">Standard declaration printed on tax invoices.</p>
                            <x-input-error :messages="$errors->get('declaration')" for="declaration" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Bank / payment --}}
                <section class="{{ $section }}">
                    <h2 class="text-base font-semibold text-gray-900">Bank and payment details</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Printed on invoice PDFs. Changes are recorded in the activity log.</p>
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
                            <x-input-error :messages="$errors->get('bank_name')" for="bank_name" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="bank_branch" value="Branch" />
                            <x-text-input id="bank_branch" name="bank_branch" type="text" class="mt-1 block w-full" :value="old('bank_branch', $company->bank_branch)" placeholder="Powai" />
                            <x-input-error :messages="$errors->get('bank_branch')" for="bank_branch" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="bank_account_type" value="Account type" />
                            <select id="bank_account_type" name="bank_account_type" class="{{ $select }}" @if ($err('bank_account_type')) aria-invalid="true" aria-describedby="bank_account_type-error" @endif>
                                @php $accountType = old('bank_account_type', $company->bank_account_type); @endphp
                                <option value="">Not specified</option>
                                <option value="current" @selected($accountType === 'current')>Current</option>
                                <option value="savings" @selected($accountType === 'savings')>Savings</option>
                            </select>
                            <x-input-error :messages="$errors->get('bank_account_type')" for="bank_account_type" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="bank_account_number" value="Account number" />
                            <x-text-input id="bank_account_number" name="bank_account_number" type="text" inputmode="numeric" class="mt-1 block w-full font-mono" :value="old('bank_account_number', $company->bank_account_number)" autocomplete="off" spellcheck="false" />
                            <x-input-error :messages="$errors->get('bank_account_number')" for="bank_account_number" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="bank_ifsc" value="IFSC" />
                            <x-text-input id="bank_ifsc" name="bank_ifsc" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('bank_ifsc', $company->bank_ifsc)" maxlength="11" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="HDFC0001234" />
                            <x-input-error :messages="$errors->get('bank_ifsc')" for="bank_ifsc" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="upi_id" value="UPI ID (optional)" />
                            <x-text-input id="upi_id" name="upi_id" type="text" class="mt-1 block w-full font-mono" :value="old('upi_id', $company->upi_id)" autocapitalize="none" autocomplete="off" spellcheck="false" placeholder="yourname@okhdfcbank" aria-describedby="upi_id-hint" />
                            <p id="upi_id-hint" class="mt-1 text-xs text-gray-500">Invoice PDFs show a UPI QR code for payment.</p>
                            <x-input-error :messages="$errors->get('upi_id')" for="upi_id" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Exports and SEZ --}}
                <section class="p-5 sm:p-8">
                    <h2 class="text-base font-semibold text-gray-900">Exports and SEZ supplies</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Needed only for exports or SEZ supplies without IGST.</p>
                    <div class="mt-5">
                        <x-input-label for="lut_number" value="LUT / bond reference number (optional)" />
                        <x-text-input id="lut_number" name="lut_number" type="text" maxlength="40" class="mt-1 block w-full sm:w-80"
                                      :value="old('lut_number', $company->lut_number)" placeholder="e.g. AD270326012345X" autocapitalize="characters" spellcheck="false" aria-describedby="lut_number-hint" />
                        <p id="lut_number-hint" class="text-xs text-gray-500 mt-1">
                            ARN of your Letter of Undertaking (Form RFD-11) for this financial year. Printed on export and SEZ
                            invoices billed under LUT. Renew it every April.
                        </p>
                        <x-input-error :messages="$errors->get('lut_number')" for="lut_number" class="mt-2" />
                    </div>
                </section>

                {{-- Save bar: sticks to the bottom of the screen on phones, where
                     the form is long and the button would otherwise be far away. --}}
                <div class="sticky bottom-0 z-10 sm:static px-4 sm:px-8 py-3 sm:py-4 bg-white/95 sm:bg-gray-50 backdrop-blur border-t border-gray-200 rounded-b-xl flex items-center justify-end gap-2 shadow-[0_-4px_12px_-6px_rgba(0,0,0,0.12)] sm:shadow-none">
                    <a href="{{ $company->exists ? route('settings.index') : route('companies.index') }}" class="inline-flex items-center h-10 px-4 rounded-md text-sm font-semibold text-gray-800 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</a>
                    <x-primary-button>{{ $company->exists ? 'Save changes' : 'Create company' }}</x-primary-button>
                </div>
            </form>

            @if ($company->exists)
                {{-- Books lock: its own form and its own save. --}}
                <form method="POST" action="{{ route('companies.update', $company) }}" id="books-lock"
                      class="bg-white rounded-xl ring-1 ring-gray-200 p-5 sm:p-8 scroll-mt-24">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="section" value="books_lock">
                    @if (request('from'))
                        <input type="hidden" name="from" value="{{ request('from') }}">
                    @endif
                    <h2 class="text-base font-semibold text-gray-900">Lock books</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Stops changes to a period you have filed or closed.</p>
                    <div class="mt-5 flex flex-col sm:flex-row sm:items-end gap-3">
                        <div>
                            <x-input-label for="books_locked_until" value="Lock books up to" />
                            <x-text-input id="books_locked_until" name="books_locked_until" type="date" class="mt-1 block w-full sm:w-56"
                                          :value="old('books_locked_until', $company->books_locked_until?->toDateString())" aria-describedby="books_locked_until-hint" />
                        </div>
                        <x-secondary-button type="submit">Save lock date</x-secondary-button>
                    </div>
                    <x-input-error :messages="$errors->get('books_locked_until')" for="books_locked_until" class="mt-2" />
                    <div id="books_locked_until-hint" class="mt-3 text-sm text-gray-600 space-y-1.5">
                        <p>
                            Entries dated on or before this date cannot be added, edited or deleted: invoices, credit notes,
                            payments received, purchase invoices, payments made, expenses and cash memos.
                        </p>
                        <p class="text-gray-500">
                            Lock each month after you file its GSTR-3B, so the figures you filed stay as filed. At year end,
                            lock up to 31 March once your CA has closed the books. Clear the date to unlock.
                        </p>
                        @if ($company->books_locked_until)
                            <p class="text-gray-700">Locked up to <strong>{{ $company->books_locked_until->format('d M Y') }}</strong>.</p>
                        @endif
                    </div>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        // Logo and signature: show the current image, preview a newly picked
        // file before it is saved, and refuse anything that is not PNG, JPG or
        // WebP (a phone may offer HEIC) or is over the size limit.
        function imagePicker(current, maxKb) {
            return {
                current: current,
                preview: current,
                picked: false,
                fileName: '',
                removing: false,
                confirming: false,
                problem: '',
                shown: function () {
                    return !!this.preview && !this.removing;
                },
                pick: function (event) {
                    var file = event.target.files && event.target.files[0];
                    this.problem = '';
                    if (!file) {
                        this.undoPick();
                        return;
                    }
                    var okType = ['image/png', 'image/jpeg', 'image/webp'].indexOf(file.type) !== -1
                        || /\.(png|jpe?g|webp)$/i.test(file.name);
                    if (!okType) {
                        this.problem = 'Choose a PNG, JPG or WebP image.';
                        event.target.value = '';
                        return;
                    }
                    if (file.size > maxKb * 1024) {
                        this.problem = 'This file is larger than ' + Math.round(maxKb / 1024) + ' MB. Choose a smaller image.';
                        event.target.value = '';
                        return;
                    }
                    this.picked = true;
                    this.removing = false;
                    this.confirming = false;
                    this.fileName = file.name;
                    this.preview = URL.createObjectURL(file);
                },
                undoPick: function () {
                    if (this.$refs.file) {
                        this.$refs.file.value = '';
                    }
                    this.picked = false;
                    this.fileName = '';
                    this.preview = this.current;
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
