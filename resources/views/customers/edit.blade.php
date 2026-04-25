<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $customer->exists ? 'Edit customer' : 'New customer' }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-breadcrumbs :items="[
                ['label' => 'Customers', 'href' => route('customers.index')],
                ['label' => $customer->exists ? $customer->name : 'New customer'],
            ]" />
            <div class="p-6 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="space-y-6">
                    @csrf
                    @if ($customer->exists) @method('PATCH') @endif

                    {{-- Top-level errors block. Matches onboarding/customer.blade.php so
                         the user always sees something actionable — even if a per-field
                         error slot is ever missed for a newly added rule. --}}
                    @if ($errors->any())
                        <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
                            <div class="font-semibold mb-1">Please fix the following before saving:</div>
                            <ul class="list-disc pl-5 space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="name" value="Customer name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $customer->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="gstin" value="GSTIN (optional, for B2B)" />
                            <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full uppercase font-mono" :value="old('gstin', $customer->gstin)" maxlength="15" placeholder="27AAACT2727Q1ZW" />
                            <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $customer->email)" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" class="mt-1 block w-full" :value="old('phone', $customer->phone)" placeholder="+91 98765 43210" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="country" value="Country *" />
                            <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $customer->country ?? 'India')" required />
                            <x-input-error :messages="$errors->get('country')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="address_line1" value="Address line 1" />
                            <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $customer->address_line1)" />
                            <x-input-error :messages="$errors->get('address_line1')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="address_line2" value="Address line 2" />
                            <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $customer->address_line2)" />
                            <x-input-error :messages="$errors->get('address_line2')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="city" value="City" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $customer->city)" />
                            <x-input-error :messages="$errors->get('city')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="state_id" value="State * (determines CGST/SGST vs IGST)" />
                            <select id="state_id" name="state_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">— Select —</option>
                                @foreach ($states as $s)
                                    <option value="{{ $s->id }}" @selected(old('state_id', $customer->state_id) == $s->id)>{{ $s->name }} ({{ $s->gst_code }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('state_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="postal_code" value="Postal code" />
                            <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $customer->postal_code)" />
                            <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <a href="{{ route('customers.index') }}" class="text-gray-500 hover:underline">← Cancel</a>
                        <x-primary-button>{{ $customer->exists ? 'Save' : 'Create customer' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-app-layout>
