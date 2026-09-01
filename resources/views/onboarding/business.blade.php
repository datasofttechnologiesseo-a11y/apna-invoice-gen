@php
    [$fyStart, $fyEnd] = \App\Models\Company::financialYearFor(now());
    $fyLabel = sprintf('%04d-%02d', $fyStart, $fyEnd % 100);
    $previewPrefix = old('invoice_prefix', $company->invoice_prefix ?? 'INV');

    // Fields that live inside the collapsed "more details" panel. If any of
    // them failed validation the panel must start open, otherwise the user
    // sees an error for a field they cannot find.
    $advancedFields = [
        'pan', 'address_line1', 'address_line2', 'city', 'postal_code', 'country',
        'email', 'phone', 'website', 'logo', 'invoice_prefix',
        'bank_name', 'bank_branch', 'bank_account_number', 'bank_ifsc', 'upi_id',
    ];
    $advancedHasError = collect($advancedFields)->contains(fn ($f) => $errors->has($f));
    // A GSTIN makes the address statutory (Rule 46), so open the panel for
    // GST-registered users too.
    $advancedOpen = $advancedHasError || filled(old('gstin', $company->gstin));

    // GST state code -> state id, so entering a GSTIN can pick the state for
    // the user (first two digits of every GSTIN are the state code).
    $stateByGstCode = $states->mapWithKeys(fn ($s) => [sprintf('%02d', $s->gst_code) => $s->id]);
@endphp

<x-onboarding-layout step="business">
    <div
        x-data="{
            advanced: {{ $advancedOpen ? 'true' : 'false' }},
            gstin: @js(old('gstin', $company->gstin) ?? ''),
            stateByCode: @js($stateByGstCode),
            syncStateFromGstin() {
                const code = (this.gstin || '').trim().slice(0, 2);
                const id = this.stateByCode[code];
                const select = document.getElementById('state_id');
                // Only fill a blank state, never overwrite a deliberate choice.
                if (id && select && !select.value) select.value = id;
                if (this.gstin.trim().length > 0) this.advanced = true;
            },
        }"
        class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden"
    >
        <div class="p-6 md:p-8 bg-gradient-to-br from-brand-900 to-brand-700 text-white">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest text-accent-300">Step 1 of 3</div>
                    <h1 class="mt-2 font-display text-2xl md:text-3xl font-extrabold">Tell us about your business</h1>
                    <p class="mt-2 text-brand-100">Just two things to start billing. Everything else can wait.</p>
                </div>
                <a href="{{ route('dashboard') }}"
                   class="hidden sm:inline-flex flex-shrink-0 items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-brand-100 ring-1 ring-white/25 hover:bg-white/10 transition">
                    Skip for now
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('onboarding.business.save') }}" enctype="multipart/form-data" class="p-6 md:p-8 space-y-6">
            @csrf

            @if ($errors->any())
                <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                    <div class="font-semibold mb-1">Please fix the following before saving:</div>
                    <ul class="list-disc pl-5 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Essentials: the only two fields needed to produce a correct invoice. --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <x-input-label for="name" value="Business name *" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $company->name)" required autofocus placeholder="Acme Consulting LLP" />
                    <p class="mt-1 text-xs text-gray-500">Appears as the letterhead on every invoice.</p>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="state_id" value="State *" />
                    <select id="state_id" name="state_id" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" required>
                        <option value="">Select your state</option>
                        @foreach ($states as $s)
                            <option value="{{ $s->id }}" @selected(old('state_id', $company->state_id) == $s->id)>{{ $s->name }} ({{ $s->gst_code }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Decides CGST + SGST vs IGST automatically.</p>
                    <x-input-error :messages="$errors->get('state_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="gstin" value="GSTIN" />
                    <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full uppercase font-mono"
                                  x-model="gstin" x-on:input="syncStateFromGstin()"
                                  :value="old('gstin', $company->gstin)" maxlength="15" placeholder="27AAACT2727Q1ZW" />
                    <p class="mt-1 text-xs text-gray-500">Optional. Leave blank if you are not GST-registered.</p>
                    <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                </div>
            </div>

            {{-- Everything below is optional and can be completed later from
                 Company settings. Collapsed by default so the first run is two
                 fields, not nineteen. --}}
            <div class="border-t pt-5">
                <button type="button" x-on:click="advanced = ! advanced"
                        class="w-full flex items-center justify-between gap-3 text-left group">
                    <span>
                        <span class="font-display font-bold text-gray-900">Add more details</span>
                        <span class="block text-sm text-gray-500 mt-0.5">Address, logo, bank &amp; UPI, invoice numbering, all optional</span>
                    </span>
                    <svg class="w-5 h-5 flex-shrink-0 text-gray-500 group-hover:text-gray-600 transition-transform"
                         x-bind:class="advanced && 'rotate-180'"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="advanced" x-cloak class="mt-6 space-y-6">
                    <template x-if="gstin.trim().length > 0">
                        <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
                            You have entered a GSTIN, so a registered address is required on your tax invoices (GST Rule 46).
                        </div>
                    </template>

                    <div>
                        <h3 class="font-display font-bold text-gray-900">Registered address</h3>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                <x-input-label for="address_line1">Address line 1 <span class="font-normal text-gray-500">(required if GST-registered)</span></x-input-label>
                                <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $company->address_line1)" placeholder="Plot 42, Tech Park" />
                                <x-input-error :messages="$errors->get('address_line1')" class="mt-2" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="address_line2" value="Address line 2" />
                                <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $company->address_line2)" placeholder="Sector 5, Powai" />
                            </div>
                            <div>
                                <x-input-label for="city">City <span class="font-normal text-gray-500">(required if GST-registered)</span></x-input-label>
                                <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $company->city)" placeholder="Mumbai" />
                                <x-input-error :messages="$errors->get('city')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="postal_code" value="PIN code" />
                                <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $company->postal_code)" maxlength="10" placeholder="400076" />
                            </div>
                            <div>
                                <x-input-label for="pan" value="PAN" />
                                <x-text-input id="pan" name="pan" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('pan', $company->pan)" maxlength="10" placeholder="AABCU9603R" />
                                <x-input-error :messages="$errors->get('pan')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="country" value="Country" />
                                <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $company->country ?? 'India')" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <h3 class="font-display font-bold text-gray-900">Contact</h3>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <x-input-label for="email" value="Email" />
                                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $company->email ?? auth()->user()->email)" placeholder="billing@company.com" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="phone" value="Phone" />
                                <x-text-input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" class="mt-1 block w-full" :value="old('phone', $company->phone)" placeholder="+91 98200 00000" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="website" value="Website" />
                                <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $company->website)" placeholder="https://company.com" />
                                <x-input-error :messages="$errors->get('website')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <h3 class="font-display font-bold text-gray-900">Letterhead logo</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Shown at the top of every invoice PDF</p>
                        @if ($company->logo_path)
                            <img src="{{ Storage::url($company->logo_path) }}" alt="Current logo" class="mt-4 h-20 border rounded-lg bg-white p-2">
                        @endif
                        <input id="logo" name="logo" type="file" accept="image/*" class="mt-3 block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                        <p class="mt-1 text-xs text-gray-500">PNG or JPG, up to 2 MB. Recommended 400×120 px.</p>
                        <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                    </div>

                    <div class="border-t pt-6">
                        <h3 class="font-display font-bold text-gray-900">Invoice numbering</h3>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <x-input-label for="invoice_prefix" value="Prefix" />
                                <x-text-input id="invoice_prefix" name="invoice_prefix" type="text" class="mt-1 block w-full font-mono" :value="$previewPrefix" maxlength="10" />
                                <p class="mt-1 text-xs text-gray-500">
                                    Your first invoice will be <strong class="font-mono text-brand-700">{{ $previewPrefix }}/{{ $fyLabel }}/0001</strong>
                                    <span class="text-gray-500">, sequence auto-resets on 1&nbsp;April (GST best practice).</span>
                                </p>
                                <x-input-error :messages="$errors->get('invoice_prefix')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <h3 class="font-display font-bold text-gray-900">Payment details</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Shown in the "Payment details" block on every invoice. Add a UPI ID to auto-generate a Scan-to-Pay QR on bills.
                        </p>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <x-input-label for="bank_name" value="Bank name" />
                                <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" :value="old('bank_name', $company->bank_name)" maxlength="120" placeholder="HDFC Bank" />
                                <x-input-error :messages="$errors->get('bank_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="bank_branch" value="Branch" />
                                <x-text-input id="bank_branch" name="bank_branch" type="text" class="mt-1 block w-full" :value="old('bank_branch', $company->bank_branch)" maxlength="120" placeholder="Saket, New Delhi" />
                                <x-input-error :messages="$errors->get('bank_branch')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="bank_account_number" value="Account number" />
                                <x-text-input id="bank_account_number" name="bank_account_number" type="text" class="mt-1 block w-full font-mono" :value="old('bank_account_number', $company->bank_account_number)" maxlength="30" />
                                <x-input-error :messages="$errors->get('bank_account_number')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="bank_ifsc" value="IFSC code" />
                                <x-text-input id="bank_ifsc" name="bank_ifsc" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('bank_ifsc', $company->bank_ifsc)" maxlength="15" placeholder="HDFC0001234" />
                                <x-input-error :messages="$errors->get('bank_ifsc')" class="mt-2" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="upi_id" value="UPI ID (VPA)" />
                                <x-text-input id="upi_id" name="upi_id" type="text" class="mt-1 block w-full font-mono" :value="old('upi_id', $company->upi_id)" maxlength="60" placeholder="yourname@okhdfcbank" />
                                <p class="mt-1 text-xs text-gray-500">If set, a QR code is auto-generated on each invoice so clients can scan and pay via any UPI app.</p>
                                <x-input-error :messages="$errors->get('upi_id')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <input type="hidden" name="default_currency" value="INR">

            <div class="border-t pt-6 flex flex-col-reverse sm:flex-row items-center justify-between gap-4">
                <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">I'll do this later</a>
                <button class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-brand transition">
                    Save and continue
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M5 12h13"/></svg>
                </button>
            </div>
        </form>
    </div>
</x-onboarding-layout>
