{{--
    Dropdown panel shared by mobile and desktop product comboboxes.
    Mounts inside an Alpine `x-data="productRowCombobox(idx)"` scope.

    The component exposes:
      - combo.{search, open, highlight, results, loading}
      - quickAdd.{open, name, rate, gst_rate, hsn_sac, unit, submitting, error, errors}
      - pick(p), openQuickAdd(), submitQuickAdd()

    `mobile` prop controls width - full-width on mobile, fixed-width on desktop.
--}}
@props(['mobile' => false])

{{-- Teleport to <body> so the dropdown isn't clipped by the table's
     overflow-x-auto wrapper (CSS quirk: overflow-x non-visible implicitly
     clips overflow-y too). Positioned with `position: fixed` using coords
     computed from the input's getBoundingClientRect() in productRowCombobox. --}}
<template x-teleport="body">
<div x-show="combo.open" x-cloak
     :style="`position: fixed; top: ${panel.top}px; left: ${panel.left}px; width: ${panel.width}px;`"
     {{-- z-30: above page content but below the sticky nav (z-40) and modals (z-50). --}}
     class="z-30 bg-white border border-gray-200 rounded-md shadow-lg max-h-72 overflow-y-auto">

    {{-- Loading: only while there is nothing to show yet. Over a list that is
         already on screen it read as stuck, so the old list stays until the
         new one replaces it. --}}
    <div x-show="combo.loading && combo.results.length === 0" x-cloak class="px-3 py-3 text-xs text-gray-600 flex items-center gap-2">
        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
        Searching…
    </div>

    {{-- Empty hint --}}
    <template x-if="!combo.loading && combo.results.length === 0 && !combo.search.trim()">
        <div class="px-3 py-2 text-xs text-gray-600">Type to search items</div>
    </template>

    {{-- Results list. The loop index is "n", never "idx": inside this
         component "idx" is the invoice row, and shadowing it made a click on
         row 2's results fill row 1. mousedown.prevent keeps focus in the
         search box, so its blur handler does not save the half-typed text as
         the description before the pick lands. --}}
    <template x-for="(p, n) in combo.results" :key="p.id">
        <button type="button"
                @mousedown.prevent
                @click="pick(p)"
                @mouseenter="combo.highlight = n"
                class="block w-full text-left px-3 py-2 text-sm hover:bg-brand-50 transition"
                :class="combo.highlight === n ? 'bg-brand-50' : ''">
            <div class="flex items-baseline justify-between gap-2">
                <span class="font-medium text-gray-900 truncate" x-text="p.name"></span>
                <span class="text-xs font-mono text-gray-700">₹ <span x-text="(v => v.toLocaleString('en-IN', Number.isInteger(v) ? { maximumFractionDigits: 0 } : { minimumFractionDigits: 2, maximumFractionDigits: 2 }))(Math.round((parseFloat(p.rate) || 0) * 100) / 100)"></span></span>
            </div>
            <div class="text-xs text-gray-600 flex flex-wrap gap-x-2">
                <template x-if="p.sku"><span x-text="'SKU ' + p.sku"></span></template>
                <template x-if="p.hsn_sac"><span x-text="'HSN ' + p.hsn_sac"></span></template>
                <template x-if="p.gst_rate !== null && p.gst_rate !== undefined"><span x-text="'GST ' + p.gst_rate + '%'"></span></template>
                <template x-if="p.unit"><span x-text="p.unit"></span></template>
                <template x-if="p.stock !== null && p.stock !== undefined">
                    <span :class="+p.stock <= 0 ? 'text-danger-700 font-semibold' : 'text-money-700 font-semibold'" x-text="'In stock ' + (+p.stock)"></span>
                </template>
            </div>
        </button>
    </template>

    {{-- "+ Add new" button - opens the inline mini-form below --}}
    <button type="button"
            x-show="!quickAdd.open && combo.search.trim()"
            @click="openQuickAdd()"
            class="block w-full text-left px-3 py-2 min-h-[40px] text-sm bg-brand-50 hover:bg-brand-100 text-brand-900 font-semibold border-t border-brand-200 sticky bottom-0">
        + New item "<span x-text="combo.search.trim()"></span>"
    </button>

    {{-- Inline mini-form for quick-create. Captures only the essentials -
         name, price, GST%. HSN/SAC and unit are optional and default to
         sensible values. The full product record can be edited later. --}}
    <div x-show="quickAdd.open" x-cloak class="border-t border-gray-200 p-3 bg-gray-50 space-y-2">
        <div class="text-sm font-semibold text-gray-900">Save new item</div>

        <div x-show="quickAdd.error" x-cloak class="text-xs text-danger-700 bg-danger-50 border border-danger-200 rounded px-2 py-1" x-text="quickAdd.error"></div>

        <div>
            <label class="block text-xs font-medium text-gray-700">Name *</label>
            <input type="text" aria-label="New product name" x-model="quickAdd.name" required maxlength="255"
                   class="mt-0.5 block w-full text-sm border-gray-300 rounded"
                   :class="quickAdd.errors.name && '!border-danger-400 !ring-danger-400'">
            <p x-show="quickAdd.errors.name" x-cloak class="text-xs text-danger-700" x-text="quickAdd.errors.name"></p>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-700">Price (₹) *</label>
                <input type="number" aria-label="New product price" step="any" min="0" inputmode="decimal" x-model.number="quickAdd.rate" required
                       class="mt-0.5 block w-full text-sm border-gray-300 rounded text-right"
                       :class="quickAdd.errors.rate && '!border-danger-400 !ring-danger-400'">
                <p x-show="quickAdd.errors.rate" x-cloak class="text-xs text-danger-700" x-text="quickAdd.errors.rate"></p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">GST % *</label>
                <select aria-label="New product GST rate" x-model.number="quickAdd.gst_rate" class="mt-0.5 block w-full text-sm border-gray-300 rounded"
                        :class="quickAdd.errors.gst_rate && '!border-danger-400 !ring-danger-400'">
                    @foreach (config('gst.rates') as $r)
                        <option value="{{ $r['value'] }}">{{ $r['label'] }}</option>
                    @endforeach
                </select>
                <p x-show="quickAdd.errors.gst_rate" x-cloak class="text-xs text-danger-700" x-text="quickAdd.errors.gst_rate"></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <label class="block text-xs font-medium text-gray-700">HSN/SAC (optional)</label>
                    @include('partials.hsn-search-link', ['size' => 'w-3 h-3'])
                </div>
                <input type="text" aria-label="HSN or SAC code for the new product" x-model="quickAdd.hsn_sac" maxlength="8" inputmode="numeric" placeholder="e.g. 998314"
                       class="mt-0.5 block w-full text-sm border-gray-300 rounded font-mono"
                       :class="quickAdd.errors.hsn_sac && '!border-danger-400 !ring-danger-400'">
                <p x-show="quickAdd.errors.hsn_sac" x-cloak class="text-xs text-danger-700" x-text="quickAdd.errors.hsn_sac"></p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Unit (optional)</label>
                <input type="text" aria-label="Unit for the new product" x-model="quickAdd.unit" maxlength="6" placeholder="NOS, KGS, HRS…"
                       class="mt-0.5 block w-full text-sm border-gray-300 rounded uppercase">
                <p x-show="quickAdd.errors.unit" x-cloak class="text-xs text-danger-700" x-text="quickAdd.errors.unit"></p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-1">
            <button type="button" @click="quickAdd.open = false" class="inline-flex items-center h-9 px-3 rounded-md text-sm font-semibold bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50">Cancel</button>
            <button type="button" @click="submitQuickAdd()" :disabled="quickAdd.submitting"
                    class="inline-flex items-center gap-1 h-9 px-3 bg-brand-700 hover:bg-brand-800 disabled:opacity-60 text-white text-sm font-semibold rounded-md">
                <svg x-show="quickAdd.submitting" x-cloak class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                <span x-text="quickAdd.submitting ? 'Saving…' : 'Save item'"></span>
            </button>
        </div>
    </div>
</div>
</template>
