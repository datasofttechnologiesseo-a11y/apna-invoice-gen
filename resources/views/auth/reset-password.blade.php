<x-guest-layout :title="'Choose a new password'" :noindex="true">
    <h1 class="mb-6 font-display text-2xl sm:text-[1.75rem] font-bold text-gray-900 tracking-tight">Choose a new password</h1>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email *')" class="font-semibold text-gray-800" />
            <x-text-input id="email" class="block mt-1.5 w-full min-h-[44px] rounded-lg" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" for="email" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-5">
            <x-input-label for="password" :value="__('New password *')" class="font-semibold text-gray-800" />
            <x-password-input id="password" name="password" autocomplete="new-password" minlength="8" class="min-h-[44px] !rounded-lg" />
            <x-input-error :messages="$errors->get('password')" for="password" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-5">
            <x-input-label for="password_confirmation" :value="__('Confirm new password *')" class="font-semibold text-gray-800" />
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="min-h-[44px] !rounded-lg" />
            <x-input-error :messages="$errors->get('password_confirmation')" for="password_confirmation" class="mt-2" />
        </div>

        <x-primary-button class="w-full mt-6 min-h-[48px] !rounded-lg !text-base">
            {{ __('Reset password') }}
        </x-primary-button>
    </form>
</x-guest-layout>
