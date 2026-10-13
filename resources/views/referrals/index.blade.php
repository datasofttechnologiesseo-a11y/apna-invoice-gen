<x-app-layout title="Refer a friend">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'All settings', 'href' => route('settings.index')],
            ['label' => 'Refer a friend'],
        ]" />
        <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Refer a friend</h1>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @if ($lifetimeFreeAt)
                <div class="p-4 sm:p-5 bg-money-50 border border-money-200 rounded-xl flex items-start gap-3">
                    <svg class="w-6 h-6 shrink-0 text-money-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <div class="font-semibold text-money-900">You have lifetime free access to Apna Invoice.</div>
                        <div class="text-sm text-money-800 mt-0.5">
                            Earned on {{ $lifetimeFreeAt->format('d M Y') }}{{ $rewardedBy ? ', when ' . $rewardedBy . ' issued their first invoice' : '' }}.
                            Your account stays free when paid plans are introduced.
                        </div>
                    </div>
                </div>
            @endif

            {{-- Code and sharing --}}
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 sm:p-8">
                <div class="text-sm font-medium text-gray-600">Your referral code</div>
                <div class="mt-1 font-display font-extrabold text-3xl sm:text-4xl tracking-tight text-gray-900 tabular-nums">{{ $code }}</div>
                <p class="mt-3 text-gray-700 max-w-2xl">
                    Refer a friend and get <strong>lifetime free access</strong> to Apna Invoice.
                    It counts when your friend signs up with your code and issues their first invoice.
                </p>
                <p class="mt-2 text-sm text-gray-600 max-w-2xl">
                    Accounts on the free plan today keep it. Lifetime free access goes further: your account stays free
                    even after paid plans are introduced.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ $waShare }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md">
                        <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                        Share on WhatsApp
                    </a>
                    <a href="{{ $mailShare }}"
                       class="inline-flex items-center gap-1.5 h-10 px-4 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-gray-800 text-sm font-semibold rounded-md">
                        <svg class="w-4 h-4 text-gray-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Share by email
                    </a>
                    <button type="button" x-data="{ copied: false }"
                            @click="copyText(@js($shareUrl)).then(ok => { if (ok) { copied = true; setTimeout(() => copied = false, 2000) } })"
                            class="inline-flex items-center gap-1.5 h-10 px-4 bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-gray-800 text-sm font-semibold rounded-md">
                        <svg class="w-4 h-4 text-gray-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        <span x-text="copied ? 'Link copied' : 'Copy sign-up link'">Copy sign-up link</span>
                    </button>
                </div>
            </div>

            {{-- How it works --}}
            <ol class="grid sm:grid-cols-3 gap-4 text-sm">
                @foreach ([
                    ['Share your code', 'Send your code or sign-up link on WhatsApp or by email.'],
                    ['Your friend signs up', 'They create their account with your code.'],
                    ['They issue an invoice', 'Your account becomes free for life. One friend is enough.'],
                ] as $i => [$title, $text])
                    <li class="p-4 bg-white rounded-xl ring-1 ring-gray-200 flex gap-3">
                        <span class="shrink-0 w-7 h-7 rounded-full bg-brand-50 text-brand-700 font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                        <span><span class="block font-semibold text-gray-900">{{ $title }}</span><span class="text-gray-600">{{ $text }}</span></span>
                    </li>
                @endforeach
            </ol>

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-3 sm:gap-4">
                <div class="p-4 sm:p-5 bg-white rounded-xl ring-1 ring-gray-200">
                    <div class="text-sm font-medium text-gray-600">Sign-ups</div>
                    <div class="text-2xl sm:text-3xl font-display font-extrabold mt-2 text-gray-900">{{ $stats['total'] }}</div>
                </div>
                <div class="p-4 sm:p-5 bg-white rounded-xl ring-1 ring-gray-200">
                    <div class="text-sm font-medium text-gray-600">Waiting for first invoice</div>
                    <div class="text-2xl sm:text-3xl font-display font-extrabold mt-2 text-gray-900">{{ $stats['pending'] }}</div>
                </div>
                <div class="p-4 sm:p-5 bg-white rounded-xl ring-1 ring-gray-200">
                    <div class="text-sm font-medium text-gray-600">Rewarded</div>
                    <div class="text-2xl sm:text-3xl font-display font-extrabold mt-2 text-gray-900">{{ $stats['rewarded'] }}</div>
                </div>
            </div>

            {{-- Referrals list --}}
            <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-900">Referrals</h2>
                </div>
                @if ($referrals->isEmpty())
                    <div class="p-8 text-center text-gray-500 text-sm">
                        No referrals yet. Friends who sign up with your code are listed here.
                    </div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($referrals as $r)
                            <li class="px-5 py-3 flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-brand-50 text-brand-700 font-bold flex items-center justify-center">
                                    {{ strtoupper(substr($r->referee->name ?? '?', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-900 truncate">{{ $r->referee->name }}</div>
                                    <div class="text-xs text-gray-500">Joined {{ $r->signed_up_at?->format('d M Y') }}</div>
                                </div>
                                @php
                                    $badge = [
                                        'pending' => 'bg-accent-50 text-accent-800',
                                        'rewarded' => 'bg-money-50 text-money-800',
                                        'disqualified' => 'bg-gray-100 text-gray-600',
                                    ][$r->reward_status] ?? 'bg-gray-100 text-gray-700';
                                @endphp
                                <span class="text-xs font-medium px-2.5 py-0.5 rounded-full whitespace-nowrap {{ $badge }}">
                                    {{ ['pending' => 'Waiting for first invoice', 'rewarded' => 'Rewarded', 'disqualified' => 'Not eligible'][$r->reward_status] ?? ucfirst($r->reward_status) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="text-xs text-gray-500 text-center break-all">
                Sign-up link: <span class="font-mono">{{ $shareUrl }}</span>
            </div>
        </div>
    </div>
</x-app-layout>
