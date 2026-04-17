<x-onboarding-layout step="business">
    <div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 bg-gradient-to-br from-brand-900 to-brand-700 text-white">
            <div class="text-xs font-bold uppercase tracking-widest text-accent-300">Step 1 of 3</div>
            <h1 class="mt-2 font-display text-2xl md:text-3xl font-extrabold">Tell us about your business</h1>
            <p class="mt-2 text-brand-100">This information appears on every invoice as your letterhead. It's also used to detect GST place-of-supply automatically.</p>
        </div>

        <form method="POST" action="{{ route('onboarding.business.save') }}" enctype="multipart/form-data" class="p-6 md:p-8 space-y-6">
            @csrf

            <div>
                <h3 class="font-display font-bold text-gray-900 text-lg">Basics</h3>
                <p class="text-sm text-gray-500 mt-0.5">Legal name and tax IDs</p>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <x-input-label for="name" value="Business name *" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $company->name)" required autofocus placeholder="Acme Consulting LLP" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="gstin" value="GSTIN" />
                        <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('gstin', $company->gstin)" maxlength="15" placeholder="27AABCU9603R1ZM" />
                        <p class="mt-1 text-xs text-gray-500">15-char GST number. Leave blank if not registered.</p>
                        <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="pan" value="PAN" />
                        <x-text-input id="pan" name="pan" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('pan', $company->pan)" maxlength="10" placeholder="AABCU9603R" />
                    </div>
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="font-display font-bold text-gray-900 text-lg">Registered address</h3>
                <p class="text-sm text-gray-500 mt-0.5">State selection drives CGST/SGST vs IGST on invoices</p>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <x-input-label for="address_line1" value="Address line 1 *" />
                        <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $company->address_line1)" required placeholder="Plot 42, Tech Park" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="address_line2" value="Address line 2" />
                        <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $company->address_line2)" placeholder="Sector 5, Powai" />
                    </div>
                    <div>
                        <x-input-label for="city" value="City *" />
                        <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $company->city)" required placeholder="Mumbai" />
                    </div>
                    <div>
                        <x-input-label for="state_id" value="State *" />
                        <select id="state_id" name="state_id" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" required>
                            <option value="">— Select —</option>
                            @foreach ($states as $s)
                                <option value="{{ $s->id }}" @selected(old('state_id', $company->state_id) == $s->id)>{{ $s->name }} ({{ $s->gst_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="postal_code" value="PIN code *" />
                        <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $company->postal_code)" required maxlength="10" placeholder="400076" />
                    </div>
                    <div>
                        <x-input-label for="country" value="Country" />
                        <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $company->country ?? 'India')" />
                    </div>
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="font-display font-bold text-gray-900 text-lg">Contact</h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $company->email ?? auth()->user()->email)" placeholder="billing@company.com" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $company->phone)" placeholder="+91 98200 00000" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="website" value="Website" />
                        <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $company->website)" placeholder="https://company.com" />
                    </div>
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="font-display font-bold text-gray-900 text-lg">Letterhead logo</h3>
                <p class="text-sm text-gray-500 mt-0.5">Optional — shown at the top of every invoice PDF</p>
                @if ($company->logo_path)
                    <img src="{{ Storage::url($company->logo_path) }}" alt="Current logo" class="mt-4 h-20 border rounded-lg bg-white p-2">
                @endif
                <input id="logo" name="logo" type="file" accept="image/*" class="mt-3 block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                <p class="mt-1 text-xs text-gray-500">PNG or JPG, up to 2 MB. Recommended 400×120 px.</p>
            </div>

            <div class="border-t pt-6">
                <h3 class="font-display font-bold text-gray-900 text-lg">Invoice numbering</h3>
                <p class="text-sm text-gray-500 mt-0.5">How your invoice numbers will be formatted</p>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <x-input-label for="invoice_prefix" value="Prefix *" />
                        <x-text-input id="invoice_prefix" name="invoice_prefix" type="text" class="mt-1 block w-full font-mono" :value="old('invoice_prefix', $company->invoice_prefix ?? 'INV')" required maxlength="10" />
                        <p class="mt-1 text-xs text-gray-500">First invoice will be {{ old('invoice_prefix', $company->invoice_prefix ?? 'INV') }}-0001</p>
                    </div>
                    <div>
                        <x-input-label for="default_currency" value="Default currency *" />
                        <select id="default_currency" name="default_currency" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (['INR','USD','EUR','GBP','AED','SGD','AUD','CAD'] as $code)
                                <option value="{{ $code }}" @selected(old('default_currency', $company->default_currency ?? 'INR') === $code)>{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="border-t pt-6 flex items-center justify-end gap-4">
                <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">I'll do this later</a>
                <button class="inline-flex items-center px-6 py-3 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-brand transition">
                    Save and continue
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M5 12h13"/></svg>
                </button>
            </div>
        </form>
    </div>
</x-onboarding-layout>
