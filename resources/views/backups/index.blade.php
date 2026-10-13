<x-app-layout title="Backups">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'All settings', 'href' => route('settings.index')],
            ['label' => 'Backups'],
        ]" />
        <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Backups</h1>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-money-50 border border-money-200 text-money-800 rounded-lg text-sm" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-4 bg-danger-50 border border-danger-200 text-danger-800 rounded">
                    <ul class="list-disc pl-6">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="font-display text-lg font-bold text-gray-900">Weekly email backup</h2>
                <p class="mt-1 text-sm text-gray-600 leading-relaxed">
                    Every Sunday morning, a ZIP of CSV files (invoices, customers, products, payments and expenses) is emailed to <strong class="font-mono">{{ $user->email }}</strong>.
                </p>

                <form method="POST" action="{{ route('backup.toggle') }}" class="mt-4 flex items-center gap-3">
                    @csrf
                    <input type="hidden" name="auto_backup_enabled" value="{{ $user->auto_backup_enabled ? '0' : '1' }}">
                    <button type="submit"
                            class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-semibold transition
                                {{ $user->auto_backup_enabled ? 'bg-money-100 text-money-800 hover:bg-money-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                            title="{{ $user->auto_backup_enabled ? 'Turn off weekly backup' : 'Turn on weekly backup' }}">
                        <span class="w-2.5 h-2.5 rounded-full {{ $user->auto_backup_enabled ? 'bg-money-500' : 'bg-gray-400' }}"></span>
                        {{ $user->auto_backup_enabled ? 'Enabled' : 'Disabled' }}
                    </button>

                    @if ($user->last_backup_sent_at)
                        <span class="text-xs text-gray-500">
                            Last sent: {{ $user->last_backup_sent_at->diffForHumans() }}
                        </span>
                    @endif
                </form>
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="font-display text-lg font-bold text-gray-900">Manual backup</h2>
                <p class="mt-1 text-sm text-gray-600 leading-relaxed">
                    Download a backup now, or email it to your registered address. The CSV files open in Excel.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('backup.download') }}" class="inline-flex items-center gap-1.5 h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download backup
                    </a>
                    <form method="POST" action="{{ route('backup.email') }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 h-10 px-4 bg-white ring-1 ring-inset ring-gray-300 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Email backup
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-accent-50 border border-accent-200 rounded-xl p-5 text-sm text-accent-900">
                <div class="font-semibold">Store backups securely</div>
                <p class="mt-1">Backups contain customer GSTINs and payment history. Keep them on an encrypted drive or a password-protected cloud folder.</p>
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 text-sm text-gray-600">
                <h3 class="font-semibold text-gray-900 mb-2">Data retention</h3>
                <ul class="space-y-1.5 list-disc pl-5">
                    <li><strong>Invoices, customers, payments and ledger</strong> stay in your account while it is active.</li>
                    <li><strong>Emailed backups</strong> are not stored on our servers after sending.</li>
                    <li><strong>If you delete your account</strong>, your personal data is erased. Invoice records that GST law requires us to keep are retained in de-identified form, then deleted.</li>
                    <li><strong>We do not sell or share</strong> your data.</li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
