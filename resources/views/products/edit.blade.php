<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $product->exists ? 'Edit product' : 'New product' }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-breadcrumbs :items="[
                ['label' => 'Products', 'href' => route('products.index')],
                ['label' => $product->exists ? $product->name : 'New product'],
            ]" />
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded">
                    <ul class="list-disc pl-6">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="p-6 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="space-y-6">
                    @csrf
                    @if ($product->exists) @method('PATCH') @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="name" value="Product / service name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $product->name)" required autofocus />
                        </div>

                        <div>
                            <x-input-label for="kind" value="Kind *" />
                            <select id="kind" name="kind" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                @foreach (config('uqc_units.kinds') as $value => $label)
                                    <option value="{{ $value }}" @selected(old('kind', $product->kind) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Goods use HSN (4/6/8 digits). Services use SAC (6 digits starting with 99).</p>
                        </div>

                        <div>
                            <x-input-label for="sku" value="SKU (optional, internal code)" />
                            <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full font-mono" :value="old('sku', $product->sku)" maxlength="60" placeholder="e.g. CEMENT-50KG" />
                        </div>

                        <div>
                            <x-input-label for="hsn_sac" value="HSN / SAC code *" />
                            <x-text-input id="hsn_sac" name="hsn_sac" type="text" class="mt-1 block w-full font-mono" :value="old('hsn_sac', $product->hsn_sac)" required pattern="[0-9]{4,8}" inputmode="numeric" placeholder="e.g. 25232910" />
                            <p class="text-xs text-gray-500 mt-1">4 digits (turnover &lt; ₹5 Cr) · 6 digits (&gt; ₹5 Cr) · 8 digits for exports.</p>
                        </div>

                        <div>
                            <x-input-label for="unit" value="Unit (UQC) *" />
                            <select id="unit" name="unit" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                @foreach (config('uqc_units.codes') as $u)
                                    <option value="{{ $u['code'] }}" @selected(old('unit', $product->unit ?: 'NOS') === $u['code'])>{{ $u['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="rate" value="Default rate (₹, pre-tax) *" />
                            <x-text-input id="rate" name="rate" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('rate', $product->rate ?? 0)" required />
                        </div>

                        <div>
                            <x-input-label for="gst_rate" value="Default GST% *" />
                            <select id="gst_rate" name="gst_rate" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                @foreach (config('gst.rates') as $r)
                                    <option value="{{ $r['value'] }}" @selected((float) old('gst_rate', $product->gst_rate ?? 18) === (float) $r['value']) title="{{ $r['note'] }}">{{ $r['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="description" value="Description (optional, shown on invoice)" />
                            <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" maxlength="1000">{{ old('description', $product->description) }}</textarea>
                        </div>

                        <div class="md:col-span-2">
                            <label class="inline-flex items-center gap-2">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                <span class="text-sm text-gray-700">Active — available in invoice autocomplete</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <a href="{{ route('products.index') }}" class="text-gray-500 hover:underline">← Cancel</a>
                        <x-primary-button>{{ $product->exists ? 'Save' : 'Create product' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
