<x-guest-layout
    :title="'Add your mobile number, Apna Invoice'"
    :description="'Add and verify your mobile number to finish creating your free Apna Invoice account.'"
    :noindex="true">

    <div class="mb-7">
        <h1 class="font-display text-2xl sm:text-[1.75rem] font-bold text-gray-900 tracking-tight">
            @if ($name) {{ \Illuminate\Support\Str::of($name)->before(' ') }}, add your mobile number @else Add your mobile number @endif
        </h1>
        <p class="mt-2 text-[15px] text-gray-600">Your email address is verified through Google.</p>
    </div>

    <form method="POST" action="{{ route('register.mobile.store') }}">
        @csrf
        <x-input-label for="phone" :value="__('Mobile number *')" class="font-semibold text-gray-800" />
        <div class="mt-1.5 flex">
            <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-[#878fa0] bg-gray-50 text-gray-700 text-sm font-semibold tabular-nums" aria-hidden="true">+91</span>
            <x-text-input id="phone" class="block w-full min-w-0 min-h-[44px] rounded-l-none rounded-r-lg" type="tel" name="phone" :value="old('phone')"
                inputmode="numeric" maxlength="10" required autofocus autocomplete="tel-national" placeholder="98765 43210" aria-describedby="phone-hint" />
            <span id="phone-hint" class="sr-only">10-digit Indian mobile number, without +91.</span>
        </div>
        <x-input-error :messages="$errors->get('phone')" for="phone" class="mt-2" />

        <x-primary-button class="w-full mt-6 min-h-[48px] !rounded-lg !text-base">
            {{ __('Create account') }}
        </x-primary-button>
    </form>

    <p class="mt-4 text-sm text-gray-500 leading-relaxed">
        Our support team uses this number to help you set up, by call or WhatsApp. You can opt out of marketing at any time.
    </p>
    <x-signup-help />
</x-guest-layout>
