{{--
    Closing call to action. "Create free account" scrolls back to the sign-up
    form in the hero (#signup) rather than opening another page. The phone
    sticky bar hides as this comes into view (id="lp-final-cta"), so the two
    are never on screen together. The buttons sit on a white panel so the
    Google button and its "or" divider keep their contrast. A WhatsApp line
    for anyone who wants to ask a person first.
--}}
@php $whatsapp = config('seo.contact.whatsapp_url'); @endphp
<section id="lp-final-cta" aria-labelledby="lp-final-title" class="relative overflow-hidden bg-brand-900 text-white">
    <div class="pointer-events-none absolute inset-0 bg-hero-mesh opacity-60" aria-hidden="true"></div>
    <div class="relative mx-auto grid max-w-6xl items-center gap-8 px-4 py-14 sm:px-6 md:py-20 lg:grid-cols-2 lg:gap-12 lg:px-8">
        <div>
            <p lang="hi" class="text-sm font-semibold text-accent-300">आज ही शुरू करें</p>
            <h2 id="lp-final-title" class="mt-2 font-display text-3xl font-extrabold tracking-tight md:text-4xl">Start billing with Apna Invoice</h2>
            <p class="mt-3 max-w-lg leading-relaxed text-brand-100">Free during beta, with no card. Your business name and state are enough to issue the first GST invoice.</p>
            @if ($whatsapp)
                <a href="{{ $whatsapp }}?text={{ rawurlencode('Hello, I have a question about Apna Invoice.') }}" target="_blank" rel="noopener"
                   class="mt-6 inline-flex min-h-[44px] items-center gap-2.5 rounded-lg bg-white/10 px-4 font-semibold text-white ring-1 ring-white/25 transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                    <svg class="h-5 w-5 text-[#25D366]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.48-1.75-1.65-2.05-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.5h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.27.49 1.7.63.72.23 1.37.2 1.88.12.58-.09 1.75-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35M12.05 21.5h-.01a9.45 9.45 0 01-4.82-1.32l-.35-.2-3.58.93.96-3.49-.23-.36A9.43 9.43 0 012.6 12C2.6 6.8 6.84 2.56 12.06 2.56c2.52 0 4.9.98 6.68 2.77a9.4 9.4 0 012.76 6.68c0 5.22-4.24 9.46-9.45 9.46"/></svg>
                    Ask us on WhatsApp
                </a>
            @endif
        </div>
        <div class="w-full max-w-md rounded-2xl bg-white p-5 text-gray-900 shadow-2xl sm:p-6 lg:justify-self-end">
            @auth
                <a href="{{ route('dashboard') }}" class="flex min-h-[52px] w-full items-center justify-center rounded-lg bg-brand-700 px-5 text-base font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">Go to dashboard</a>
            @else
                <a href="#signup" data-lp-signup class="flex min-h-[52px] w-full items-center justify-center rounded-lg bg-brand-700 px-5 text-base font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">Create free account</a>
                <x-google-auth-button />
                <p class="mt-4 text-center text-sm text-gray-600">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-700 underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">Log in</a></p>
            @endauth
        </div>
    </div>
</section>
