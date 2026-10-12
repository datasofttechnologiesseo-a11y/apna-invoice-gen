<x-app-layout :title="'Stock · ' . $product->name">
    @php
        $unit = $product->unit ?: 'NOS';
        $price = (float) ($product->purchase_rate ?? $product->rate);
        $low = $product->low_stock_level !== null && $onHand <= (float) $product->low_stock_level;
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        $btnSecondary = 'inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md whitespace-nowrap';
        $field = 'mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600';
        // What each movement was and the document behind it.
        $invoiceType = (new \App\Models\Invoice)->getMorphClass();
        $billType = (new \App\Models\PurchaseBill)->getMorphClass();
        $creditNoteType = (new \App\Models\CreditNote)->getMorphClass();
        $describe = function ($m) use ($invoiceType, $billType, $creditNoteType) {
            $label = $m->kind === 'sale_return' ? 'Returned by customer' : $m->kindLabel();
            $link = null;
            if ($m->source) {
                $link = match ($m->source_type) {
                    $invoiceType => route('invoices.show', $m->source_id),
                    $billType => route('purchases.show', $m->source_id),
                    // A credit note is read on its invoice.
                    $creditNoteType => $m->source->invoice_id ? route('invoices.show', $m->source->invoice_id) : null,
                    default => null,
                };
            }

            return ['label' => $label, 'link' => $link, 'note' => $m->note];
        };
    @endphp
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Items', 'href' => route('products.index')],
            ['label' => $product->name, 'href' => route('products.edit', $product)],
            ['label' => 'Stock'],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight break-words">{{ $product->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">Stock history{{ $product->sku ? ' · ' . $product->sku : '' }}{{ $product->hsn_sac ? ' · HSN ' . $product->hsn_sac : '' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('products.edit', $product) }}" class="{{ $btnSecondary }}">Edit item</a>
                <a href="{{ route('reports.show', ['report' => 'stock-summary']) }}" class="{{ $btnSecondary }}">Stock summary</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @unless ($product->track_stock)
                <div class="p-4 rounded-xl bg-accent-50 border border-accent-200 text-sm text-accent-900">
                    Stock is not tracked for this item. <a href="{{ route('products.edit', $product) }}#stock" class="font-semibold underline">Turn it on</a> to keep count.
                </div>
            @endunless

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="col-span-2 sm:col-span-1 p-4 rounded-xl ring-1 {{ $low || $onHand < 0 ? 'ring-danger-300 bg-danger-50' : 'ring-gray-200 bg-white' }}">
                    <div class="text-xs font-semibold text-gray-600">In stock now</div>
                    <div class="mt-1 font-display text-2xl font-extrabold tabular-nums {{ $onHand < 0 ? 'text-danger-700' : 'text-gray-900' }}">{{ $qty($onHand) }} <span class="text-sm font-semibold text-gray-500">{{ $unit }}</span></div>
                    @if ($onHand < 0)
                        <div class="text-xs font-semibold text-danger-700 mt-1">More sold than recorded. Enter the purchase or adjust stock.</div>
                    @elseif ($low)
                        <div class="text-xs font-semibold text-danger-700 mt-1">Low stock, reorder (alert at {{ $qty($product->low_stock_level) }})</div>
                    @endif
                </div>
                <div class="p-4 rounded-xl bg-white ring-1 ring-gray-200">
                    <div class="text-xs font-semibold text-gray-600">Stock value</div>
                    <div class="mt-1 font-display text-2xl font-extrabold tabular-nums text-gray-900">₹{{ rupees(max(0, $onHand) * $price) }}</div>
                    <div class="text-xs text-gray-500 mt-1">At {{ $product->purchase_rate !== null ? 'purchase' : 'sale' }} price ₹{{ inr($price) }}</div>
                </div>
                <div class="p-4 rounded-xl bg-white ring-1 ring-gray-200">
                    <div class="text-xs font-semibold text-gray-600">Opening stock</div>
                    <div class="mt-1 font-display text-2xl font-extrabold tabular-nums text-gray-900">{{ $qty($product->opening_stock) }} <span class="text-sm font-semibold text-gray-500">{{ $unit }}</span></div>
                </div>
            </div>

            @if ($product->track_stock)
                {{-- Adjust --}}
                <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 sm:p-5" x-data="{ dir: @js(old('direction', 'in')), reason: @js(old('reason', '')) }">
                    <h2 class="text-sm font-semibold text-gray-900">Add or remove stock</h2>
                    <p class="text-sm text-gray-600 mt-0.5">
                        Issued invoices take stock out and purchase invoices bring it in automatically.
                        Bought on a supplier's bill? @if (\App\Support\Team::can('purchases.create'))<a href="{{ route('purchases.create') }}" class="text-brand-700 font-semibold underline">Enter a purchase invoice</a> and pick this item on the line, so stock and input tax credit update together.@else Enter it as a purchase invoice and pick this item on the line.@endif
                        Use this form for everything else.
                    </p>
                    <form method="POST" action="{{ route('products.stock.adjust', $product) }}" class="mt-4 grid grid-cols-2 sm:grid-cols-12 gap-3 items-start">
                        @csrf
                        <fieldset class="col-span-2 sm:col-span-3">
                            <legend class="text-sm font-medium text-gray-700">Stock *</legend>
                            <div class="mt-1 grid grid-cols-2 rounded-md ring-1 ring-gray-300 overflow-hidden text-sm font-semibold">
                                <label class="flex items-center justify-center h-10 cursor-pointer" :class="dir === 'in' ? 'bg-brand-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'">
                                    <input type="radio" name="direction" value="in" x-model="dir" class="sr-only"> Add
                                </label>
                                <label class="flex items-center justify-center h-10 cursor-pointer border-l border-gray-300" :class="dir === 'out' ? 'bg-brand-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'">
                                    <input type="radio" name="direction" value="out" x-model="dir" class="sr-only"> Remove
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('direction')" for="direction" class="mt-1" />
                        </fieldset>
                        <div class="col-span-1 sm:col-span-2">
                            <label for="adj_qty" class="block text-sm font-medium text-gray-700">Quantity ({{ $unit }}) *</label>
                            <input inputmode="decimal" id="adj_qty" name="quantity" type="number" step="any" min="0.001" required value="{{ old('quantity') }}"
                                   @error('quantity') aria-invalid="true" aria-describedby="quantity-error" @enderror class="{{ $field }}">
                            <x-input-error :messages="$errors->get('quantity')" for="quantity" class="mt-1" />
                        </div>
                        <div class="col-span-1 sm:col-span-3">
                            <label for="adj_date" class="block text-sm font-medium text-gray-700">Date *</label>
                            <input id="adj_date" name="moved_on" type="date" required value="{{ old('moved_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                                   @error('moved_on') aria-invalid="true" aria-describedby="moved_on-error" @enderror class="{{ $field }} min-w-0">
                            <x-input-error :messages="$errors->get('moved_on')" for="moved_on" class="mt-1" />
                        </div>
                        <div class="col-span-2 sm:col-span-4">
                            <label for="adj_reason" class="block text-sm font-medium text-gray-700">Reason *</label>
                            <input id="adj_reason" name="reason" type="text" maxlength="200" required x-model="reason" :placeholder="dir === 'in' ? 'e.g. Stock received from godown' : 'e.g. Damaged in transit'"
                                   @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror class="{{ $field }}">
                            <x-input-error :messages="$errors->get('reason')" for="reason" class="mt-1" />
                        </div>
                        <div class="col-span-2 sm:col-span-12">
                            <span class="block text-xs text-gray-500 mb-1.5">Common reasons</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach (['in' => ['Stock received', 'Customer returned goods', 'Physical count correction', 'Opening stock correction'], 'out' => ['Damaged / expired', 'Returned to supplier', 'Free sample given', 'Used in-house', 'Physical count correction']] as $d => $labels)
                                    @foreach ($labels as $label)
                                        <button type="button" x-show="dir === '{{ $d }}'" @click="reason = @js($label)"
                                                :class="reason === @js($label) ? 'bg-brand-50 ring-brand-300 text-brand-800' : 'bg-white ring-gray-300 text-gray-700 hover:bg-gray-50'"
                                                class="inline-flex items-center min-h-[40px] px-3 rounded-full ring-1 text-sm font-medium">{{ $label }}</button>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                        <div class="col-span-2 sm:col-span-12 flex justify-end">
                            <button class="inline-flex items-center justify-center h-10 px-4 w-full sm:w-auto bg-brand-700 hover:bg-brand-800 text-white rounded-md text-sm font-semibold" x-text="dir === 'in' ? 'Add to stock' : 'Remove from stock'">Save</button>
                        </div>
                    </form>
                </section>
            @endif

            {{-- History --}}
            <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                <div class="px-4 sm:px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">History <span class="text-gray-500 font-normal">· newest first</span></h2>
                </div>

                {{-- Phones: one compact row per movement, the balance always in view. --}}
                <ul class="sm:hidden divide-y divide-gray-100">
                    @forelse ($rows as $row)
                        @php $m = $row['movement']; $d = $describe($m); $q = (float) $m->quantity; @endphp
                        <li class="px-4 py-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm"><span class="font-semibold text-gray-900">{{ $d['label'] }}</span></div>
                                <div class="text-xs text-gray-500 break-words">
                                    {{ $m->moved_on->format('d M Y') }}
                                    @if ($d['note'])
                                        · @if ($d['link'])<a href="{{ $d['link'] }}" class="text-brand-700 hover:underline">{{ $d['note'] }}</a>@else{{ $d['note'] }}@endif
                                    @endif
                                </div>
                            </div>
                            <div class="text-right shrink-0 tabular-nums">
                                <div class="font-mono text-sm font-semibold {{ $q > 0 ? 'text-money-700' : 'text-gray-900' }}">{{ $q > 0 ? '+' : '−' }}{{ $qty(abs($q)) }}</div>
                                <div class="text-xs text-gray-500">Balance <span class="font-mono font-semibold text-gray-900">{{ $qty($row['balance']) }}</span></div>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-sm text-gray-500">No movements yet. Issued invoices and purchase invoices for this item are listed here.</li>
                    @endforelse
                    <li class="px-4 py-3 flex items-center justify-between gap-3 bg-gray-50">
                        <span class="text-sm text-gray-600">Opening stock</span>
                        <span class="text-xs text-gray-500">Balance <span class="font-mono font-semibold text-gray-900">{{ $qty($product->opening_stock) }}</span></span>
                    </li>
                </ul>

                <div class="hidden sm:block">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-600">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold">Date</th>
                                <th class="px-4 py-2.5 text-left font-semibold">Particulars</th>
                                <th class="px-4 py-2.5 text-right font-semibold">In</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Out</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $row)
                                @php $m = $row['movement']; $d = $describe($m); @endphp
                                <tr>
                                    <td class="px-4 py-2.5 whitespace-nowrap">{{ $m->moved_on->format('d M Y') }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-semibold text-gray-800">{{ $d['label'] }}</span>
                                        @if ($d['note'])
                                            @if ($d['link'])
                                                · <a href="{{ $d['link'] }}" class="text-brand-700 hover:underline">{{ $d['note'] }}</a>
                                            @else
                                                <span class="text-gray-500">· {{ $d['note'] }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums text-money-700">{{ (float) $m->quantity > 0 ? $qty($m->quantity) : '' }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums text-gray-900">{{ (float) $m->quantity < 0 ? $qty(abs((float) $m->quantity)) : '' }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums font-semibold">{{ $qty($row['balance']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No movements yet. Issued invoices and purchase invoices for this item are listed here.</td></tr>
                            @endforelse
                            <tr class="bg-gray-50">
                                <td class="px-4 py-2.5 text-gray-600" colspan="4">Opening stock</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ $qty($product->opening_stock) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
