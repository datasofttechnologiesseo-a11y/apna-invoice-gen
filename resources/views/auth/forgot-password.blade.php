<x-guest-layout :title="'Reset your password'" :noindex="true">
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? Enter your registered email below and we will send you a secure reset link.') }}
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
        <div class="mb-4 rounded-md bg-accent-50 border border-accent-200 p-3 text-xs text-accent-900">
            <strong>Development note:</strong> <code>MAIL_MAILER</code> is
            <code>{{ config('mail.default') }}</code>, so no email was actually sent.
            @if (config('mail.default') === 'log')
                The message and its reset link are in <code>storage/logs/laravel.log</code>.
            @endif
            Run <code>php artisan mail:check</code> for the full picture.
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email *')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-turnstile />

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
