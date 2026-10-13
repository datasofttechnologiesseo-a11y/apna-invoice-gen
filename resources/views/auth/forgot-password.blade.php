<x-guest-layout :title="'Reset your password'" :noindex="true">
    <h1 class="font-display text-2xl sm:text-[1.75rem] font-bold text-gray-900 tracking-tight">Reset your password</h1>
    <div class="mt-2 mb-6 text-[15px] text-gray-600">
        {{ __('Enter your registered email address. We will send you a link to reset your password.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- "We have emailed your reset link" is a lie when MAIL_MAILER is log or
         array: the message was written to a file and nobody will ever receive
         it. That combination cost a real round of "forgot password is broken"
         debugging, so on a non-production environment say where the mail
         actually went. Never shown in production - a signed-out visitor must
         not learn how the server is configured. --}}
    @if (session('status') && ! app()->isProduction() && in_array(config('mail.default'), ['log', 'array', 'null'], true))
        <div class="mb-4 rounded-lg bg-accent-50 ring-1 ring-accent-200 px-4 py-3 text-xs text-accent-900">
            <strong>Development note:</strong> <code>MAIL_MAILER</code> is
            <code>{{ config('mail.default') }}</code>, so no email was actually sent.
            @if (config('mail.default') === 'log')
                The message and its reset link are in <code>storage/logs/laravel.log</code>.
            @endif
            Run <code>php artisan mail:check</code> for details.
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email *')" class="font-semibold text-gray-800" />
            <x-text-input id="email" class="block mt-1.5 w-full min-h-[44px] rounded-lg" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" for="email" class="mt-2" />
        </div>

        <x-turnstile />

        <x-primary-button class="w-full mt-6 min-h-[48px] !rounded-lg !text-base">
            {{ __('Send reset link') }}
        </x-primary-button>

        <p class="mt-4 text-center text-sm">
            <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:text-brand-800 hover:underline underline-offset-2">Back to log in</a>
        </p>
    </form>
</x-guest-layout>
