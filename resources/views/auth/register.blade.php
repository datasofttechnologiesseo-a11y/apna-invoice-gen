<x-guest-layout
    :title="'Sign up free, GST Invoice Generator'"
    :description="'Create a free Apna Invoice account. GST-compliant invoices, HSN/SAC codes, Indian number format and payment reminders, built for Indian SMEs, startups, freelancers, and CAs.'"
    :keywords="'free GST invoice signup, invoice generator signup India, create GST invoice free, Apna Invoice register'">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-extrabold text-gray-900 tracking-tight">Create your free account</h1>
        <p class="mt-1 text-sm text-gray-500">Unlimited GST invoices, free during beta.</p>
    </div>

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
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="e.g. Priya Sharma" />
            <p class="mt-1 text-xs text-gray-500">Shown on invoices unless you set a separate company name later.</p>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address (verification code goes here) -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email *')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="you@example.com" />
            <p class="mt-1 text-xs text-gray-500">We'll email you a 6-digit code to verify your account.</p>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Mobile Number (required, so our team can help you get set up) -->
        <div class="mt-4">
            <x-input-label for="phone" :value="__('Mobile number *')" />
            <div class="mt-1 relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500 text-sm font-semibold">+91</span>
                <x-text-input id="phone" class="block w-full pl-10" type="tel" name="phone" :value="old('phone')"
                    inputmode="numeric" maxlength="10" required autocomplete="tel-national" placeholder="98765 43210" />
            </div>
            <p class="mt-1 text-xs text-gray-500">So our team can reach you on WhatsApp/call to help you get set up. We won't spam you.</p>
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password *')" />
            <x-password-input id="password" name="password" autocomplete="new-password" />
            <p class="mt-1 text-xs text-gray-500">Use at least 8 characters. Mix letters, numbers and a symbol if you can.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm password *')" />
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        {{-- DPDP Act §6 / GDPR Art.7, explicit, unbundled, affirmative consent.
             Pre-ticked boxes are NOT valid consent (CJEU Planet49), so defaults are false. --}}
        <div class="mt-5 space-y-3">
            <label class="flex items-start gap-2.5 text-sm text-gray-700 leading-snug">
                <input id="terms_accepted" name="terms_accepted" type="checkbox" value="1"
                    @checked(old('terms_accepted'))
                    required
                    class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
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
                    class="mt-0.5 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                <span>Send me occasional product tips and GST-compliance updates by email. Optional, and you can unsubscribe any time.</span>
            </label>
        </div>

        <x-turnstile />

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-600" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Create my free account') }}
            </x-primary-button>
        </div>
    </form>

    <x-google-auth-button label="Sign up with Google" :show-terms="true" />

    <x-signup-help note="New to GST billing? Message us and we'll walk you through your first invoice, free." />
</x-guest-layout>
