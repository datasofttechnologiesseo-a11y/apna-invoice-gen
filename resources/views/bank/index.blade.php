<x-app-layout title="Bank reconciliation">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Bank reconciliation</h1>
                <p class="text-sm text-gray-500 mt-1">Match bank statement lines with receipts, vendor payments and expenses.</p>
            </div>
            @if ($counts['unmatched'])
                <form method="POST" action="{{ route('bank.auto-match') }}" class="self-start">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 px-3 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded shadow-sm">Match automatically</button>
                </form>
            @endif
        </div>
    </x-slot>

    @php
        $num = 'px-3 py-2.5 text-right font-mono tabular-nums whitespace-nowrap';
        $tabs = ['unmatched' => 'To review', 'matched' => 'Matched', 'ignored' => 'Ignored', 'all' => 'All'];
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Upload --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 p-5" x-data="{ name: '' }">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Upload bank statement</h2>
                        <ol class="mt-2 text-sm text-gray-600 list-decimal pl-5 space-y-1">
                            <li>In net banking, open the account statement and pick the dates.</li>
                            <li>Download it as <strong>Excel (.xlsx)</strong> or <strong>CSV</strong>. Any bank works.</li>
                            <li>Upload it here. Lines already uploaded are skipped.</li>
                        </ol>
                        @if ($statements->isNotEmpty())
                            <div class="mt-3 text-xs text-gray-500">Recent:
                                @foreach ($statements->take(4) as $st)
                                    <span class="inline-flex items-center gap-1 mr-2">{{ $st->account_name }} · {{ $st->period_from?->format('d M') }}-{{ $st->period_to?->format('d M Y') }} ({{ $st->lines_count }})
                                        <x-confirm-form :action="route('bank.statements.destroy', $st)" method="DELETE" title="Remove this statement?"
                                                        message="Its lines and matches are deleted. Receipts, payments and expenses are not affected." confirmLabel="Remove" confirmClass="bg-danger-600 hover:bg-danger-700" tone="danger">
                                            <button type="button" class="text-danger-600 hover:underline" title="Remove this statement">×</button>
                                        </x-confirm-form>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('bank.upload') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        @if ($errors->any())
                            <div class="p-3 rounded bg-danger-50 border border-danger-200 text-danger-800 text-sm" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
                        @endif
                        <div>
                            <label for="account_name" class="text-[10px] uppercase tracking-wider font-bold text-gray-500 block mb-1">Bank account</label>
                            <input id="account_name" name="account_name" list="bank-accounts" required maxlength="80" value="{{ old('account_name', $account ?: ($accounts->first() ?? '')) }}" placeholder="e.g. HDFC Current A/c"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                            <datalist id="bank-accounts">@foreach ($accounts as $a)<option value="{{ $a }}">@endforeach</datalist>
                        </div>
                        <label class="block border-2 border-dashed border-gray-300 hover:border-brand-500 rounded-xl p-4 text-center cursor-pointer transition">
                            <input type="file" name="file" accept=".xlsx,.csv,.txt" required class="sr-only" @change="name = $event.target.files[0]?.name || ''">
                            <span class="block text-sm font-semibold text-gray-800" x-text="name || 'Choose statement file'"></span>
                            <span class="block text-xs text-gray-500">Excel (.xlsx) or CSV</span>
                        </label>
                        <div class="text-right">
                            <button class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-md text-sm font-semibold">Upload and match</button>
                        </div>
                    </form>
                </div>
            </section>

            {{-- Summary + tabs --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('bank.index', array_filter(['status' => $key, 'account' => $account])) }}"
                       class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ $status === $key ? 'bg-brand-700 text-white' : 'bg-white ring-1 ring-gray-200 text-gray-700 hover:bg-gray-50' }}">
                        {{ $label }} @if (isset($counts[$key]))<span class="ml-1 opacity-80">{{ $counts[$key] }}</span>@endif
                    </a>
                @endforeach
                @if ($accounts->count() > 1)
                    <form method="GET" class="ml-auto">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <select name="account" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm" aria-label="Bank account">
                            <option value="">All accounts</option>
                            @foreach ($accounts as $a)<option value="{{ $a }}" @selected($a === $account)>{{ $a }}</option>@endforeach
                        </select>
                    </form>
                @endif
            </div>
            @if ($counts['unmatched'])
                <div class="text-sm text-gray-600">To review: money in <strong class="tabular-nums text-money-700">₹{{ rupees($unmatchedIn) }}</strong> · money out <strong class="tabular-nums text-danger-700">₹{{ rupees($unmatchedOut) }}</strong></div>
            @endif

            {{-- Lines --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                @if ($lines->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-gray-500">
                        @if ($counts['unmatched'] + $counts['matched'] + $counts['ignored'] === 0)
                            No statements uploaded yet.
                        @else
                            No lines here. @if ($status === 'unmatched') All lines are matched or ignored. @endif
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[860px]">
                            <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-3 py-2.5 text-left font-semibold">Date</th>
                                    <th class="px-3 py-2.5 text-left font-semibold">Bank narration</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">Money in</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">Money out</th>
                                    <th class="px-3 py-2.5 text-left font-semibold">In your books</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($lines as $line)
                                    <tr class="align-top">
                                        <td class="px-3 py-2.5 whitespace-nowrap">{{ $line->txn_date->format('d M Y') }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="text-gray-900 max-w-[22rem] break-words">{{ $line->description ?: '-' }}</div>
                                            @if ($line->reference)<div class="text-xs font-mono text-gray-500">{{ $line->reference }}</div>@endif
                                        </td>
                                        <td class="{{ $num }} text-money-700">{{ (float) $line->credit > 0 ? rupees($line->credit) : '' }}</td>
                                        <td class="{{ $num }} text-danger-700">{{ (float) $line->debit > 0 ? rupees($line->debit) : '' }}</td>
                                        <td class="px-3 py-2.5 min-w-[18rem]">
                                            @if ($line->status === 'matched')
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-money-100 text-money-800 text-xs font-bold">Matched</span>
                                                    <span class="text-xs text-gray-700 truncate">
                                                        @if ($line->matched instanceof \App\Models\Payment)
                                                            Receipt {{ $line->matched->receipt_number }}
                                                        @elseif ($line->matched instanceof \App\Models\PurchasePayment)
                                                            Vendor payment
                                                        @elseif ($line->matched instanceof \App\Models\Expense)
                                                            Expense · {{ $line->matched->vendor_name ?: $line->matched->description }}
                                                        @else
                                                            (entry removed from the books)
                                                        @endif
                                                    </span>
                                                    <form method="POST" action="{{ route('bank.lines.unmatch', $line) }}" class="ml-auto">@csrf
                                                        <button class="text-xs text-gray-500 hover:text-gray-900 hover:underline">Undo</button>
                                                    </form>
                                                </div>
                                            @elseif ($line->status === 'ignored')
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs font-bold">Ignored</span>
                                                    <form method="POST" action="{{ route('bank.lines.unmatch', $line) }}" class="ml-auto">@csrf
                                                        <button class="text-xs text-gray-500 hover:text-gray-900 hover:underline">Undo</button>
                                                    </form>
                                                </div>
                                            @else
                                                @php $cands = $suggestions[$line->id] ?? collect(); @endphp
                                                @if ($cands->isNotEmpty())
                                                    <form method="POST" action="{{ route('bank.lines.match', $line) }}" class="flex items-center gap-2">
                                                        @csrf
                                                        <select name="candidate" class="flex-1 min-w-0 border-gray-300 rounded text-xs" aria-label="Match to">
                                                            @foreach ($cands as $c)
                                                                <option value="{{ $c['model']->getMorphClass() }}#{{ $c['model']->getKey() }}">{{ $c['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button class="px-2.5 py-1 rounded bg-money-600 hover:bg-money-700 text-white text-xs font-semibold">Match</button>
                                                    </form>
                                                @elseif ($line->isCredit())
                                                    <div class="text-xs text-gray-600">No receipt for this amount yet.</div>
                                                    @foreach ($invoiceHints[$line->id] ?? [] as $inv)
                                                        <a href="{{ route('invoices.show', ['invoice' => $inv, 'pay_amount' => $line->credit, 'pay_date' => $line->txn_date->toDateString(), 'pay_method' => 'neft', 'pay_ref' => $line->reference]) }}#record-payment"
                                                           class="mt-1 inline-flex items-center px-2 py-1 rounded bg-brand-50 text-brand-800 text-xs font-semibold hover:bg-brand-100">Record receipt on {{ $inv->invoice_number }} · {{ $inv->billedCustomer()?->name }}</a>
                                                    @endforeach
                                                    @if (empty($invoiceHints[$line->id]) || $invoiceHints[$line->id]->isEmpty())
                                                        <a href="{{ route('invoices.index', ['status' => 'outstanding']) }}" class="mt-1 inline-block text-xs text-brand-700 hover:underline font-semibold">Find the invoice and record the receipt</a>
                                                    @endif
                                                @else
                                                    <div class="text-xs text-gray-600">No payment or expense for this amount yet.</div>
                                                    <a href="{{ route('finance.expenses.create', ['amount' => $line->debit, 'entry_date' => $line->txn_date->toDateString(), 'description' => $line->description, 'reference' => $line->reference]) }}"
                                                       class="mt-1 inline-flex items-center px-2 py-1 rounded bg-brand-50 text-brand-800 text-xs font-semibold hover:bg-brand-100">+ Add as expense</a>
                                                @endif
                                                <form method="POST" action="{{ route('bank.lines.ignore', $line) }}" class="mt-1">@csrf
                                                    <button class="text-xs text-gray-500 hover:text-gray-900 hover:underline" title="For bank charges, transfers between your own accounts and cash withdrawals">Ignore line</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $lines->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
