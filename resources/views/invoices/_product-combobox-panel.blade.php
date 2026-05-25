{{--
    Dropdown panel shared by mobile and desktop product comboboxes.
    Mounts inside an Alpine `x-data="productRowCombobox(idx)"` scope.

    The component exposes:
      - combo.{search, open, highlight, results, loading}
      - quickAdd.{open, name, rate, gst_rate, hsn_sac, unit, submitting, error, errors}
      - pick(p), openQuickAdd(), submitQuickAdd()

    `mobile` prop controls width — full-width on mobile, fixed-width on desktop.
--}}
@props(['mobile' => false])

{{-- Teleport to <body> so the dropdown isn't clipped by the table's
     overflow-x-auto wrapper (CSS quirk: overflow-x non-visible implicitly
     clips overflow-y too). Positioned with `position: fixed` using coords
     computed from the input's getBoundingClientRect() in productRowCombobox. --}}
<template x-teleport="body">
<div x-show="combo.open" x-cloak
     :style="`position: fixed; top: ${panel.top}px; left: ${panel.left}px; width: ${panel.width}px;`"
     class="z-50 bg-white border border-gray-200 rounded-md shadow-lg max-h-72 overflow-y-auto">

    {{-- Loading spinner --}}
    <div x-show="combo.loading" x-cloak class="px-3 py-3 text-xs text-gray-500 flex items-center gap-2">
        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
        Searching…
    </div>

    {{-- Empty hint --}}
    <template x-if="!combo.loading && combo.results.length === 0 && !combo.search.trim()">
        <div class="px-3 py-2 text-xs text-gray-500">Start typing a product name…</div>
    </template>

    {{-- Results list --}}
    <template x-for="(p, idx) in combo.results" :key="p.id">
        <button type="button"
                @click="pick(p)"
                @mouseenter="combo.highlight = idx"
                class="block w-full text-left px-3 py-2 text-sm hover:bg-brand-50 transition"
                :class="combo.highlight === idx ? 'bg-brand-50' : ''">
            <div class="flex items-baseline justify-between gap-2">
                <span class="font-medium text-gray-900 truncate" x-text="p.name"></span>
                <span class="text-xs font-mono text-gray-700">₹ <span x-text="parseFloat(p.rate || 0).toFixed(2)"></span></span>
            </div>
            <div class="text-xs text-gray-500 flex flex-wrap gap-x-2">
                <template x-if="p.sku"><span x-text="'SKU ' + p.sku"></span></template>
                <template x-if="p.hsn_sac"><span x-text="'HSN ' + p.hsn_sac"></span></template>
                <template x-if="p.gst_rate !== null && p.gst_rate !== undefined"><span x-text="'GST ' + p.gst_rate + '%'"></span></template>
                <template x-if="p.unit"><span x-text="p.unit"></span></template>
            </div>
        </button>
    </template>

    {{-- "+ Add new" button — opens the inline mini-form below --}}
    <button type="button"
            x-show="!quickAdd.open && combo.search.trim()"
            @click="openQuickAdd()"
            class="block w-full text-left px-3 py-2 text-sm bg-saffron-50 hover:bg-saffron-100 text-saffron-900 font-semibold border-t border-saffron-200 sticky bottom-0">
        + Save as new product: "<span x-text="combo.search.trim()"></span>"
    </button>

    {{-- Inline mini-form for quick-create. Captures only the essentials —
         name, price, GST%. HSN/SAC and unit are optional and default to
         sensible values. The full product record can be edited later. --}}
    <div x-show="quickAdd.open" x-cloak class="border-t border-gray-200 p-3 bg-gray-50 space-y-2">
        <div class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Save new product</div>

        <div x-show="quickAdd.error" x-cloak class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1" x-text="quickAdd.error"></div>

        <div>
            <label class="block text-[10px] font-bold uppercase text-gray-500">Name *</label>
            <input type="text" x-model="quickAdd.name" required maxlength="255"
                   class="mt-0.5 block w-full text-sm border-gray-300 rounded"
                   :class="quickAdd.errors.name && '!border-red-400 !ring-red-400'">
            <p x-show="quickAdd.errors.name" x-cloak class="text-[10px] text-red-600" x-text="quickAdd.errors.name"></p>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] font-bold uppercase text-gray-500">Price (₹) *</label>
                <input type="number" step="any" min="0" inputmode="decimal" x-model.number="quickAdd.rate" required
                       class="mt-0.5 block w-full text-sm border-gray-300 rounded text-right"
                       :class="quickAdd.errors.rate && '!border-red-400 !ring-red-400'">
                <p x-show="quickAdd.errors.rate" x-cloak class="text-[10px] text-red-600" x-text="quickAdd.errors.rate"></p>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase text-gray-500">GST % *</label>
                <select x-model.number="quickAdd.gst_rate" class="mt-0.5 block w-full text-sm border-gray-300 rounded"
                        :class="quickAdd.errors.gst_rate && '!border-red-400 !ring-red-400'">
                    @foreach (config('gst.rates') as $r)
                        <option value="{{ $r['value'] }}">{{ $r['label'] }}</option>
                    @endforeach
                </select>
                <p x-show="quickAdd.errors.gst_rate" x-cloak class="text-[10px] text-red-600" x-text="quickAdd.errors.gst_rate"></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] font-bold uppercase text-gray-500">HSN/SAC (optional)</label>
                <input type="text" x-model="quickAdd.hsn_sac" maxlength="8" inputmode="numeric" placeholder="e.g. 998314"
                       class="mt-0.5 block w-full text-sm border-gray-300 rounded font-mono"
                       :class="quickAdd.errors.hsn_sac && '!border-red-400 !ring-red-400'">
                <p x-show="quickAdd.errors.hsn_sac" x-cloak class="text-[10px] text-red-600" x-text="quickAdd.errors.hsn_sac"></p>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase text-gray-500">Unit (optional)</label>
                <input type="text" x-model="quickAdd.unit" maxlength="6" placeholder="NOS, KGS, HRS…"
                       class="mt-0.5 block w-full text-sm border-gray-300 rounded uppercase">
                <p x-show="quickAdd.errors.unit" x-cloak class="text-[10px] text-red-600" x-text="quickAdd.errors.unit"></p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-1">
            <button type="button" @click="quickAdd.open = false" class="text-xs text-gray-600 hover:text-gray-900 px-2 py-1">Cancel</button>
            <button type="button" @click="submitQuickAdd()" :disabled="quickAdd.submitting"
                    class="inline-flex items-center gap-1 px-3 py-1 bg-brand-700 hover:bg-brand-800 disabled:opacity-60 text-white text-xs font-semibold rounded">
                <svg x-show="quickAdd.submitting" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                <span x-text="quickAdd.submitting ? 'Saving…' : 'Save product'"></span>
            </button>
        </div>
    </div>
</div>
</template>
