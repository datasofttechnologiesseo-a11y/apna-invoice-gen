<x-guest-layout
    :title="'Verify your mobile, Apna Invoice'"
    :description="'Confirm the one-time code sent to your mobile to finish creating your free Apna Invoice account.'">

    <div class="mb-6 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-1m-6-1h.01M20 4l-9 9-3-3"/>
            </svg>
        </div>
        <h1 class="mt-3 font-display text-xl font-extrabold text-gray-900">Verify your mobile</h1>
        <p class="mt-1 text-sm text-gray-500">
            Enter the {{ config('otp.length', 6) }}-digit code we sent to <strong class="text-gray-700">{{ $maskedPhone }}</strong>.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 p-3 rounded-md bg-money-50 border border-money-200 text-money-900 text-sm">
            {{ session('status') }}
        </div>
    @endif

    @if (! empty($devCode))
        <div class="mb-4 p-3 rounded-md bg-amber-50 border border-amber-200 text-amber-800 text-sm">
            <strong>Dev mode:</strong> your code is
            <span class="font-mono font-bold tracking-widest text-base">{{ $devCode }}</span>
            <div class="text-xs mt-1 text-amber-700">Shown only because no SMS gateway is configured yet. This never appears in production.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('register.verify.store') }}">
        @csrf
        <x-input-label for="code" :value="__('Verification code')" class="sr-only" />
        <x-text-input id="code" name="code" type="text" inputmode="numeric" maxlength="6"
            autocomplete="one-time-code" autofocus required
            class="block w-full text-center text-2xl tracking-widest font-bold py-3"
            placeholder="••••••" />
        <x-input-error :messages="$errors->get('code')" class="mt-2" />

        <x-primary-button class="w-full justify-center mt-5 py-3">
            {{ __('Verify & create account') }}
        </x-primary-button>
    </form>

    <div class="mt-5 flex items-center justify-between text-sm">
        <form method="POST" action="{{ route('register.resend') }}">
            @csrf
            <button type="submit" class="text-brand-700 font-semibold hover:underline focus:outline-none">
                {{ __('Resend code') }}
            </button>
        </form>
        <a href="{{ route('register') }}" class="text-gray-600 hover:text-gray-900">
            {{ __('Change number') }}
        </a>
    </div>
</x-guest-layout>
