@props(['note' => null])

@php
    $whatsapp = config('seo.contact.whatsapp_url');
    $email = config('contacts.support');
@endphp

{{-- Sign-up is where a new user is most likely to get stuck with no way out:
     a code that never lands, a number they mistyped, a form they don't
     understand. A dead end here costs the whole account, so every step of the
     journey carries a route to a human. --}}
<div class="mt-6 pt-5 border-t border-gray-100">
    <div class="flex items-start gap-3">
        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-money-50 ring-1 ring-money-200 flex items-center justify-center">
            <svg class="w-4.5 h-4.5 text-money-700" style="width:1.125rem;height:1.125rem" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4.3-.96L3 20l1.4-3.7A7.6 7.6 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-semibold text-gray-800">Stuck? Talk to a real person.</div>
            <p class="mt-0.5 text-xs text-gray-500 leading-relaxed">
                {{ $note ?? 'We help new businesses set up every day. Ask us anything, however small.' }}
            </p>
            <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                @if ($whatsapp)
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#128C4A] hover:underline">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                        WhatsApp us
                    </a>
                @endif
                @if ($email)
                    <a href="mailto:{{ $email }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 4.26a2 2 0 001.98 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        {{ $email }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
