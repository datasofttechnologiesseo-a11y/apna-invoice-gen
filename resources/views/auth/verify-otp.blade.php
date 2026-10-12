@php $isEmail = ($channel ?? 'sms') === 'email'; @endphp
<x-guest-layout
    :title="'Verify your account, Apna Invoice'"
    :description="'Confirm the one-time code we sent you to finish creating your free Apna Invoice account.'"
    :noindex="true">

    <div class="mb-7">
        {{-- Waiting on a code is the loneliest moment in the whole sign-up: the
             user has handed over their details and has no idea how much is
             left. Showing the rest of the journey makes the wait finite. --}}
        <ol class="mb-6 flex items-center gap-2 text-[13px] font-semibold" aria-label="Sign-up steps">
            @foreach ([
                ['label' => 'Verify', 'state' => 'current'],
                ['label' => 'Your business', 'state' => 'next'],
                ['label' => 'First invoice', 'state' => 'next'],
            ] as $i => $stepItem)
                @if ($i > 0)
                    <li aria-hidden="true" class="w-5 h-px bg-gray-300"></li>
                @endif
                <li @if ($stepItem['state'] === 'current') aria-current="step" @endif @class([
                    'inline-flex items-center gap-1.5',
                    'text-brand-800' => $stepItem['state'] === 'current',
                    'text-gray-500' => $stepItem['state'] !== 'current',
                ])>
                    <span @class([
                        'inline-flex items-center justify-center w-5 h-5 rounded-full text-[11px] tabular-nums',
                        'bg-brand-700 text-white' => $stepItem['state'] === 'current',
                        'ring-1 ring-gray-300 text-gray-500' => $stepItem['state'] !== 'current',
                    ])>{{ $i + 1 }}</span>
                    {{ $stepItem['label'] }}
                </li>
            @endforeach
        </ol>
        <h1 class="font-display text-2xl sm:text-[1.75rem] font-bold text-gray-900 tracking-tight">
            {{ $isEmail ? 'Check your email' : 'Verify your mobile number' }}
        </h1>
        <p class="mt-2 text-[15px] text-gray-600">
            Enter the {{ config('otp.length', 6) }}-digit code we sent to <strong class="text-gray-700">{{ $sentTo }}</strong>.
        </p>
        @if ($isEmail)
            <p class="mt-2 text-sm text-gray-500">
                Not in your inbox? Check <strong>Spam</strong> or <strong>Promotions</strong>. The code can take up to a minute to arrive.
            </p>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-money-50 ring-1 ring-money-200 text-money-900 text-sm" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if (! empty($devCode))
        <div class="mb-4 px-4 py-3 rounded-lg bg-accent-50 ring-1 ring-accent-200 text-accent-900 text-sm">
            <strong>Development mode:</strong> your code is
            <span class="font-mono font-bold tracking-widest text-base">{{ $devCode }}</span>
            <div class="text-xs mt-1 text-accent-700">Shown because no delivery gateway is configured. Never shown in production.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('register.verify.store') }}" id="otp-form">
        @csrf
        <x-input-label for="code" :value="__('Verification code')" class="sr-only" />
        <x-text-input id="code" name="code" type="text" inputmode="numeric" maxlength="{{ (int) config('otp.length', 6) }}"
            autocomplete="one-time-code" autofocus required
            class="block w-full text-center text-2xl tracking-[0.4em] font-bold py-3 min-h-[56px] rounded-lg tabular-nums"
            placeholder="••••••" />
        <x-input-error :messages="$errors->get('code')" for="code" class="mt-2" />

        <x-primary-button class="w-full mt-5 min-h-[48px] !rounded-lg !text-base">
            {{ __('Verify and create account') }}
        </x-primary-button>
    </form>

    <div class="mt-5 flex items-center justify-between text-sm">
        <form method="POST" action="{{ route('register.resend') }}">
            @csrf
            <button type="submit" id="resend-btn" class="min-h-[44px] text-brand-700 font-semibold hover:underline underline-offset-2 disabled:text-gray-500 disabled:no-underline disabled:cursor-not-allowed tabular-nums">
                {{ __('Resend code') }}
            </button>
        </form>
        <a href="{{ route('register') }}" class="inline-flex items-center min-h-[44px] font-medium text-gray-700 hover:text-gray-900 hover:underline underline-offset-2">
            {{ $isEmail ? __('Change details') : __('Change number') }}
        </a>
    </div>

    <x-signup-help note="Code not arriving, or wrong details entered? Message us and we can verify your account manually." />

    <script>
        (function () {
            // Auto-submit the moment all 6 digits are in - one less tap.
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
