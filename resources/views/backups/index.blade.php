<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Backups</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-money-50 border border-money-200 text-money-800 rounded">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded">
                    <ul class="list-disc pl-6">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-card ring-1 ring-gray-100 p-6">
                <h3 class="font-display text-lg font-bold text-gray-900">Weekly auto-backup</h3>
                <p class="mt-1 text-sm text-gray-600 leading-relaxed">
                    Every Sunday morning, we zip up your business data — invoices, customers, products, payments, expenses — as CSV files and email it to your registered address, <strong class="font-mono">{{ $user->email }}</strong>.
                </p>

                <form method="POST" action="{{ route('backup.toggle') }}" class="mt-4 flex items-center gap-3">
                    @csrf
                    <input type="hidden" name="auto_backup_enabled" value="{{ $user->auto_backup_enabled ? '0' : '1' }}">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded text-sm font-semibold
                                {{ $user->auto_backup_enabled ? 'bg-money-100 text-money-800 hover:bg-money-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        <span class="w-2.5 h-2.5 rounded-full {{ $user->auto_backup_enabled ? 'bg-money-500' : 'bg-gray-400' }}"></span>
                        {{ $user->auto_backup_enabled ? 'ON — click to disable' : 'OFF — click to enable' }}
                    </button>

                    @if ($user->last_backup_sent_at)
                        <span class="text-xs text-gray-500">
                            Last sent: {{ $user->last_backup_sent_at->diffForHumans() }}
                        </span>
                    @endif
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-card ring-1 ring-gray-100 p-6">
                <h3 class="font-display text-lg font-bold text-gray-900">Manual backup</h3>
                <p class="mt-1 text-sm text-gray-600 leading-relaxed">
                    Download or email a backup right now. Useful before big changes, at tax filing time, or if you need a clean CSV export for your CA.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('backup.download') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download backup ZIP
                    </a>
                    <form method="POST" action="{{ route('backup.email') }}" class="inline">
                        @csrf
                        <button class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 text-gray-800 text-sm font-semibold rounded hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Email it to me now
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-900">
                <div class="font-semibold">Keep your backup ZIPs secure.</div>
                <p class="mt-1">They contain customer GSTINs and payment history. Store them on an encrypted drive or password-protected cloud folder, and never share them publicly.</p>
            </div>
        </div>
    </div>
</x-app-layout>
