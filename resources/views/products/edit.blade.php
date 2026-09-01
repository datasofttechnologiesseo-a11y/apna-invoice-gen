<x-app-layout :title="$product->exists ? 'Edit Item' : 'New Item'">
    <x-slot name="header">
        <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
            {{ $product->exists ? 'Edit Item' : 'New Item' }}
        </h1>
    </x-slot>

    @php
        // HSN is mandated by Rule 46(g) only when the supplier (this company) is
        // GST-registered. For un-registered dealers the catalogue field is optional.
        $hsnRequired = ! empty($company?->gstin ?? null);
    @endphp

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-breadcrumbs :items="[
                ['label' => 'Products', 'href' => route('products.index')],
                ['label' => $product->exists ? $product->name : 'New Item'],
            ]" />

            <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}"
                  class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                @csrf
                @if ($product->exists) @method('PATCH') @endif

                @if ($errors->any())
                    <div class="m-6 mb-0 p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm" role="alert">
                        <div class="font-semibold mb-1">Please fix the following before saving:</div>
                        <ul class="list-disc pl-5 space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                {{-- Section: Item details --}}
                <section class="p-6 sm:p-8 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Item details</h3>
                    <p class="text-xs text-gray-500 mt-0.5">What you're selling. Reused across invoices via autocomplete.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <x-input-label for="name" value="Item name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $product->name)" required autofocus placeholder="e.g. OPC 53 Grade Cement" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="kind" value="Kind *" />
                            <select id="kind" name="kind" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (config('uqc_units.kinds') as $value => $label)
                                    <option value="{{ $value }}" @selected(old('kind', $product->kind) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Goods use HSN. Services use SAC (6 digits starting with 99).</p>
                        </div>
                        <div>
                            <x-input-label for="sku" value="Item code (optional)" />
                            <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full font-mono" :value="old('sku', $product->sku)" maxlength="60" placeholder="e.g. CEMENT-50KG" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="description" value="Description (optional, shown on invoice)" />
                            <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500" maxlength="1000">{{ old('description', $product->description) }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="inline-flex items-center gap-2">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                <span class="text-sm text-gray-700">Active, available in invoice autocomplete</span>
                            </label>
                        </div>
                    </div>
                </section>

                {{-- Section: Tax & pricing --}}
                <section class="p-6 sm:p-8">
                    <h3 class="text-sm font-bold text-gray-900">Tax &amp; pricing</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Defaults pre-fill the invoice line; you can still tweak per invoice.</p>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <x-input-label for="hsn_sac" :value="'HSN / SAC code' . ($hsnRequired ? ' *' : ' (optional)')" />
                                @include('partials.hsn-search-link', ['label' => 'Search on GST portal'])
                            </div>
                            <x-text-input id="hsn_sac" name="hsn_sac" type="text" class="mt-1 block w-full font-mono" :value="old('hsn_sac', $product->hsn_sac)" :required="$hsnRequired" pattern="[0-9]{4,8}" inputmode="numeric" placeholder="e.g. 25232910" />
                            <p class="text-xs text-gray-500 mt-1">
                                @if ($hsnRequired)
                                    4 digits (turnover &lt; ₹5 Cr) · 6 digits (&gt; ₹5 Cr) · 8 digits for exports.
                                @else
                                    Optional, your business isn't GST-registered. You can add this later.
                                @endif
                            </p>
                            <x-input-error :messages="$errors->get('hsn_sac')" class="mt-2" />
                        </div>
                        {{-- Searchable UQC picker: type to filter the 30+ codes by
                             code or name (e.g. "kg", "litre"). A hidden input submits
                             the chosen code; only a valid code can be committed, so the
                             server-side Rule::in still holds. --}}
                        <div x-data='uqcPicker(@json(config("uqc_units.codes")), @json(old("unit", $product->unit ?: "NOS")))'>
                            <x-input-label for="unit_search" value="Unit (UQC) *" />
                            <input type="hidden" name="unit" :value="selected">
                            <div class="relative mt-1" @click.outside="open = false">
                                <input id="unit_search" type="text" x-model="search" autocomplete="off"
                                       @focus="open = true" @click="open = true"
                                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                                       @keydown.enter.prevent="pick(filtered[highlight])" @keydown.escape="open = false; syncLabel()"
                                       @blur="setTimeout(() => { open = false; syncLabel() }, 150)"
                                       placeholder="Type a unit, e.g. kg, litre, box"
                                       class="block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <div x-show="open" x-cloak class="absolute z-30 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg">
                                    <template x-for="(u, i) in filtered" :key="u.code">
                                        <button type="button" @click="pick(u)" @mouseenter="highlight = i"
                                                class="block w-full text-left px-3 py-2 text-sm"
                                                :class="i === highlight ? 'bg-brand-50 text-brand-700' : 'text-gray-700 hover:bg-gray-50'"
                                                x-text="u.label"></button>
                                    </template>
                                    <div x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-500">No matching unit</div>
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('unit')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="rate" value="Sale price (₹, pre-tax) *" />
                            <x-text-input id="rate" name="rate" type="number" step="any" min="0" class="mt-1 block w-full" :value="old('rate', $product->rate ?? 0)" required />
                            <x-input-error :messages="$errors->get('rate')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="gst_rate" value="Default GST% *" />
                            <select id="gst_rate" name="gst_rate" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (config('gst.rates') as $r)
                                    <option value="{{ $r['value'] }}" @selected((float) old('gst_rate', $product->gst_rate ?? 18) === (float) $r['value']) title="{{ $r['note'] }}">{{ $r['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                {{-- Action bar --}}
                <div class="px-6 sm:px-8 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <a href="{{ route('products.index') }}" class="text-sm text-gray-500 hover:text-gray-800">Cancel</a>
                    <x-primary-button>{{ $product->exists ? 'Save' : 'Create product' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function uqcPicker(codes, initial) {
            const labelFor = (code) => (codes.find(u => u.code === code) || {}).label || '';
            return {
                codes,
                open: false,
                highlight: 0,
                selected: initial || 'NOS',
                search: labelFor(initial || 'NOS'),
                get filtered() {
                    const q = this.search.trim().toLowerCase();
                    // While a valid selection's label is shown verbatim, list everything.
                    if (!q || q === labelFor(this.selected).toLowerCase()) return this.codes;
                    return this.codes.filter(u =>
                        u.code.toLowerCase().includes(q) || u.label.toLowerCase().includes(q));
                },
                move(dir) {
                    this.open = true;
                    const len = this.filtered.length;
                    if (len) this.highlight = (this.highlight + dir + len) % len;
                },
                pick(u) {
                    if (!u) return;
                    this.selected = u.code;
                    this.search = u.label;
                    this.open = false;
                    this.highlight = 0;
                },
                // On blur/escape, snap the text back to the committed code's label so
                // a half-typed, invalid value can never be submitted.
                syncLabel() {
                    this.search = labelFor(this.selected);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
