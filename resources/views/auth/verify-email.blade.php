<x-guest-layout :title="'Verify your email'" :noindex="true">
    <h1 class="font-display text-2xl sm:text-[1.75rem] font-bold text-gray-900 tracking-tight">Verify your email</h1>
    <div class="mt-2 mb-6 text-[15px] text-gray-600">
        {{ __('Open the link in the email we sent you to verify your email address. If it has not arrived, send it again.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 px-4 py-3 rounded-lg bg-money-50 ring-1 ring-money-200 font-medium text-sm text-money-800" role="status">
            {{ __('A new verification link has been sent to your email address.') }}
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button class="min-h-[44px] !rounded-lg">
                    {{ __('Resend verification email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="min-h-[44px] px-1 underline underline-offset-2 text-sm text-gray-700 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-600">
                {{ __('Log out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
