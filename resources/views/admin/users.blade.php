<x-layouts.admin title="Users" subtitle="{{ $users->total() }} total · everyone on the platform">
    <x-slot:action>
        <form method="GET" class="flex w-full md:w-auto gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email" class="min-h-[40px] min-w-0 flex-1 md:flex-none md:w-56 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600" aria-label="Search name or email">
            @if (request('search'))
                <a href="{{ route('admin.users') }}" class="inline-flex items-center min-h-[40px] px-3 text-sm text-gray-600 hover:text-gray-900">Clear</a>
            @endif
        </form>
        {{-- Download, narrowed by the search above when there is one. --}}
        <div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open" :aria-expanded="open"
                    class="inline-flex items-center gap-1.5 min-h-[40px] px-3 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Download
            </button>
            <div x-show="open" x-cloak x-transition
                 class="absolute right-0 z-20 mt-1 w-56 rounded-lg bg-white shadow-lg ring-1 ring-black/10 py-1 text-sm">
                <div class="px-3 pt-1.5 pb-1 text-xs font-semibold text-gray-500">Verified users</div>
                <a href="{{ route('admin.users.export', array_filter(['format' => 'xlsx', 'search' => request('search')])) }}" class="block px-3 py-2 text-gray-700 hover:bg-gray-50">Excel (.xlsx)</a>
                <a href="{{ route('admin.users.export', array_filter(['format' => 'csv', 'search' => request('search')])) }}" class="block px-3 py-2 text-gray-700 hover:bg-gray-50">CSV</a>
                <div class="my-1 border-t border-gray-100"></div>
                <div class="px-3 pt-1.5 pb-1 text-xs font-semibold text-gray-500">All users</div>
                <a href="{{ route('admin.users.export', array_filter(['format' => 'xlsx', 'scope' => 'all', 'search' => request('search')])) }}" class="block px-3 py-2 text-gray-700 hover:bg-gray-50">Excel (.xlsx)</a>
                <a href="{{ route('admin.users.export', array_filter(['format' => 'csv', 'scope' => 'all', 'search' => request('search')])) }}" class="block px-3 py-2 text-gray-700 hover:bg-gray-50">CSV</a>
            </div>
        </div>
    </x-slot:action>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      {{-- Phones: one card per user --}}
      <ul class="sm:hidden divide-y divide-gray-100">
          @forelse ($users as $u)
              <li class="p-4">
                  <div class="flex items-start gap-3">
                      <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-brand-500 text-white font-bold flex items-center justify-center text-xs flex-shrink-0">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
                      <div class="min-w-0 flex-1">
                          <div class="font-medium text-gray-900 flex items-center gap-1.5 flex-wrap">
                              <span class="break-words">{{ $u->name }}</span>
                              @if ($u->isSuperAdmin())<span class="text-xs px-1.5 py-0.5 rounded bg-danger-100 text-danger-700 font-semibold">Super admin</span>@endif
                              @if ($u->email_verified_at)<span class="text-xs px-1.5 py-0.5 rounded bg-money-100 text-money-700 font-semibold">✓ Verified</span>@endif
                          </div>
                          <div class="text-xs text-gray-500 break-all">{{ $u->email }}</div>
                          @if ($u->phone)
                              <div class="text-xs text-gray-500 font-mono">{{ $u->phone }}</div>
                          @endif
                      </div>
                  </div>
                  <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                      <div>
                          <dt class="text-xs text-gray-500">Companies</dt>
                          <dd class="font-mono tabular-nums text-gray-700">{{ $u->companies_count }}</dd>
                      </div>
                      <div>
                          <dt class="text-xs text-gray-500">Customers</dt>
                          <dd class="font-mono tabular-nums text-gray-700">{{ $u->customers_count }}</dd>
                      </div>
                      <div>
                          <dt class="text-xs text-gray-500">Invoices</dt>
                          <dd class="font-mono tabular-nums text-gray-700">{{ $u->invoices_count }}</dd>
                      </div>
                      <div>
                          <dt class="text-xs text-gray-500">Revenue</dt>
                          <dd class="font-mono tabular-nums font-semibold text-gray-900 break-all">₹{{ rupees((float) $u->revenue) }}</dd>
                      </div>
                      <div>
                          <dt class="text-xs text-gray-500">Joined</dt>
                          <dd class="text-gray-700">{{ $u->created_at->format('d M Y') }}</dd>
                      </div>
                  </dl>
                  <div class="mt-3 flex flex-wrap items-center gap-2">
                      <a href="{{ route('admin.users.show', $u) }}" class="inline-flex items-center min-h-[40px] px-3 rounded-md ring-1 ring-gray-200 text-brand-700 text-sm font-medium hover:bg-gray-50">View</a>
                      @if (! $u->isSuperAdmin() && $u->id !== auth()->id())
                          <form method="POST" action="{{ route('admin.users.impersonate', $u) }}"
                                onsubmit="return confirm(@js('Log in as ' . $u->name . '? You can return to your own account anytime using the banner at the top.'))">
                              @csrf
                              <button class="inline-flex items-center min-h-[40px] px-3 bg-accent-100 hover:bg-accent-200 text-accent-800 text-sm font-semibold rounded-md transition" title="Log in as this user">Impersonate</button>
                          </form>
                      @endif
                  </div>
              </li>
          @empty
              <li class="px-4 py-12 text-center text-gray-500">No users match.</li>
          @endforelse
      </ul>

      {{-- Tablets and up: the full table --}}
      <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-gray-50 text-xs text-gray-500">
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
                                        @if ($u->isSuperAdmin())<span class="text-[10px] px-1.5 py-0.5 rounded bg-danger-100 text-danger-700 font-semibold">Super admin</span>@endif
                                        @if ($u->email_verified_at)<span class="text-[10px] px-1.5 py-0.5 rounded bg-money-100 text-money-700 font-semibold">✓ Verified</span>@endif
                                    </div>
                                    <div class="text-xs text-gray-500 truncate">{{ $u->email }}</div>
                                    @if ($u->phone)
                                        <div class="text-xs text-gray-500 font-mono">{{ $u->phone }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-700">{{ $u->companies_count }}</td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-700">{{ $u->customers_count }}</td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-700">{{ $u->invoices_count }}</td>
                        <td class="px-5 py-3 text-right font-mono tabular-nums font-semibold text-gray-900">₹{{ rupees((float) $u->revenue) }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $u->created_at->format('d M Y') }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.users.show', $u) }}" class="text-brand-600 hover:underline text-sm font-medium">View</a>
                                @if (! $u->isSuperAdmin() && $u->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.impersonate', $u) }}" class="inline"
                                          onsubmit="return confirm(@js('Log in as ' . $u->name . '? You can return to your own account anytime using the banner at the top.'))">
                                        @csrf
                                        <button class="px-2.5 py-1 bg-accent-100 hover:bg-accent-200 text-accent-800 text-xs font-semibold rounded transition" title="Log in as this user">Impersonate</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No users match.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4 max-sm:[&_a]:min-h-[40px] max-sm:[&_span]:min-h-[40px]">{{ $users->links() }}</div>
</x-layouts.admin>
