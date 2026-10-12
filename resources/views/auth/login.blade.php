<x-guest-layout
    :title="'Log in to your free GST invoice account'"
    :description="'Log in to Apna Invoice, free GST-compliant online invoice generator for Indian SMEs, startups, freelancers, and CAs.'"
    :keywords="'Apna Invoice login, GST invoice login, invoice generator India login'">
    <div class="mb-7">
        <h1 class="font-display text-2xl sm:text-[1.75rem] font-bold text-gray-900 tracking-tight">Log in to Apna Invoice</h1>
        <p class="mt-2 text-[15px] text-gray-600">
            New here? <a href="{{ route('register') }}" class="font-semibold text-brand-700 hover:text-brand-800 hover:underline underline-offset-2">Create a free account</a>
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email *')" class="font-semibold text-gray-800" />
            <x-text-input id="email" class="block mt-1.5 w-full min-h-[44px] rounded-lg" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" for="email" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-5">
            <div class="flex items-center justify-between gap-3">
                <x-input-label for="password" :value="__('Password *')" class="font-semibold text-gray-800" />
                @if (Route::has('password.request'))
                    <a class="text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline underline-offset-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-600" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>
            <x-password-input id="password" name="password" autocomplete="current-password" class="min-h-[44px] !rounded-lg" />
            <x-input-error :messages="$errors->get('password')" for="password" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center min-h-[32px]">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-brand-700 shadow-sm focus:ring-brand-600" name="remember">
                <span class="ms-2 text-sm text-gray-700">{{ __('Remember me') }}</span>
            </label>
        </div>

        <x-turnstile />

        <x-primary-button class="w-full mt-6 min-h-[48px] !rounded-lg !text-base">
            {{ __('Log in') }}
        </x-primary-button>
    </form>

    <x-google-auth-button label="Sign in with Google" />
</x-guest-layout>
