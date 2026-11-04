{{--
    Closing call to action. "Create free account" scrolls back to the sign-up
    form in the hero (#signup) rather than opening another page. The phone
    sticky bar hides as this comes into view (id="lp-final-cta"), so the two
    are never on screen together. The buttons sit on a white panel so the
    Google button and its "or" divider keep their contrast.
--}}
<section id="lp-final-cta" aria-labelledby="lp-final-title" class="bg-brand-900 text-white">
    <div class="mx-auto grid max-w-6xl items-center gap-8 px-4 py-14 sm:px-6 md:py-20 lg:grid-cols-2 lg:gap-12 lg:px-8">
        <div>
            <h2 id="lp-final-title" class="font-display text-2xl font-extrabold tracking-tight md:text-3xl">Start billing with Apna Invoice</h2>
            <p class="mt-3 max-w-lg leading-relaxed text-brand-100">Free during beta, with no card. Your business name and state are enough to issue the first GST invoice.</p>
        </div>
        <div class="w-full max-w-md rounded-xl bg-white p-5 text-gray-900 shadow-lg sm:p-6 lg:justify-self-end">
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
