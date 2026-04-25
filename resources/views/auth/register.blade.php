<x-guest-layout
    :title="'Sign up free — GST Invoice Generator'"
    :description="'Create a free Apna Invoice account. GST-compliant invoices, HSN/SAC codes, Indian number format, payment reminders — built for Indian SMEs, startups, freelancers, and CAs.'"
    :keywords="'free GST invoice signup, invoice generator signup India, create GST invoice free, Apna Invoice register'">
    @if (! empty($referralCode))
        <div class="mb-4 p-3 bg-money-50 border border-money-200 rounded text-sm text-money-900 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2.39 4.84L18 8l-4 3.9.94 5.48L10 14.77 5.06 17.38 6 11.9 2 8l5.61-1.16z"/></svg>
            <span>You're signing up with a friend's referral code: <strong class="font-mono">{{ $referralCode }}</strong></span>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        @if (! empty($referralCode))
            <input type="hidden" name="referral_code" value="{{ $referralCode }}">
        @endif

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Your name *')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email *')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password *')" />
            <x-password-input id="password" name="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm password *')" />
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        {{-- DPDP Act §6 / GDPR Art.7 — explicit, unbundled, affirmative consent.
             Pre-ticked boxes are NOT valid consent (CJEU Planet49), so defaults are false. --}}
        <div class="mt-5 space-y-3">
            <label class="flex items-start gap-2.5 text-sm text-gray-700 leading-snug">
                <input id="terms_accepted" name="terms_accepted" type="checkbox" value="1"
                    @checked(old('terms_accepted'))
                    required
                    class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <span>
                    I've read and agree to the
                    <a href="{{ route('pages.terms') }}" target="_blank" rel="noopener" class="font-semibold text-brand-700 hover:underline">Terms of Service</a>
                    and
                    <a href="{{ route('pages.privacy') }}" target="_blank" rel="noopener" class="font-semibold text-brand-700 hover:underline">Privacy Policy</a>,
                    and I'm at least 18 years old.
                </span>
            </label>
            <x-input-error :messages="$errors->get('terms_accepted')" class="!mt-1" />

            <label class="flex items-start gap-2.5 text-sm text-gray-600 leading-snug">
                <input id="marketing_opt_in" name="marketing_opt_in" type="checkbox" value="1"
                    @checked(old('marketing_opt_in'))
                    class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <span>Send me occasional product tips and GST-compliance updates by email. Optional — you can unsubscribe any time.</span>
            </label>
        </div>

        <x-turnstile />

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
