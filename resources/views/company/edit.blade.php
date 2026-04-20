<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $company->exists ? 'Edit ' . $company->name : 'New company' }}
            </h2>
            <a href="{{ route('companies.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← All companies</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <header class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Business details</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        This information appears on your invoices' letterhead and is used for GST place-of-supply detection.
                    </p>
                </header>

                <form method="POST" action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @if ($company->exists) @method('PATCH') @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="name" value="Business Name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $company->name)" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="gstin" value="GSTIN (15-char)" />
                            <x-text-input id="gstin" name="gstin" type="text" class="mt-1 block w-full uppercase" :value="old('gstin', $company->gstin)" maxlength="15" />
                            <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="pan" value="PAN" />
                            <x-text-input id="pan" name="pan" type="text" class="mt-1 block w-full uppercase" :value="old('pan', $company->pan)" maxlength="10" />
                            <x-input-error :messages="$errors->get('pan')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $company->email)" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $company->phone)" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="website" value="Website" />
                            <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $company->website)" placeholder="https://..." />
                            <x-input-error :messages="$errors->get('website')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                            <select id="state_id" name="state_id" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                <option value="">— Select —</option>
                                @foreach ($states as $state)
                                    <option value="{{ $state->id }}" @selected(old('state_id', $company->state_id) == $state->id)>
                                        {{ $state->name }} ({{ $state->gst_code }})
                                    </option>
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                    <input type="hidden" name="default_currency" value="INR">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="invoice_prefix" value="Invoice prefix *" />
                            <x-text-input id="invoice_prefix" name="invoice_prefix" type="text" class="mt-1 block w-full" :value="old('invoice_prefix', $company->invoice_prefix)" required />
                        </div>
                        <div>
                            <x-input-label for="invoice_number_padding" value="Number padding (digits) *" />
                            <x-text-input id="invoice_number_padding" name="invoice_number_padding" type="number" min="1" max="8" class="mt-1 block w-full" :value="old('invoice_number_padding', $company->invoice_number_padding)" required />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="default_terms" value="Default terms & conditions (shown at bottom of invoices)" />
                        <textarea id="default_terms" name="default_terms" rows="4" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" placeholder="1. Payment due within 30 days.&#10;2. Late payment attracts 2% monthly interest.">{{ old('default_terms', $company->default_terms) }}</textarea>
                    </div>

                    <div>
                        <x-input-label for="declaration" value="Declaration (appears on every invoice PDF)" />
                        <textarea id="declaration" name="declaration" rows="2" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" placeholder="We declare that this invoice shows the actual price of the goods/services described and that all particulars are true and correct.">{{ old('declaration', $company->declaration) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Standard legal declaration required by CBIC for tax invoices.</p>
                    </div>

                    <div class="border-t pt-6">
                        <h3 class="font-display font-bold text-gray-900 text-lg">Bank / payment details</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Shown on invoice PDF so customers know how to pay</p>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
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
                    </div>

                    <div class="border-t pt-6 flex items-center gap-4">
                        <x-primary-button>{{ __('Save profile') }}</x-primary-button>
                        <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">Back to dashboard</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
