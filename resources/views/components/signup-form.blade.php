@props([
    // Pre-filled from the session (?ref= on any page) unless given.
    'referralCode' => null,
    'autofocus' => false,
    // Prefix for element ids, so the form can appear more than once on a page.
    'idPrefix' => '',
    // Landing pages: shorter hints.
    'compact' => false,
    'submitLabel' => 'Create account',
])

{{--
    The one sign-up form: the /register page and the landing pages (home,
    /start) all post the same fields to route('register'), with the same
    captcha, consent and referral handling, so a visitor from an ad signs up
    without leaving the page they landed on. A failed check comes back to the
    page the form was on (Laravel redirects back) with the errors in place.
--}}
@php
    $referralCode ??= session('referral_code');
    $id = fn (string $name) => $idPrefix . $name;
    $hint = 'mt-1.5 text-[13px] text-gray-500';
@endphp

<div {{ $attributes }}>
    @if (! empty($referralCode))
        <div class="mb-5 px-4 py-3 bg-money-50 ring-1 ring-money-200 rounded-lg text-sm text-money-900 flex items-center gap-2.5">
            <svg class="w-5 h-5 shrink-0 text-money-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span>Referral code applied: <strong class="font-mono">{{ $referralCode }}</strong></span>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        @if (! empty($referralCode))
            <input type="hidden" name="referral_code" value="{{ $referralCode }}">
        @endif

        <div>
            <x-input-label :for="$id('name')" :value="__('Your name *')" class="font-semibold text-gray-800" />
            <x-text-input :id="$id('name')" class="block mt-1.5 w-full min-h-[44px] rounded-lg" type="text" name="name" :value="old('name')" required :autofocus="$autofocus" autocomplete="name" placeholder="e.g. Priya Sharma" :aria-describedby="$compact ? null : $id('name-hint')" />
            @unless ($compact)
                <p id="{{ $id('name-hint') }}" class="{{ $hint }}">Shown on invoices until you add a business name.</p>
            @endunless
            <x-input-error :messages="$errors->get('name')" :for="$id('name')" class="mt-2" />
        </div>

        <div class="{{ $compact ? 'mt-4' : 'mt-5' }}">
            <x-input-label :for="$id('email')" :value="__('Email *')" class="font-semibold text-gray-800" />
            <x-text-input :id="$id('email')" class="block mt-1.5 w-full min-h-[44px] rounded-lg" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="you@example.com" :aria-describedby="$id('email-hint')" />
            <p id="{{ $id('email-hint') }}" class="{{ $hint }}">A 6-digit verification code will be sent to this address.</p>
            <x-input-error :messages="$errors->get('email')" :for="$id('email')" class="mt-2" />
        </div>

        <div class="{{ $compact ? 'mt-4' : 'mt-5' }}">
            <x-input-label :for="$id('phone')" :value="__('Mobile number *')" class="font-semibold text-gray-800" />
            <div class="mt-1.5 flex">
                <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-[#878fa0] bg-gray-50 text-gray-700 text-sm font-semibold tabular-nums" aria-hidden="true">+91</span>
                <x-text-input :id="$id('phone')" class="block w-full min-w-0 min-h-[44px] rounded-l-none rounded-r-lg" type="tel" name="phone" :value="old('phone')"
                    inputmode="numeric" maxlength="10" required autocomplete="tel-national" placeholder="98765 43210" :aria-describedby="$id('phone-hint')" />
            </div>
            <p id="{{ $id('phone-hint') }}" class="{{ $hint }}"><span class="sr-only">10-digit Indian mobile number, without +91. </span>{{ $compact ? 'For help with setup, by call or WhatsApp. No spam.' : 'Our support team uses this number to help you set up, by call or WhatsApp. No spam.' }}</p>
            <x-input-error :messages="$errors->get('phone')" :for="$id('phone')" class="mt-2" />
        </div>

        <div class="{{ $compact ? 'mt-4' : 'mt-5' }}">
            <x-input-label :for="$id('password')" :value="__('Password *')" class="font-semibold text-gray-800" />
            <x-password-input :id="$id('password')" name="password" autocomplete="new-password" minlength="8" :aria-describedby="$id('password-hint')" class="min-h-[44px] !rounded-lg" />
            <p id="{{ $id('password-hint') }}" class="{{ $hint }}">At least 8 characters.{{ $compact ? '' : ' A mix of letters, numbers and symbols is stronger.' }}</p>
            <x-input-error :messages="$errors->get('password')" :for="$id('password')" class="mt-2" />
        </div>

        {{-- DPDP Act 2023 s.6 / GDPR Art.7: explicit, unbundled, affirmative
             consent. Pre-ticked boxes are not valid consent, so both default off. --}}
        <div class="mt-5 space-y-3">
            <label class="flex items-start gap-2.5 text-sm text-gray-700 leading-snug">
                <input id="{{ $id('terms_accepted') }}" name="terms_accepted" type="checkbox" value="1"
                    @checked(old('terms_accepted'))
                    required
                    class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                <span>
                    I have read and agree to the
                    <a href="{{ route('pages.terms') }}" target="_blank" rel="noopener" class="font-semibold text-brand-700 hover:underline">Terms of Service</a>
                    and
                    <a href="{{ route('pages.privacy') }}" target="_blank" rel="noopener" class="font-semibold text-brand-700 hover:underline">Privacy Policy</a>,
                    and I am at least 18 years old.
                </span>
            </label>
            <x-input-error :messages="$errors->get('terms_accepted')" :for="$id('terms_accepted')" class="!mt-1" />

            <label class="flex items-start gap-2.5 text-sm text-gray-600 leading-snug">
                <input id="{{ $id('marketing_opt_in') }}" name="marketing_opt_in" type="checkbox" value="1"
                    @checked(old('marketing_opt_in'))
                    class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                <span>Email me product updates and GST compliance news (optional). You can unsubscribe at any time.</span>
            </label>
        </div>

        <x-turnstile />

        <x-primary-button class="w-full mt-6 min-h-[48px] !rounded-lg !text-base">
            {{ $submitLabel }}
        </x-primary-button>

        <p class="mt-4 text-center text-sm text-gray-600">
            <a class="font-medium text-brand-700 hover:text-brand-800 hover:underline underline-offset-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-600" href="{{ route('login') }}">
                {{ __('Already registered? Log in') }}
            </a>
        </p>
    </form>

    {{-- One tap with Google, when it is set up (renders nothing otherwise). --}}
    <x-google-auth-button label="Sign up with Google" :show-terms="true" />
</div>
