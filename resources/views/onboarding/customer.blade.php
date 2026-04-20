<x-onboarding-layout step="customer">
    <div class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 bg-gradient-to-br from-saffron-600 to-accent-700 text-white">
            <div class="text-xs font-bold uppercase tracking-widest text-accent-100">Step 2 of 3</div>
            <h1 class="mt-2 font-display text-2xl md:text-3xl font-extrabold">Add your first customer</h1>
            <p class="mt-2 text-accent-100">Save customer details once — we'll auto-fill them on every future invoice. You can add more any time.</p>
        </div>

        <form method="POST" action="{{ route('onboarding.customer.save') }}" class="p-6 md:p-8 space-y-6">
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

            <div>
                <h3 class="font-display font-bold text-gray-900 text-lg">Customer basics</h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <x-input-label for="name" value="Customer name *" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus placeholder="Blue Ocean Pvt Ltd" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="gstin" value="GSTIN (optional, for B2B)" />
                        <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('gstin')" maxlength="15" placeholder="27ABCDE1234F1Z5" />
                        <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" placeholder="accounts@customer.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="country" value="Country" />
                        <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', 'India')" />
                        <x-input-error :messages="$errors->get('country')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="font-display font-bold text-gray-900 text-lg">Billing address</h3>
                <p class="text-sm text-gray-500 mt-0.5">The customer's state determines whether GST is split (CGST+SGST) or consolidated (IGST)</p>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <x-input-label for="address_line1" value="Address line 1 *" />
                        <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1')" required />
                        <x-input-error :messages="$errors->get('address_line1')" class="mt-2" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="address_line2" value="Address line 2" />
                        <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2')" />
                    </div>
                    <div>
                        <x-input-label for="city" value="City *" />
                        <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city')" required />
                        <x-input-error :messages="$errors->get('city')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="state_id" value="State *" />
                        <select id="state_id" name="state_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                            <option value="">— Select —</option>
                            @foreach ($states as $s)
                                <option value="{{ $s->id }}" @selected(old('state_id') == $s->id)>{{ $s->name }} ({{ $s->gst_code }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('state_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="postal_code" value="PIN code" />
                        <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code')" />
                        <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="border-t pt-6 flex items-center justify-between gap-4">
                <a href="{{ route('onboarding.customer.skip') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">Skip for now — I'll add customers later</a>
                <button class="inline-flex items-center px-6 py-3 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-brand transition">
                    Save customer
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M5 12h13"/></svg>
                </button>
            </div>
        </form>
    </div>
</x-onboarding-layout>
