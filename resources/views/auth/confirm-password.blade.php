<x-guest-layout :title="'Confirm your password'" :noindex="true">
    <h1 class="font-display text-2xl sm:text-[1.75rem] font-bold text-gray-900 tracking-tight">Confirm your password</h1>

    <div class="mt-2 mb-6 text-[15px] text-gray-600">
        {{ __('Enter your password to continue.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" class="font-semibold text-gray-800" />
            <x-password-input id="password" name="password" autocomplete="current-password" class="min-h-[44px] !rounded-lg" />
            <x-input-error :messages="$errors->get('password')" for="password" class="mt-2" />
        </div>

        <x-primary-button class="w-full mt-6 min-h-[48px] !rounded-lg !text-base">
            {{ __('Confirm') }}
        </x-primary-button>
    </form>
</x-guest-layout>
