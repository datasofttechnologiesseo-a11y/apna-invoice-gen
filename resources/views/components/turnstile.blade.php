@php
    $siteKey = config('captcha.site_key');
@endphp

@if ($siteKey)
    <div class="mt-4">
        <div class="cf-turnstile" data-sitekey="{{ $siteKey }}" data-theme="light" data-size="flexible"></div>
        @error('cf-turnstile-response')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    @once
        @push('scripts')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endpush
    @endonce
@endif
