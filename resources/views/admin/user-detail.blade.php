<x-layouts.admin title="{{ $user->name }}" subtitle="{{ $user->email }} · Joined {{ $user->created_at->format('d M Y') }}">
    <x-slot:action>
        @if (! $user->isSuperAdmin() && $user->id !== auth()->id())
            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}"
                  onsubmit="return confirm('Log in as {{ $user->name }}? You can return to your own account anytime.')">
                @csrf
                <button class="px-4 py-2 bg-accent-500 hover:bg-accent-600 text-white text-sm font-semibold rounded shadow-sm">Impersonate</button>
            </form>
        @endif
        <a href="{{ route('admin.users') }}" class="text-sm text-gray-500 hover:text-gray-900">← All users</a>
    </x-slot:action>

    <div class="space-y-6">

        <div class="flex items-center gap-2">
            @if ($user->isSuperAdmin())
                <span class="px-2.5 py-1 rounded bg-danger-100 text-danger-700 text-xs font-bold uppercase tracking-wider">Super admin</span>
            @endif
            @if ($user->email_verified_at)
                <span class="px-2.5 py-1 rounded bg-money-100 text-money-700 text-xs font-bold uppercase tracking-wider">Email verified</span>
            @else
                <span class="px-2.5 py-1 rounded bg-accent-100 text-accent-700 text-xs font-bold uppercase tracking-wider">Email unverified</span>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @php
                $tiles = [
                    ['label' => 'Companies', 'value' => $totals['companies'], 'color' => 'brand'],
                    ['label' => 'Customers', 'value' => $totals['customers'], 'color' => 'money'],
                    ['label' => 'Invoices',  'value' => $totals['invoices'], 'color' => 'accent'],
                    ['label' => 'Revenue',   'value' => '₹' . number_format($totals['revenue']), 'color' => 'brand'],
                ];
                $bgColors = ['brand' => 'bg-brand-50 text-brand-700', 'money' => 'bg-money-50 text-money-700', 'accent' => 'bg-accent-50 text-accent-700', 'danger' => 'bg-danger-50 text-danger-700'];
            @endphp
            @foreach ($tiles as $t)
                <div class="p-5 bg-white rounded-xl border border-gray-200">
                    <div class="text-xs font-bold uppercase tracking-wider {{ $bgColors[$t['color']] }} inline-block px-2 py-0.5 rounded">{{ $t['label'] }}</div>
                    <div class="mt-2 font-display text-2xl font-extrabold text-gray-900 tabular-nums">{{ $t['value'] }}</div>
                </div>
            @endforeach
        </div>

        @php
            $phones = $user->companies->filter(fn ($c) => filled($c->phone));
            $waDigits = preg_replace('/\D/', '', (string) $user->phone);
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200"><h3 class="font-display font-bold text-gray-900">Phone numbers</h3></div>
            <ul class="divide-y divide-gray-100">
                {{-- The personal mobile captured at signup (users.phone) - the
                     number the team actually calls/WhatsApps for onboarding.
                     Listed first; company phones (from settings) follow. --}}
                <li class="px-5 py-3 flex items-center justify-between gap-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500">Signup mobile</span>
                        @if ($user->phone && $user->phone_verified_at)
                            <span class="px-1.5 py-0.5 rounded bg-money-100 text-money-700 text-[10px] font-bold uppercase tracking-wider">Verified</span>
                        @elseif ($user->phone)
                            <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-[10px] font-bold uppercase tracking-wider">Unverified</span>
                        @endif
                    </div>
                    @if ($user->phone)
                        <div class="flex items-center gap-3">
                            <a href="tel:{{ $user->phone }}" class="font-mono text-gray-900 hover:text-brand-600">{{ $user->phone }}</a>
                            <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener" class="text-money-600 hover:text-money-700 text-xs font-semibold">WhatsApp →</a>
                        </div>
                    @else
                        <span class="text-gray-400">Not provided (pre-mandatory signup)</span>
                    @endif
                </li>
                @foreach ($phones as $co)
                    <li class="px-5 py-3 flex items-center justify-between gap-4 text-sm">
                        <div class="text-gray-500">{{ $co->name }} <span class="text-gray-400 text-xs">(company)</span></div>
                        <a href="tel:{{ $co->phone }}" class="font-mono text-gray-900 hover:text-brand-600">{{ $co->phone }}</a>
                    </li>
                @endforeach
            </ul>
        </div>

        @if (! $user->isSuperAdmin() && $user->id !== auth()->id())
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="font-display font-bold text-gray-900">Reset password</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Set a new password for this user. Share it securely - they should change it on next login.</p>
                </div>
                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="p-5 grid gap-3 sm:grid-cols-[1fr_1fr_auto] items-start"
                      onsubmit="return confirm('Reset password for {{ $user->name }}?')">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">New password</label>
                        <input type="password" name="password" required minlength="8" autocomplete="new-password"
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-brand-600 focus:ring-brand-600">
                        @error('password')<div class="text-xs text-danger-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Confirm</label>
                        <input type="password" name="password_confirmation" required minlength="8"
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    <button type="submit" class="sm:mt-6 px-4 py-2 bg-danger-600 hover:bg-danger-700 text-white text-sm font-semibold rounded shadow-sm">Reset</button>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200"><h3 class="font-display font-bold text-gray-900">Companies ({{ $user->companies->count() }})</h3></div>
            @if ($user->companies->isEmpty())
                <div class="px-5 py-10 text-center text-gray-400">No companies.</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($user->companies as $co)
                        <li class="px-5 py-3 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 flex items-center gap-2">
                                    {{ $co->name }}
                                    @if ($co->id === $user->active_company_id)<span class="text-[9px] px-1.5 py-0.5 rounded bg-brand-100 text-brand-700 font-bold uppercase tracking-wider">Active</span>@endif
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
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-display font-bold text-gray-900">Recent invoices</h3>
                <a href="{{ route('admin.invoices') }}" class="text-xs font-semibold text-brand-600 hover:underline">View all →</a>
            </div>
            @if ($recentInvoices->isEmpty())
                <div class="px-5 py-10 text-center text-gray-400">No invoices yet.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
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
                                <td class="px-5 py-2"><span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700">{{ ucfirst(str_replace('_', ' ', $inv->status)) }}</span></td>
                                <td class="px-5 py-2 text-right font-mono tabular-nums">₹{{ inr($inv->grand_total) }}</td>
                                <td class="px-5 py-2 text-xs text-gray-500">{{ $inv->invoice_date?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-layouts.admin>
