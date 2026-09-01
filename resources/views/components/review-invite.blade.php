@props(['show' => false])

{{--
    Google review invite.

    Shown once, on the first dashboard visit after the user has issued an
    invoice. Deliberately not on the invoice screen: that page already
    congratulates them, and its job is to get the bill sent - a modal over the
    share buttons would block the very thing being celebrated.

    The poster carries its own QR code, which is what a phone user will scan.
    A clickable button is added on top only when GOOGLE_REVIEW_URL is set,
    since a desktop user cannot scan their own screen.
--}}
@if ($show ?? false)
    @php $reviewUrl = config('seo.contact.google_review_url'); @endphp

    <div x-data="{ open: true }"
         x-show="open"
         x-cloak
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="review-invite-title">

        {{-- Backdrop. Clicking it dismisses, which is the least annoying way
             out of a prompt nobody asked for. --}}
        <div x-show="open"
             x-transition.opacity
             @click="open = false"
             class="absolute inset-0 bg-brand-950/70 backdrop-blur-sm"></div>

        <div x-show="open"
             x-transition
             class="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl ring-1 ring-black/10">

            <button type="button"
                    @click="open = false"
                    aria-label="Close"
                    class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white/90 text-gray-500 hover:text-gray-900 hover:bg-white ring-1 ring-gray-200 flex items-center justify-center transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <h2 id="review-invite-title" class="sr-only">Leave us a Google review</h2>

            {{-- 800px wide covers 2x retina in a modal this size. WebP first:
                 the source PNG was 1.5 MB, which is several seconds on the
                 mobile data this audience actually uses. --}}
            <picture>
                <source srcset="{{ asset('brand/review-invite.webp') }}" type="image/webp">
                <img src="{{ asset('brand/review-invite.png') }}"
                     alt="Love our service? Leave us a review. Scan the QR code to share your experience on Google."
                     class="w-full h-auto rounded-t-2xl"
                     loading="lazy" decoding="async"
                     width="800" height="1200">
            </picture>

            <div class="p-5 flex flex-col sm:flex-row items-center gap-3">
                @if ($reviewUrl)
                    <a href="{{ $reviewUrl }}" target="_blank" rel="noopener"
                       @click="open = false"
                       class="w-full sm:flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-brand transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.36 4.18a1 1 0 00.95.69h4.4c.97 0 1.37 1.24.59 1.81l-3.56 2.59a1 1 0 00-.36 1.12l1.36 4.18c.3.92-.75 1.69-1.54 1.12l-3.55-2.59a1 1 0 00-1.18 0l-3.55 2.59c-.79.57-1.84-.2-1.54-1.12l1.36-4.18a1 1 0 00-.36-1.12L1.76 9.61c-.79-.57-.38-1.81.58-1.81h4.4a1 1 0 00.95-.69z"/>
                        </svg>
                        Write a review
                    </a>
                @endif
                <button type="button" @click="open = false"
                        class="w-full sm:w-auto px-5 py-3 text-sm font-semibold text-gray-600 hover:text-gray-900 transition">
                    Maybe later
                </button>
            </div>
        </div>
    </div>
@endif
