<x-app-layout :title="'Stock · ' . $product->name">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ $product->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">Stock history{{ $product->sku ? ' · ' . $product->sku : '' }}{{ $product->hsn_sac ? ' · HSN ' . $product->hsn_sac : '' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('products.edit', $product) }}" class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-md ring-1 ring-gray-300 bg-white hover:bg-gray-50">Edit item</a>
                <a href="{{ route('reports.show', ['report' => 'stock-summary']) }}" class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-md ring-1 ring-gray-300 bg-white hover:bg-gray-50">All stock</a>
            </div>
        </div>
    </x-slot>

    @php
        $unit = $product->unit ?: 'NOS';
        $price = (float) ($product->purchase_rate ?? $product->rate);
        $low = $product->low_stock_level !== null && $onHand <= (float) $product->low_stock_level;
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @unless ($product->track_stock)
                <div class="p-4 rounded-xl bg-accent-50 border border-accent-200 text-sm text-accent-900">
                    Stock is not tracked for this item. <a href="{{ route('products.edit', $product) }}" class="font-semibold underline">Turn it on</a> to keep count.
                </div>
            @endunless

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-4 rounded-xl bg-white ring-1 {{ $low ? 'ring-danger-300 bg-danger-50' : 'ring-gray-200' }}">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">In stock now</div>
                    <div class="mt-1 font-display text-2xl font-extrabold tabular-nums {{ $onHand < 0 ? 'text-danger-700' : 'text-gray-900' }}">{{ $qty($onHand) }} <span class="text-sm font-semibold text-gray-500">{{ $unit }}</span></div>
                    @if ($low)
                        <div class="text-xs font-semibold text-danger-700 mt-1">Low stock, reorder (alert at {{ $qty($product->low_stock_level) }})</div>
                    @elseif ($onHand < 0)
                        <div class="text-xs font-semibold text-danger-700 mt-1">More sold than recorded. Enter the purchase or adjust stock.</div>
                    @endif
                </div>
                <div class="p-4 rounded-xl bg-white ring-1 ring-gray-200">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Stock value</div>
                    <div class="mt-1 font-display text-2xl font-extrabold tabular-nums text-gray-900">₹{{ rupees(max(0, $onHand) * $price) }}</div>
                    <div class="text-xs text-gray-500 mt-1">at {{ $product->purchase_rate !== null ? 'purchase' : 'sale' }} price ₹{{ inr($price) }}</div>
                </div>
                <div class="p-4 rounded-xl bg-white ring-1 ring-gray-200">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Opening stock</div>
                    <div class="mt-1 font-display text-2xl font-extrabold tabular-nums text-gray-900">{{ $qty($product->opening_stock) }} <span class="text-sm font-semibold text-gray-500">{{ $unit }}</span></div>
                </div>
            </div>

            @if ($product->track_stock)
                {{-- Adjust --}}
                <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 p-5" x-data="{ dir: @js(old('direction', 'in')), reason: @js(old('reason', '')) }">
                    <h2 class="text-sm font-bold text-gray-900">Add or remove stock</h2>
                    <p class="text-xs text-gray-600 mt-0.5">
                        Issued invoices take stock out and purchase invoices bring it in automatically.
                        Bought on a supplier's bill? @if (\App\Support\Team::can('purchases.create'))<a href="{{ route('purchases.create') }}" class="text-brand-700 font-semibold underline">Enter a purchase invoice</a> and link this item, so stock and input tax credit update together.@else Enter it as a purchase invoice and link this item.@endif
                        Use this form for everything else.
                    </p>
                    @if ($errors->any())
                        <div class="mt-3 p-3 rounded bg-danger-50 border border-danger-200 text-danger-800 text-sm">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
                    @endif
                    <form method="POST" action="{{ route('products.stock.adjust', $product) }}" class="mt-4 grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        @csrf
                        <div class="sm:col-span-3">
                            <span class="text-xs font-semibold text-gray-600">Stock</span>
                            <div class="mt-1 grid grid-cols-2 rounded-md ring-1 ring-gray-300 overflow-hidden text-sm font-semibold">
                                <label class="text-center py-2 cursor-pointer" :class="dir === 'in' ? 'bg-money-600 text-white' : 'bg-white text-gray-700'">
                                    <input type="radio" name="direction" value="in" x-model="dir" class="sr-only"> + Add
                                </label>
                                <label class="text-center py-2 cursor-pointer" :class="dir === 'out' ? 'bg-danger-600 text-white' : 'bg-white text-gray-700'">
                                    <input type="radio" name="direction" value="out" x-model="dir" class="sr-only"> − Remove
                                </label>
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="adj_qty" class="text-xs font-semibold text-gray-600">Quantity ({{ $unit }})</label>
                            <input inputmode="decimal" id="adj_qty" name="quantity" type="number" step="any" min="0.001" required value="{{ old('quantity') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                        </div>
                        <div class="sm:col-span-3">
                            <label for="adj_date" class="text-xs font-semibold text-gray-600">Date</label>
                            <input id="adj_date" name="moved_on" type="date" required value="{{ old('moved_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                        </div>
                        <div class="sm:col-span-4">
                            <label for="adj_reason" class="text-xs font-semibold text-gray-600">Reason</label>
                            <input id="adj_reason" name="reason" type="text" maxlength="200" required x-model="reason" :placeholder="dir === 'in' ? 'e.g. Stock received from godown' : 'e.g. Damaged in transit'"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                        </div>
                        <div class="sm:col-span-12 flex flex-wrap items-center gap-2">
                            <span class="text-xs text-gray-500">Common reasons:</span>
                            @foreach (['in' => ['Stock received', 'Customer returned goods', 'Physical count correction', 'Opening stock correction'], 'out' => ['Damaged / expired', 'Returned to supplier', 'Free sample given', 'Used in-house', 'Physical count correction']] as $d => $labels)
                                @foreach ($labels as $label)
                                    <button type="button" x-show="dir === '{{ $d }}'" @click="reason = @js($label)"
                                            class="px-2.5 py-1.5 rounded-full text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700">{{ $label }}</button>
                                @endforeach
                            @endforeach
                            <button class="ml-auto px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-md text-sm font-semibold" x-text="dir === 'in' ? 'Add to stock' : 'Remove from stock'">Save</button>
                        </div>
                    </form>
                </section>
            @endif

            {{-- History --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">History <span class="text-gray-500 font-normal">· newest first</span></h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[560px]">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
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
                                @php
                                    $m = $row['movement'];
                                    $link = match ($m->source_type) {
                                        (new \App\Models\Invoice)->getMorphClass() => route('invoices.show', $m->source_id),
                                        (new \App\Models\PurchaseBill)->getMorphClass() => route('purchases.show', $m->source_id),
                                        default => null,
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-2.5 whitespace-nowrap">{{ $m->moved_on->format('d M Y') }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-semibold text-gray-800">{{ $m->kindLabel() }}</span>
                                        @if ($link && $m->source)
                                            · <a href="{{ $link }}" class="text-brand-700 hover:underline">{{ $m->note }}</a>
                                        @else
                                            <span class="text-gray-500">· {{ $m->note }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums text-money-700">{{ (float) $m->quantity > 0 ? $qty($m->quantity) : '' }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums text-danger-700">{{ (float) $m->quantity < 0 ? $qty(abs((float) $m->quantity)) : '' }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums font-semibold">{{ $qty($row['balance']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No movements yet. Issued invoices and purchase invoices for this item are listed here.</td></tr>
                            @endforelse
                            <tr class="bg-gray-50">
                                <td class="px-4 py-2.5 text-gray-500" colspan="4">Opening stock</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ $qty($product->opening_stock) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
