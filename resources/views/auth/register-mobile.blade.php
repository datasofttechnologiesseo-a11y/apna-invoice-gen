<x-guest-layout
    :title="'Add your mobile number, Apna Invoice'"
    :description="'Add and verify your mobile number to finish creating your free Apna Invoice account.'">

    <div class="mb-6 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
        </div>
        <h1 class="mt-3 font-display text-xl font-extrabold text-gray-900">
            @if ($name) Almost there, {{ \Illuminate\Support\Str::of($name)->before(' ') }}! @else Almost there! @endif
        </h1>
        <p class="mt-1 text-sm text-gray-500">Add your mobile number so we can verify it with a one-time code.</p>
    </div>

    <form method="POST" action="{{ route('register.mobile.store') }}">
        @csrf
        <x-input-label for="phone" :value="__('Mobile number *')" />
        <div class="mt-1 relative">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500 text-sm font-semibold">+91</span>
            <x-text-input id="phone" class="block w-full pl-10" type="tel" name="phone" :value="old('phone')"
                inputmode="numeric" maxlength="10" required autofocus autocomplete="tel-national" placeholder="98765 43210" />
        </div>
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />

        <x-primary-button class="w-full justify-center mt-5 py-3">
            {{ __('Send verification code') }}
        </x-primary-button>
    </form>

    <p class="mt-4 text-center text-xs text-gray-400">
        We only use your number to secure your account and send invoice alerts. You can opt out of marketing any time.
    </p>
</x-guest-layout>
