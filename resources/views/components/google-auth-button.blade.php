@props(['label' => 'Continue with Google', 'showTerms' => false])

{{-- Renders only when Google OAuth is configured, so the page never shows a
     dead button. Set GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET to enable. --}}
@if (filled(config('services.google.client_id')))
    <div class="my-5 flex items-center gap-3" aria-hidden="true">
        <div class="h-px flex-1 bg-gray-200"></div>
        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wide">or</span>
        <div class="h-px flex-1 bg-gray-200"></div>
    </div>

    <a href="{{ route('social.redirect', 'google') }}"
       class="flex items-center justify-center gap-3 w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-semibold hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/30 transition">
        <svg class="w-5 h-5" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
            <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
            <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
            <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.792 2.237-2.231 4.166-4.087 5.571.001-.001.002-.001.003-.002l6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
        </svg>
        {{ $label }}
    </a>

    @if ($showTerms)
        <p class="mt-3 text-center text-xs text-gray-500">
            By continuing with Google you agree to our
            <a href="{{ route('pages.terms') }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">Terms</a>
            and
            <a href="{{ route('pages.privacy') }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">Privacy Policy</a>.
        </p>
    @endif
@endif
