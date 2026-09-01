<x-layouts.admin title="Users" subtitle="{{ $users->total() }} total · manage everyone on the platform">
    <x-slot:action>
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email" class="w-56 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600" aria-label="Search name or email">
            @if (request('search'))
                <a href="{{ route('admin.users') }}" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Clear</a>
            @endif
        </form>
    </x-slot:action>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold">User</th>
                    <th class="px-5 py-3 text-right font-semibold">Companies</th>
                    <th class="px-5 py-3 text-right font-semibold">Customers</th>
                    <th class="px-5 py-3 text-right font-semibold">Invoices</th>
                    <th class="px-5 py-3 text-right font-semibold">Revenue</th>
                    <th class="px-5 py-3 text-left font-semibold">Joined</th>
                    <th class="px-5 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $u)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-brand-500 text-white font-bold flex items-center justify-center text-xs flex-shrink-0">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-900 flex items-center gap-1.5 flex-wrap">
                                        {{ $u->name }}
                                        @if ($u->isSuperAdmin())<span class="text-[9px] px-1.5 py-0.5 rounded bg-danger-100 text-danger-700 font-bold uppercase tracking-wider">Super</span>@endif
                                        @if ($u->email_verified_at)<span class="text-[9px] px-1.5 py-0.5 rounded bg-money-100 text-money-700 font-bold">✓ Verified</span>@endif
                                    </div>
                                    <div class="text-xs text-gray-500 truncate">{{ $u->email }}</div>
                                    @if ($u->phone)
                                        <div class="text-xs text-gray-400 font-mono">{{ $u->phone }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-700">{{ $u->companies_count }}</td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-700">{{ $u->customers_count }}</td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-700">{{ $u->invoices_count }}</td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums font-semibold text-gray-900">₹{{ number_format((float) $u->revenue) }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $u->created_at->format('d M Y') }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.users.show', $u) }}" class="text-brand-600 hover:underline text-sm font-medium">View</a>
                                @if (! $u->isSuperAdmin() && $u->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.impersonate', $u) }}" class="inline"
                                          onsubmit="return confirm('Log in as {{ $u->name }}? You can return to your own account anytime using the banner at the top.')">
                                        @csrf
                                        <button class="px-2.5 py-1 bg-accent-100 hover:bg-accent-200 text-accent-800 text-xs font-semibold rounded transition" title="Log in as this user">Impersonate</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">No users match.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.admin>
