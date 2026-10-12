<x-app-layout title="Bank reconciliation">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Bank reconciliation</h1>
                <p class="text-sm text-gray-500 mt-1">Match bank statement lines with payments received, payments made and expenses.</p>
            </div>
            @if ($counts['unmatched'])
                <form method="POST" action="{{ route('bank.auto-match') }}" class="self-start sm:self-auto">
                    @csrf
                    <button class="inline-flex items-center justify-center h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm whitespace-nowrap">Match automatically</button>
                </form>
            @endif
        </div>
    </x-slot>

    @php
        // Matching is to the paisa, so this screen shows paise: ₹17.70, not ₹18.
        $money = fn ($v) => '₹' . inr((float) $v);
        $tabs = ['unmatched' => 'To review', 'matched' => 'Matched', 'ignored' => 'Ignored', 'all' => 'All'];
        // Date | narration | amount | in your books. One row per line from 1024px,
        // a stacked card below that.
        $cols = 'lg:grid-cols-[6.5rem_minmax(0,1fr)_7.5rem_minmax(0,1.15fr)]';
        $btnSecondary = 'inline-flex items-center justify-center h-9 px-3 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md whitespace-nowrap';
        $isCharge = fn ($line) => (bool) preg_match('/\b(CHRG|CHGS|CHARGES?|FEES?|COMMISSION)\b|GST\s*ON\s*(CHRG|CHGS|CHARGES?)/i', (string) $line->description);
        $expenseErrors = $errors->getBag('expense');
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Upload --}}
            <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 sm:p-5" x-data="{ name: '' }">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Upload bank statement</h2>
                        <ol class="mt-2 text-sm text-gray-600 list-decimal pl-5 space-y-1">
                            <li>In net banking, open the account statement and pick the dates.</li>
                            <li>Download it as <strong>Excel (.xlsx)</strong> or <strong>CSV</strong>. Any bank works.</li>
                            <li>Upload it here. Lines already uploaded are skipped.</li>
                        </ol>
                        @if ($statements->isNotEmpty())
                            <div class="mt-4">
                                <h3 class="text-xs font-semibold text-gray-600">Recent statements</h3>
                                <ul class="mt-1 divide-y divide-gray-100 text-sm">
                                    @foreach ($statements->take(4) as $st)
                                        <li class="flex items-center justify-between gap-3 py-1.5">
                                            <span class="min-w-0 text-gray-700">
                                                <span class="font-medium text-gray-900">{{ $st->account_name }}</span>
                                                <span class="text-gray-500">· {{ $st->period_from?->format('d M') }} to {{ $st->period_to?->format('d M Y') }} · {{ $st->lines_count }} {{ Str::plural('line', $st->lines_count) }}</span>
                                            </span>
                                            <x-confirm-form :action="route('bank.statements.destroy', $st)" method="DELETE" title="Remove this statement?"
                                                            message="Its lines and matches are deleted. Receipts, payments and expenses are not affected." confirmLabel="Remove statement" confirmClass="bg-danger-600 hover:bg-danger-700" tone="danger">
                                                <button type="button" class="inline-flex items-center justify-center min-h-[36px] px-3 rounded-md text-sm font-medium text-danger-700 ring-1 ring-danger-200 hover:bg-danger-50"
                                                        aria-label="Remove the {{ $st->account_name }} statement for {{ $st->period_from?->format('d M') }} to {{ $st->period_to?->format('d M Y') }}">Remove</button>
                                            </x-confirm-form>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('bank.upload') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div>
                            <x-input-label for="account_name" value="Bank account *" />
                            <input id="account_name" name="account_name" list="bank-accounts" required maxlength="80" value="{{ old('account_name', $account ?: ($accounts->first() ?? '')) }}" placeholder="e.g. HDFC Current A/c"
                                   @error('account_name') aria-invalid="true" aria-describedby="account_name-error" @enderror
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                            <datalist id="bank-accounts">@foreach ($accounts as $a)<option value="{{ $a }}">@endforeach</datalist>
                            <x-input-error :messages="$errors->get('account_name')" for="account_name" class="mt-1" />
                        </div>
                        <div>
                            <label class="block border-2 border-dashed border-gray-300 hover:border-brand-500 rounded-xl p-4 text-center cursor-pointer transition">
                                <input type="file" name="file" accept=".xlsx,.csv,.txt" required class="sr-only" @change="name = $event.target.files[0]?.name || ''"
                                       @error('file') aria-invalid="true" aria-describedby="file-error" @enderror>
                                <span class="block text-sm font-semibold text-gray-800" x-text="name || 'Choose statement file'"></span>
                                <span class="block text-xs text-gray-500">Excel (.xlsx) or CSV</span>
                            </label>
                            <x-input-error :messages="$errors->get('file')" for="file" class="mt-1" />
                        </div>
                        <div class="flex justify-end">
                            <button class="inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md">Upload and match</button>
                        </div>
                    </form>
                </div>
            </section>

            {{-- Totals: what is still to review and what is already matched. --}}
            @if ($counts['unmatched'] + $counts['matched'] + $counts['ignored'] > 0)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="rounded-xl bg-white ring-1 {{ $counts['unmatched'] ? 'ring-accent-300' : 'ring-gray-200' }} px-4 py-3">
                        <div class="text-xs font-semibold text-gray-600">To review · {{ $counts['unmatched'] }} {{ Str::plural('line', $counts['unmatched']) }}</div>
                        <div class="mt-1 text-sm tabular-nums">
                            <span class="text-gray-500">In</span> <strong class="font-mono text-money-700">{{ $money($unmatchedIn) }}</strong>
                            <span class="text-gray-500 ml-2">Out</span> <strong class="font-mono text-gray-900">{{ $money($unmatchedOut) }}</strong>
                        </div>
                    </div>
                    <div class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3">
                        <div class="text-xs font-semibold text-gray-600">Matched · {{ $counts['matched'] }} {{ Str::plural('line', $counts['matched']) }}</div>
                        <div class="mt-1 text-sm tabular-nums">
                            <span class="text-gray-500">In</span> <strong class="font-mono text-money-700">{{ $money($matchedIn) }}</strong>
                            <span class="text-gray-500 ml-2">Out</span> <strong class="font-mono text-gray-900">{{ $money($matchedOut) }}</strong>
                        </div>
                    </div>
                    <div class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3">
                        <div class="text-xs font-semibold text-gray-600">Ignored</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $counts['ignored'] }} {{ Str::plural('line', $counts['ignored']) }}</div>
                    </div>
                </div>
            @endif

            {{-- Tabs --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('bank.index', array_filter(['status' => $key, 'account' => $account])) }}"
                       @if ($status === $key) aria-current="page" @endif
                       class="inline-flex items-center min-h-[36px] px-3 rounded-lg text-sm font-semibold {{ $status === $key ? 'bg-brand-700 text-white' : 'bg-white ring-1 ring-gray-200 text-gray-700 hover:bg-gray-50' }}">
                        {{ $label }} @if (isset($counts[$key]))<span class="ml-1 opacity-80 tabular-nums">{{ $counts[$key] }}</span>@endif
                    </a>
                @endforeach
                @if ($accounts->count() > 1)
                    <form method="GET" class="w-full sm:w-auto sm:ml-auto">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <select name="account" onchange="this.form.submit()" class="w-full sm:w-auto h-10 border-gray-300 rounded-md shadow-sm text-sm" aria-label="Bank account">
                            <option value="">All accounts</option>
                            @foreach ($accounts as $a)<option value="{{ $a }}" @selected($a === $account)>{{ $a }}</option>@endforeach
                        </select>
                    </form>
                @endif
            </div>

            {{-- Lines --}}
            <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                @if ($lines->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-gray-500">
                        @if ($counts['unmatched'] + $counts['matched'] + $counts['ignored'] === 0)
                            No statements uploaded yet.
                        @else
                            No lines here. @if ($status === 'unmatched') All lines are matched or ignored. @endif
                        @endif
                    </div>
                @else
                    <div class="hidden lg:grid {{ $cols }} gap-x-4 px-4 py-2.5 bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-600" aria-hidden="true">
                        <div>Date</div>
                        <div>Bank narration</div>
                        <div class="text-right">Amount</div>
                        <div>In your books</div>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($lines as $line)
                            @php
                                $amount = ($line->isCredit() ? '+' : '−') . $money($line->amount());
                                $amountTone = $line->isCredit() ? 'text-money-700' : 'text-gray-900';
                                $direction = $line->isCredit() ? 'Money in' : 'Money out';
                            @endphp
                            <li class="px-4 py-3 lg:grid {{ $cols }} lg:gap-x-4 lg:items-start">
                                <div class="flex items-baseline justify-between gap-3 lg:block">
                                    <div class="text-sm text-gray-700 whitespace-nowrap">{{ $line->txn_date->format('d M Y') }}</div>
                                    <div class="lg:hidden font-mono font-semibold tabular-nums whitespace-nowrap {{ $amountTone }}"><span class="sr-only">{{ $direction }} </span>{{ $amount }}</div>
                                </div>
                                <div class="mt-1 lg:mt-0 min-w-0">
                                    <div class="text-sm text-gray-900 break-words">{{ $line->description ?: '–' }}</div>
                                    @if ($line->reference)<div class="text-xs font-mono text-gray-500 break-all">{{ $line->reference }}</div>@endif
                                </div>
                                <div class="hidden lg:block text-right">
                                    <div class="font-mono font-semibold tabular-nums whitespace-nowrap {{ $amountTone }}"><span class="sr-only">{{ $direction }} </span>{{ $amount }}</div>
                                    <div class="text-xs text-gray-500">{{ $line->isCredit() ? 'In' : 'Out' }}</div>
                                </div>

                                <div class="mt-3 lg:mt-0 min-w-0">
                                    @if ($line->status === 'matched')
                                        @php $m = \App\Services\BankReconciler::describe($line->matched); @endphp
                                        <div class="flex items-start gap-3">
                                            <div class="min-w-0 flex-1">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset bg-money-50 text-money-800 ring-money-200">Matched</span>
                                                @if ($m)
                                                    <div class="mt-1 text-sm text-gray-900 break-words">
                                                        {{ $m['kind'] }}@if ($m['party']) · {{ $m['party'] }}@endif
                                                    </div>
                                                    <div class="text-xs text-gray-500 break-words">
                                                        @if ($m['document'])
                                                            @if ($m['url'])<a href="{{ $m['url'] }}" class="text-brand-700 hover:underline">{{ $m['document'] }}</a>@else{{ $m['document'] }}@endif ·
                                                        @endif
                                                        {{ $m['date'] }} · {{ $money($m['amount']) }}
                                                    </div>
                                                @else
                                                    <div class="mt-1 text-sm text-gray-500">The entry was removed from the books.</div>
                                                @endif
                                            </div>
                                            <form method="POST" action="{{ route('bank.lines.unmatch', $line) }}" class="shrink-0">@csrf
                                                <button class="{{ $btnSecondary }}">Undo</button>
                                            </form>
                                        </div>
                                    @elseif ($line->status === 'ignored')
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset bg-gray-100 text-gray-700 ring-gray-200">Ignored</span>
                                            <form method="POST" action="{{ route('bank.lines.unmatch', $line) }}" class="shrink-0">@csrf
                                                <button class="{{ $btnSecondary }}">Undo</button>
                                            </form>
                                        </div>
                                    @else
                                        @php $cands = $suggestions[$line->id] ?? collect(); @endphp
                                        @if ($cands->isNotEmpty())
                                            {{-- Each possible match in full (who, which document, date,
                                                 amount, reference), so two payments to one vendor can be told apart. --}}
                                            <form method="POST" action="{{ route('bank.lines.match', $line) }}">
                                                @csrf
                                                <fieldset>
                                                    <legend class="text-xs font-semibold text-gray-600">{{ $cands->count() === 1 ? 'Found in your books' : 'Which one is it?' }}</legend>
                                                    <div class="mt-1 space-y-1">
                                                        @foreach ($cands as $i => $c)
                                                            @php $d = \App\Services\BankReconciler::describe($c['model']); @endphp
                                                            <label class="flex items-start gap-2 rounded-md px-2 py-1.5 -mx-2 hover:bg-gray-50 cursor-pointer">
                                                                <input type="radio" name="candidate" value="{{ $c['model']->getMorphClass() }}#{{ $c['model']->getKey() }}" @checked($i === 0) class="mt-1 border-gray-300 text-brand-700 focus:ring-brand-600">
                                                                <span class="min-w-0 text-sm">
                                                                    <span class="block text-gray-900 break-words">{{ $d['kind'] }}@if ($d['party']) · {{ $d['party'] }}@endif</span>
                                                                    <span class="block text-xs text-gray-500 break-words">{{ collect([$d['document'], $d['date'], $money($d['amount']), $d['ref'] ? 'Ref ' . $d['ref'] : null])->filter()->implode(' · ') }}</span>
                                                                </span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </fieldset>
                                                <button class="mt-2 inline-flex items-center justify-center h-9 px-4 bg-white ring-1 ring-brand-300 text-brand-800 hover:bg-brand-50 text-sm font-semibold rounded-md">Match</button>
                                            </form>
                                        @elseif ($line->isCredit())
                                            <div class="text-sm text-gray-600">No receipt for this amount yet.</div>
                                            @foreach ($invoiceHints[$line->id] ?? [] as $inv)
                                                <a href="{{ route('invoices.show', ['invoice' => $inv, 'pay_amount' => $line->credit, 'pay_date' => $line->txn_date->toDateString(), 'pay_method' => 'neft', 'pay_ref' => $line->reference]) }}#record-payment"
                                                   class="mt-1 inline-flex items-center min-h-[36px] px-3 rounded-md bg-brand-50 text-brand-800 text-sm font-semibold hover:bg-brand-100">Record receipt on {{ $inv->invoice_number }} · {{ $inv->billedCustomer()?->name }}</a>
                                            @endforeach
                                            @if (empty($invoiceHints[$line->id]) || $invoiceHints[$line->id]->isEmpty())
                                                <a href="{{ route('invoices.index', ['status' => 'outstanding']) }}" class="mt-1 inline-flex items-center min-h-[36px] text-sm text-brand-700 hover:underline font-semibold">Find the invoice and record the receipt</a>
                                            @endif
                                        @else
                                            @php
                                                $mine = (string) old('line_id') === (string) $line->id;
                                                $defaultCategory = $isCharge($line) && $bankChargeCategory ? $bankChargeCategory : 'misc';
                                            @endphp
                                            <div class="text-sm text-gray-600">No payment or expense for this amount yet.</div>
                                            {{-- Quick entry: saved for this date and amount, matched to this
                                                 line, and the page stays here. --}}
                                            <div x-data="{ open: {{ $mine ? 'true' : 'false' }} }" class="mt-1">
                                                <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" aria-controls="add-expense-{{ $line->id }}"
                                                        class="inline-flex items-center min-h-[36px] px-3 rounded-md bg-brand-50 text-brand-800 text-sm font-semibold hover:bg-brand-100">+ Add as expense</button>
                                                <form id="add-expense-{{ $line->id }}" x-show="open" x-cloak method="POST" action="{{ route('bank.lines.match', $line) }}"
                                                      class="mt-2 p-3 rounded-lg bg-gray-50 ring-1 ring-gray-200 space-y-3">
                                                    @csrf
                                                    <input type="hidden" name="candidate" value="new-expense">
                                                    <input type="hidden" name="line_id" value="{{ $line->id }}">
                                                    <p class="text-xs text-gray-600">{{ $money($line->debit) }} on {{ $line->txn_date->format('d M Y') }}, paid from the bank.</p>
                                                    <div>
                                                        <label for="exp-cat-{{ $line->id }}" class="block text-xs font-medium text-gray-700">Category *</label>
                                                        <select id="exp-cat-{{ $line->id }}" name="category" required class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm"
                                                                @if ($mine && $expenseErrors->has('category')) aria-invalid="true" aria-describedby="exp-cat-{{ $line->id }}-error" @endif>
                                                            @foreach ($categories as $key => $cat)
                                                                <option value="{{ $key }}" @selected(($mine ? old('category', $defaultCategory) : $defaultCategory) === $key)>{{ $cat['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                        @if ($mine)<x-input-error :messages="$expenseErrors->get('category')" :for="'exp-cat-' . $line->id" class="mt-1" />@endif
                                                    </div>
                                                    <div>
                                                        <label for="exp-desc-{{ $line->id }}" class="block text-xs font-medium text-gray-700">Description *</label>
                                                        <input id="exp-desc-{{ $line->id }}" name="description" required maxlength="255" value="{{ $mine ? old('description') : mb_substr((string) $line->description, 0, 255) }}"
                                                               @if ($mine && $expenseErrors->has('description')) aria-invalid="true" aria-describedby="exp-desc-{{ $line->id }}-error" @endif
                                                               class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm">
                                                        @if ($mine)<x-input-error :messages="$expenseErrors->get('description')" :for="'exp-desc-' . $line->id" class="mt-1" />@endif
                                                    </div>
                                                    <div>
                                                        <label for="exp-vendor-{{ $line->id }}" class="block text-xs font-medium text-gray-700">Paid to</label>
                                                        <input id="exp-vendor-{{ $line->id }}" name="vendor_name" maxlength="120" value="{{ $mine ? old('vendor_name') : '' }}" placeholder="Optional"
                                                               class="mt-1 block w-full h-10 border-gray-300 rounded-md shadow-sm text-sm">
                                                        @if ($mine)<x-input-error :messages="$expenseErrors->get('vendor_name')" :for="'exp-vendor-' . $line->id" class="mt-1" />@endif
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <button class="inline-flex items-center justify-center h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md">Save and match</button>
                                                        <button type="button" @click="open = false" class="inline-flex items-center justify-center h-10 px-4 bg-white ring-1 ring-gray-300 text-gray-800 hover:bg-gray-50 text-sm font-semibold rounded-md">Cancel</button>
                                                    </div>
                                                    <p class="text-xs text-gray-500">GST to claim on it?
                                                        <a href="{{ route('finance.expenses.create', ['amount' => $line->debit, 'entry_date' => $line->txn_date->toDateString(), 'description' => $line->description, 'reference' => $line->reference, 'category' => $defaultCategory]) }}" class="font-semibold text-brand-700 hover:underline">Use the full expense form</a>,
                                                        then match it here.</p>
                                                </form>
                                            </div>
                                        @endif
                                        <form method="POST" action="{{ route('bank.lines.ignore', $line) }}" class="mt-1">@csrf
                                            <button class="inline-flex items-center min-h-[36px] text-sm text-gray-600 hover:text-gray-900 hover:underline" title="For bank charges you do not book, transfers between your own accounts and cash withdrawals">Ignore line</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="p-4 border-t border-gray-100">{{ $lines->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
