<x-app-layout :title="$customer->exists ? 'Edit customer' : 'New customer'">
    <x-slot name="header">
        <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
            {{ $customer->exists ? 'Edit customer' : 'New customer' }}
        </h1>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-breadcrumbs :items="[
                ['label' => 'Customers', 'href' => route('customers.index')],
                ['label' => $customer->exists ? $customer->name : 'New customer'],
            ]" />

            <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}"
                  class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                @csrf
                @if ($customer->exists) @method('PATCH') @endif

                @if ($errors->any())
                    <div class="m-6 mb-0 p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm" role="alert">
                        <div class="font-semibold mb-1">Please fix the following before saving:</div>
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Section: Customer details --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Customer details</h3>
                    <p class="text-xs text-gray-500 mt-0.5">How this customer appears on your invoices.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <x-input-label for="name" value="Customer name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $customer->name)" required autofocus placeholder="e.g. Acme Consulting LLP" />
                            <p class="mt-1 text-xs text-gray-500">Use the legal name as it should appear on invoices.</p>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="gstin" value="GSTIN (optional, for B2B)" />
                            <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('gstin', $customer->gstin)" maxlength="15" placeholder="27AAACT2727Q1ZW" />
                            <p class="mt-1 text-xs text-gray-500">15 characters. Leave blank for unregistered (B2C) customers.</p>
                            <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Section: Contact --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Contact</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Where we send invoices and payment reminders.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $customer->email)" placeholder="billing@customer.com" />
                            <p class="mt-1 text-xs text-gray-500">Used when you email an invoice or reminder.</p>
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" class="mt-1 block w-full" :value="old('phone', $customer->phone)" placeholder="+91 98765 43210" />
                            <p class="mt-1 text-xs text-gray-500">Used for WhatsApp invoice sharing.</p>
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Section: Billing address --}}
                <section class="p-6 sm:p-8">
                    <h3 class="text-sm font-bold text-gray-900">Billing address</h3>
                    <p class="text-xs text-gray-500 mt-0.5">The state determines the GST place of supply.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <x-input-label for="address_line1" value="Address line 1" />
                            <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $customer->address_line1)" placeholder="Building, street" />
                            <x-input-error :messages="$errors->get('address_line1')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="address_line2" value="Address line 2" />
                            <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $customer->address_line2)" placeholder="Area, landmark (optional)" />
                            <x-input-error :messages="$errors->get('address_line2')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="city" value="City" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $customer->city)" placeholder="e.g. Mumbai" />
                            <x-input-error :messages="$errors->get('city')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="state_id" value="State *" />
                            <select id="state_id" name="state_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">— Select state —</option>
                                @foreach ($states as $s)
                                    <option value="{{ $s->id }}" @selected(old('state_id', $customer->state_id) == $s->id)>{{ $s->name }} ({{ $s->gst_code }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">CGST + SGST (same state) or IGST (other state).</p>
                            <x-input-error :messages="$errors->get('state_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="postal_code" value="Postal code" />
                            <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $customer->postal_code)" placeholder="6 digits" />
                            <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="country" value="Country *" />
                            <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $customer->country ?? 'India')" required />
                            <x-input-error :messages="$errors->get('country')" class="mt-2" />
                        </div>
                    </div>
                </section>

                {{-- Action bar --}}
                <div class="px-6 sm:px-8 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <a href="{{ route('customers.index') }}" class="text-sm text-gray-500 hover:text-gray-800">Cancel</a>
                    <x-primary-button>{{ $customer->exists ? 'Save changes' : 'Save customer' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
