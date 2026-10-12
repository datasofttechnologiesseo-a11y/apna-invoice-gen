<x-app-layout :title="$isEdit ? 'Edit vendor' : 'New vendor'">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Vendors', 'href' => route('vendors.index')],
            ['label' => $isEdit ? $vendor->name : 'New vendor'],
        ]" />
        <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ $isEdit ? 'Edit ' . $vendor->name : 'New vendor' }}</h1>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white rounded-xl ring-1 ring-gray-200 shadow-sm">
                <form method="POST" action="{{ $isEdit ? route('vendors.update', $vendor) : route('vendors.store') }}" class="space-y-6">
                    @csrf
                    @if ($isEdit) @method('PUT') @endif

                    @if ($errors->any())
                        <div class="p-3 rounded-lg bg-danger-50 border border-danger-200 text-danger-800 text-sm" role="alert">
                            Check the fields marked in red.
                        </div>
                    @endif

                    <section class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <x-input-label for="name" value="Vendor name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $vendor->name)" required maxlength="160" autocomplete="off" placeholder="e.g. Sharma Traders" />
                            <x-input-error :messages="$errors->get('name')" for="name" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="gstin" value="GSTIN (if registered)" />
                            <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full font-mono uppercase" :value="old('gstin', $vendor->gstin)" maxlength="15" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="27AAAAA0000A1Z5" aria-describedby="gstin-hint" />
                            <p id="gstin-hint" class="mt-1 text-xs text-gray-500">Needed to claim ITC. Must match the vendor's state.</p>
                            <x-input-error :messages="$errors->get('gstin')" for="gstin" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="state_id" value="State" />
                            <select id="state_id" name="state_id" aria-describedby="state_id-hint @error('state_id') state_id-error @enderror" @error('state_id') aria-invalid="true" @enderror class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">
                                <option value="">Select state</option>
                                @foreach ($states as $st)
                                    <option value="{{ $st->id }}" @selected((int) old('state_id', $vendor->state_id) === $st->id)>{{ $st->name }} ({{ $st->gst_code }})</option>
                                @endforeach
                            </select>
                            <p id="state_id-hint" class="mt-1 text-xs text-gray-500">Sets CGST + SGST or IGST on their invoices.</p>
                            <x-input-error :messages="$errors->get('state_id')" for="state_id" class="mt-1" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="address_line1" value="Address line 1" />
                            <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $vendor->address_line1)" maxlength="255" />
                            <x-input-error :messages="$errors->get('address_line1')" for="address_line1" class="mt-1" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="address_line2" value="Address line 2" />
                            <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $vendor->address_line2)" maxlength="255" />
                            <x-input-error :messages="$errors->get('address_line2')" for="address_line2" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="city" value="City" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $vendor->city)" maxlength="100" />
                            <x-input-error :messages="$errors->get('city')" for="city" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="postal_code" value="PIN code" />
                            @php $vendorInIndia = strcasecmp(trim((string) old('country', $vendor->country ?? 'India')), 'India') === 0; @endphp
                            <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $vendor->postal_code)" :inputmode="$vendorInIndia ? 'numeric' : 'text'" :maxlength="$vendorInIndia ? 6 : 10" autocomplete="off" />
                            <x-input-error :messages="$errors->get('postal_code')" for="postal_code" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" name="phone" type="tel" inputmode="tel" class="mt-1 block w-full" :value="old('phone', $vendor->phone)" maxlength="30" autocomplete="off" placeholder="+91 98765 43210" />
                            <x-input-error :messages="$errors->get('phone')" for="phone" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $vendor->email)" autocomplete="off" />
                            <x-input-error :messages="$errors->get('email')" for="email" class="mt-1" />
                        </div>
                        <input type="hidden" name="country" value="{{ old('country', $vendor->country ?? 'India') }}">
                        <div class="md:col-span-2">
                            <x-input-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="2" maxlength="1000" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">{{ old('notes', $vendor->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" for="notes" class="mt-1" />
                        </div>
                    </section>

                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-gray-100">
                        <a href="{{ route('vendors.index') }}" class="inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md">Cancel</a>
                        <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3">
                            @if ($isEdit)
                                <a href="{{ route('purchases.index', ['vendor' => $vendor->id]) }}" class="inline-flex items-center justify-center min-h-[40px] text-sm font-semibold text-brand-700 hover:underline">Purchase invoices from this vendor</a>
                            @endif
                            <x-primary-button class="h-10">{{ $isEdit ? 'Save changes' : 'Save vendor' }}</x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
