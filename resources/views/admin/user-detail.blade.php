<x-layouts.admin title="{{ $user->name }}" subtitle="{{ $user->email }} · Joined {{ $user->created_at->format('d M Y') }}">
    <x-slot:action>
        @if (! $user->isSuperAdmin() && $user->id !== auth()->id())
            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}"
                  onsubmit="return confirm(@js('Log in as ' . $user->name . '? You can return to your own account anytime.'))">
                @csrf
                <button class="inline-flex items-center min-h-[40px] px-4 py-2 bg-accent-500 hover:bg-accent-600 text-white text-sm font-semibold rounded shadow-sm">Impersonate</button>
            </form>
        @endif
        <a href="{{ route('admin.users') }}" class="inline-flex items-center min-h-[40px] px-1 text-sm text-gray-500 hover:text-gray-900">← All users</a>
    </x-slot:action>

    <div class="space-y-6">

        <div class="flex flex-wrap items-center gap-2">
            @if ($user->isSuperAdmin())
                <span class="px-2.5 py-1 rounded bg-danger-100 text-danger-700 text-xs font-semibold">Super admin</span>
            @endif
            @if ($user->email_verified_at)
                <span class="px-2.5 py-1 rounded bg-money-100 text-money-700 text-xs font-semibold">Email verified</span>
            @else
                <span class="px-2.5 py-1 rounded bg-accent-100 text-accent-700 text-xs font-semibold">Email unverified</span>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
            @php
                $tiles = [
                    ['label' => 'Companies', 'value' => $totals['companies'], 'color' => 'brand'],
                    ['label' => 'Customers', 'value' => $totals['customers'], 'color' => 'money'],
                    ['label' => 'Invoices',  'value' => $totals['invoices'], 'color' => 'accent'],
                    ['label' => 'Revenue',   'value' => '₹' . rupees($totals['revenue']), 'color' => 'brand'],
                ];
                $bgColors = ['brand' => 'bg-brand-50 text-brand-700', 'money' => 'bg-money-50 text-money-700', 'accent' => 'bg-accent-50 text-accent-700', 'danger' => 'bg-danger-50 text-danger-700'];
            @endphp
            @foreach ($tiles as $t)
                <div class="p-4 sm:p-5 bg-white rounded-xl border border-gray-200 min-w-0">
                    <div class="text-xs font-semibold {{ $bgColors[$t['color']] ?? 'bg-gray-100 text-gray-700' }} inline-block px-2 py-0.5 rounded">{{ $t['label'] }}</div>
                    <div class="mt-2 font-display text-xl sm:text-2xl font-extrabold text-gray-900 tabular-nums break-all">{{ $t['value'] }}</div>
                </div>
            @endforeach
        </div>

        @php
            $phones = $user->companies->filter(fn ($c) => filled($c->phone));
            $waDigits = preg_replace('/\D/', '', (string) $user->phone);
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 sm:px-5 py-4 border-b border-gray-200"><h2 class="font-display font-bold text-gray-900">Phone numbers</h2></div>
            <ul class="divide-y divide-gray-100">
                {{-- The personal mobile captured at signup (users.phone): the
                     number the team actually calls/WhatsApps for onboarding.
                     Listed first; company phones (from settings) follow. --}}
                <li class="px-4 sm:px-5 py-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500">Signup mobile</span>
                        @if ($user->phone && $user->phone_verified_at)
                            <span class="px-1.5 py-0.5 rounded bg-money-100 text-money-700 text-xs font-semibold">Verified</span>
                        @elseif ($user->phone)
                            <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-xs font-semibold">Unverified</span>
                        @endif
                    </div>
                    @if ($user->phone)
                        <div class="flex items-center gap-4">
                            <a href="tel:{{ $user->phone }}" class="inline-flex items-center min-h-[40px] font-mono text-gray-900 hover:text-brand-600">{{ $user->phone }}</a>
                            <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener" class="inline-flex items-center min-h-[40px] text-money-600 hover:text-money-700 text-xs font-semibold">WhatsApp →</a>
                        </div>
                    @else
                        <span class="text-gray-500">Not given (signed up before mobile was required)</span>
                    @endif
                </li>
                @foreach ($phones as $co)
                    <li class="px-4 sm:px-5 py-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
                        <div class="text-gray-500 min-w-0 break-words">{{ $co->name }} <span class="text-gray-500 text-xs">(company)</span></div>
                        <a href="tel:{{ $co->phone }}" class="inline-flex items-center min-h-[40px] font-mono text-gray-900 hover:text-brand-600">{{ $co->phone }}</a>
                    </li>
                @endforeach
            </ul>
        </div>

        @if (! $user->isSuperAdmin() && $user->id !== auth()->id())
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 sm:px-5 py-4 border-b border-gray-200">
                    <h2 class="font-display font-bold text-gray-900">Reset password</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Set a new password for this user. Share it privately and ask them to change it after they log in.</p>
                </div>
                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="p-4 sm:p-5 grid gap-3 sm:grid-cols-[1fr_1fr_auto] items-start"
                      onsubmit="return confirm(@js('Reset password for ' . $user->name . '?'))">
                    @csrf
                    <div>
                        <label for="admin-user-password" class="block text-xs font-semibold text-gray-600 mb-1">New password</label>
                        <input id="admin-user-password" type="password" name="password" required minlength="8" autocomplete="new-password"
                               class="min-h-[40px] w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-brand-600 focus:ring-brand-600">
                        @error('password')<div class="text-xs text-danger-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="admin-user-password-confirmation" class="block text-xs font-semibold text-gray-600 mb-1">Confirm password</label>
                        <input id="admin-user-password-confirmation" type="password" name="password_confirmation" required minlength="8"
                               class="min-h-[40px] w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    <button type="submit" class="sm:mt-[1.4rem] min-h-[40px] px-4 py-2 bg-danger-600 hover:bg-danger-700 text-white text-sm font-semibold rounded shadow-sm">Reset password</button>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 sm:px-5 py-4 border-b border-gray-200"><h2 class="font-display font-bold text-gray-900">Companies ({{ $user->companies->count() }})</h2></div>
            @if ($user->companies->isEmpty())
                <div class="px-5 py-10 text-center text-gray-500">No companies.</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($user->companies as $co)
                        <li class="px-4 sm:px-5 py-3 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 flex flex-wrap items-center gap-2">
                                    <span class="break-words">{{ $co->name }}</span>
                                    @if ($co->id === $user->active_company_id)<span class="text-[10px] px-1.5 py-0.5 rounded bg-brand-100 text-brand-700 font-semibold">Active</span>@endif
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    @if ($co->gstin)<span class="font-mono">GSTIN {{ $co->gstin }}</span> · @endif
                                    Prefix <span class="font-mono">{{ $co->invoice_prefix }}</span> ·
                                    Counter at {{ $co->invoice_counter }}
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 whitespace-nowrap text-right">
                                <div>{{ $co->customers_count }} customer{{ $co->customers_count === 1 ? '' : 's' }}</div>
                                <div>{{ $co->invoices_count }} invoice{{ $co->invoices_count === 1 ? '' : 's' }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 sm:px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-3">
                <h2 class="font-display font-bold text-gray-900">Recent invoices</h2>
                <a href="{{ route('admin.invoices') }}" class="inline-flex items-center min-h-[40px] sm:min-h-0 text-xs font-semibold text-brand-600 hover:underline whitespace-nowrap">View all →</a>
            </div>
            @if ($recentInvoices->isEmpty())
                <div class="px-5 py-10 text-center text-gray-500">No invoices yet.</div>
            @else
                {{-- Phones: one card per invoice --}}
                <ul class="sm:hidden divide-y divide-gray-100">
                    @foreach ($recentInvoices as $inv)
                        <li class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-mono text-sm font-semibold text-gray-900 break-all">{{ $inv->displayNumber() }}</div>
                                    <div class="text-xs text-gray-500">{{ $inv->invoice_date?->format('d M Y') }}</div>
                                </div>
                                <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700">{{ $inv->statusLabel() }}</span>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500">Customer</dt>
                                    <dd class="text-gray-900 break-words">{{ $inv->customer?->name ?? '-' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500">Total</dt>
                                    <dd class="font-mono tabular-nums text-gray-900 break-all">₹{{ rupees($inv->grand_total) }}</dd>
                                </div>
                                <div class="col-span-2 min-w-0">
                                    <dt class="text-xs text-gray-500">Company</dt>
                                    <dd class="text-gray-600 break-words">{{ $inv->company?->name ?? '-' }}</dd>
                                </div>
                            </dl>
                        </li>
                    @endforeach
                </ul>

                {{-- Tablets and up: the full table --}}
                <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-2 text-left font-semibold">Number</th>
                            <th class="px-5 py-2 text-left font-semibold">Customer</th>
                            <th class="px-5 py-2 text-left font-semibold">Company</th>
                            <th class="px-5 py-2 text-left font-semibold">Status</th>
                            <th class="px-5 py-2 text-right font-semibold">Total</th>
                            <th class="px-5 py-2 text-left font-semibold">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($recentInvoices as $inv)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-2 font-mono text-xs">{{ $inv->displayNumber() }}</td>
                                <td class="px-5 py-2">{{ $inv->customer?->name ?? '-' }}</td>
                                <td class="px-5 py-2 text-xs text-gray-600">{{ $inv->company?->name ?? '-' }}</td>
                                <td class="px-5 py-2"><span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700">{{ $inv->statusLabel() }}</span></td>
                                <td class="px-5 py-2 text-right font-mono tabular-nums">₹{{ rupees($inv->grand_total) }}</td>
                                <td class="px-5 py-2 text-xs text-gray-500">{{ $inv->invoice_date?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
