<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Refer a friend</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-money-50 border border-money-200 text-money-800 rounded">{{ session('status') }}</div>
            @endif

            {{-- Hero --}}
            <div class="bg-gradient-to-br from-brand-900 via-brand-800 to-accent-900 rounded-2xl p-8 text-white shadow-brand">
                <div class="flex items-start gap-2 text-accent-300 text-xs font-bold uppercase tracking-widest">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2.39 4.84L18 8l-4 3.9.94 5.48L10 14.77 5.06 17.38 6 11.9 2 8l5.61-1.16z"/></svg>
                    Your referral code
                </div>
                <div class="mt-3 font-display font-extrabold text-4xl sm:text-5xl tracking-tight tabular-nums">{{ $code }}</div>
                <p class="mt-3 text-brand-100 max-w-xl">
                    Share this code with other business owners you know. They get an easy on-ramp to GST-compliant invoicing,
                    and you get a shout-out for spreading the word.
                </p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ $waShare }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#25D366] text-white text-sm font-semibold rounded hover:bg-[#1ebe5b]">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                        Share on WhatsApp
                    </a>
                    <a href="mailto:?subject={{ urlencode('Try Apna Invoice') }}&body={{ urlencode($shareText) }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-brand-900 text-sm font-semibold rounded hover:bg-gray-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Email a friend
                    </a>
                    <button type="button" x-data="{ copied: false }"
                            @click="navigator.clipboard.writeText('{{ $shareUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-white/10 text-white text-sm font-semibold rounded hover:bg-white/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        <span x-text="copied ? 'Link copied!' : 'Copy signup link'"></span>
                    </button>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="p-5 bg-white rounded-xl shadow-card ring-1 ring-gray-100">
                    <div class="text-xs uppercase tracking-wider font-bold text-gray-500">Sign-ups via your code</div>
                    <div class="text-3xl font-display font-extrabold mt-2 text-gray-900">{{ $stats['total'] }}</div>
                </div>
                <div class="p-5 bg-white rounded-xl shadow-card ring-1 ring-gray-100">
                    <div class="text-xs uppercase tracking-wider font-bold text-gray-500">Pending</div>
                    <div class="text-3xl font-display font-extrabold mt-2 text-amber-700">{{ $stats['pending'] }}</div>
                </div>
                <div class="p-5 bg-white rounded-xl shadow-card ring-1 ring-gray-100">
                    <div class="text-xs uppercase tracking-wider font-bold text-gray-500">Rewarded</div>
                    <div class="text-3xl font-display font-extrabold mt-2 text-money-700">{{ $stats['rewarded'] }}</div>
                </div>
            </div>

            {{-- Referrals list --}}
            <div class="bg-white rounded-xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b">
                    <h3 class="font-semibold text-gray-900">People you've referred</h3>
                </div>
                @if ($referrals->isEmpty())
                    <div class="p-8 text-center text-gray-500 text-sm">
                        No referrals yet. Share your code above — every person helps!
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
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        'rewarded' => 'bg-money-100 text-money-800',
                                        'disqualified' => 'bg-gray-200 text-gray-600',
                                    ][$r->reward_status] ?? 'bg-gray-100 text-gray-700';
                                @endphp
                                <span class="text-xs font-semibold uppercase tracking-wider px-2 py-1 rounded {{ $badge }}">
                                    {{ str_replace('_', ' ', $r->reward_status) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="text-xs text-gray-500 text-center">
                Tip: the referral code also works as a bookmark-friendly link — <span class="font-mono">{{ $shareUrl }}</span>.
            </div>
        </div>
    </div>
</x-app-layout>
