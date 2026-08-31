@php $isEmail = ($channel ?? 'sms') === 'email'; @endphp
<x-guest-layout
    :title="'Verify your account, Apna Invoice'"
    :description="'Confirm the one-time code we sent you to finish creating your free Apna Invoice account.'">

    <div class="mb-6 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
            @if ($isEmail)
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                </svg>
            @else
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-1m-6-1h.01M20 4l-9 9-3-3"/>
                </svg>
            @endif
        </div>
        <h1 class="mt-3 font-display text-xl font-extrabold text-gray-900">
            {{ $isEmail ? 'Check your email' : 'Verify your mobile' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            Enter the {{ config('otp.length', 6) }}-digit code we sent to <strong class="text-gray-700">{{ $sentTo }}</strong>.
        </p>
        @if ($isEmail)
            <p class="mt-2 text-xs text-gray-400">
                Not in your inbox? Check <strong>Spam</strong> or <strong>Promotions</strong> — it can take up to a minute to arrive.
            </p>
        @endif

        {{-- Waiting on a code is the loneliest moment in the whole sign-up: the
             user has handed over their details and has no idea how much is
             left. Showing the rest of the journey makes the wait finite. --}}
        <ol class="mt-5 flex items-center justify-center gap-2 text-[11px] font-semibold">
            @foreach ([
                ['label' => 'Verify', 'state' => 'current'],
                ['label' => 'Your business', 'state' => 'next'],
                ['label' => 'First invoice', 'state' => 'next'],
            ] as $i => $stepItem)
                @if ($i > 0)
                    <li aria-hidden="true" class="w-4 h-px bg-gray-200"></li>
                @endif
                <li @class([
                    'px-2.5 py-1 rounded-full ring-1',
                    'bg-brand-50 text-brand-700 ring-brand-200' => $stepItem['state'] === 'current',
                    'text-gray-400 ring-gray-200' => $stepItem['state'] !== 'current',
                ])>{{ $stepItem['label'] }}</li>
            @endforeach
        </ol>
        <p class="mt-2 text-xs text-gray-400">About a minute from here to your first invoice.</p>
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

    <form method="POST" action="{{ route('register.verify.store') }}" id="otp-form">
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
            <button type="submit" id="resend-btn" class="text-brand-700 font-semibold hover:underline focus:outline-none disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed">
                {{ __('Resend code') }}
            </button>
        </form>
        <a href="{{ route('register') }}" class="text-gray-600 hover:text-gray-900">
            {{ $isEmail ? __('Change details') : __('Change number') }}
        </a>
    </div>

    <x-signup-help note="Code not arriving, or entered the wrong number? Message us and we'll get you in — we can verify you manually." />

    <script>
        (function () {
            // Auto-submit the moment all 6 digits are in — one less tap.
            var input = document.getElementById('code');
            var form = document.getElementById('otp-form');
            var len = {{ (int) config('otp.length', 6) }};
            input.addEventListener('input', function () {
                input.value = input.value.replace(/\D/g, '').slice(0, len);
                if (input.value.length === len) form.submit();
            });

            // Resend cooldown countdown, so the button explains itself instead
            // of erroring with "please wait Ns".
            var btn = document.getElementById('resend-btn');
            var readyAt = {{ (int) ($canResendAt ?? 0) }} * 1000;
            function tick() {
                var left = Math.ceil((readyAt - Date.now()) / 1000);
                if (left > 0) {
                    btn.disabled = true;
                    btn.textContent = 'Resend code in ' + left + 's';
                    setTimeout(tick, 1000);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Resend code';
                }
            }
            tick();
        })();
    </script>
</x-guest-layout>
