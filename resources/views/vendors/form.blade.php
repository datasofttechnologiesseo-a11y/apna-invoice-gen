<x-app-layout :title="$isEdit ? 'Edit vendor' : 'New vendor'">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ $isEdit ? 'Edit ' . $vendor->name : 'New vendor' }}</h1>
            <a href="{{ route('vendors.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← All vendors</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="p-6 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ $isEdit ? route('vendors.update', $vendor) : route('vendors.store') }}" class="space-y-6">
                    @csrf
                    @if ($isEdit) @method('PUT') @endif

                    @if ($errors->any())
                        <div class="p-4 rounded-lg bg-danger-50 border border-danger-200 text-danger-800 text-sm" role="alert">
                            <div class="font-semibold mb-1">Correct the following before saving:</div>
                            <ul class="list-disc pl-5 space-y-0.5">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <section class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <x-input-label for="name" value="Vendor name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $vendor->name)" required maxlength="160" autocomplete="off" placeholder="e.g. Sharma Traders" />
                        </div>
                        <div>
                            <x-input-label for="gstin" value="GSTIN (if registered)" />
                            <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full font-mono uppercase" :value="old('gstin', $vendor->gstin)" maxlength="15" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="27AAAAA0000A1Z5" aria-describedby="gstin-hint" />
                            <p id="gstin-hint" class="mt-1 text-[11px] text-gray-500">Needed to claim ITC. Must match the vendor's state.</p>
                        </div>
                        <div>
                            <x-input-label for="state_id" value="State" />
                            <select id="state_id" name="state_id" aria-describedby="state_id-hint" @error('state_id') aria-invalid="true" @enderror class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">
                                <option value="">Select state</option>
                                @foreach ($states as $st)
                                    <option value="{{ $st->id }}" @selected((int) old('state_id', $vendor->state_id) === $st->id)>{{ $st->name }} ({{ $st->gst_code }})</option>
                                @endforeach
                            </select>
                            <p id="state_id-hint" class="mt-1 text-[11px] text-gray-500">Sets CGST + SGST or IGST on their invoices.</p>
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="address_line1" value="Address line 1" />
                            <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $vendor->address_line1)" maxlength="255" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="address_line2" value="Address line 2" />
                            <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $vendor->address_line2)" maxlength="255" />
                        </div>
                        <div>
                            <x-input-label for="city" value="City" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $vendor->city)" maxlength="100" />
                        </div>
                        <div>
                            <x-input-label for="postal_code" value="Postal code" />
                            @php $vendorInIndia = strcasecmp(trim((string) old('country', $vendor->country ?? 'India')), 'India') === 0; @endphp
                            <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $vendor->postal_code)" :inputmode="$vendorInIndia ? 'numeric' : 'text'" :maxlength="$vendorInIndia ? 6 : 10" autocomplete="off" />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" name="phone" type="tel" inputmode="tel" class="mt-1 block w-full" :value="old('phone', $vendor->phone)" maxlength="30" autocomplete="off" placeholder="+91 98765 43210" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $vendor->email)" autocomplete="off" />
                        </div>
                        <input type="hidden" name="country" value="{{ old('country', $vendor->country ?? 'India') }}">
                        <div class="md:col-span-2">
                            <x-input-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="2" maxlength="1000" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">{{ old('notes', $vendor->notes) }}</textarea>
                        </div>
                    </section>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <a href="{{ route('vendors.index') }}" class="text-sm text-gray-500 hover:underline">← Cancel</a>
                        <x-primary-button>{{ $isEdit ? 'Save changes' : 'Save vendor' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
