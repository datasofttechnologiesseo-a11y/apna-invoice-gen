<x-guest-layout>
    <div class="text-center">
        <div class="text-[11px] uppercase tracking-widest font-bold text-brand-700">Invitation</div>
        <h1 class="mt-1 font-display text-xl font-extrabold text-gray-900">Join {{ $member->company->name }}</h1>
        <p class="mt-2 text-sm text-gray-600">
            You are invited as <strong>{{ $member->roleLabel() }}</strong>.
        </p>
        <p class="mt-1 text-xs text-gray-500">{{ \App\Models\CompanyMember::ROLES[$member->role]['help'] ?? '' }}</p>
    </div>

    @if (session('error'))
        <div class="mt-4 p-3 rounded bg-danger-50 border border-danger-200 text-danger-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="mt-6">
        @if (! $user)
            @php session()->put('url.intended', request()->fullUrl()); @endphp
            <p class="text-sm text-gray-700 text-center">Log in with <strong>{{ $member->email }}</strong> to accept.</p>
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="{{ route('login') }}" class="text-center px-4 py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-md text-sm font-semibold">Log in</a>
                <a href="{{ route('register') }}" class="text-center px-4 py-2.5 ring-1 ring-gray-300 hover:bg-gray-50 rounded-md text-sm font-semibold">Create account</a>
            </div>
            <p class="mt-3 text-xs text-gray-500 text-center">New to Apna Invoice? Create an account with {{ $member->email }}, then open this link again.</p>
        @elseif ($member->isAccepted() && (int) $member->user_id === (int) $user->id)
            <p class="text-sm text-gray-700 text-center">You have already joined.</p>
            <form method="POST" action="{{ route('companies.switch', $member->company) }}" class="mt-4 text-center">@csrf
                <button class="px-4 py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-md text-sm font-semibold">Open {{ $member->company->name }}</button>
            </form>
        @elseif ($emailMatches)
            <form method="POST" action="{{ route('team.accept', $member->token) }}" class="text-center">@csrf
                <button class="w-full px-4 py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-md text-sm font-semibold">Accept and open {{ $member->company->name }}</button>
            </form>
        @else
            <p class="text-sm text-gray-700 text-center">This invitation is for <strong>{{ $member->email }}</strong>, but you are logged in as {{ $user->email }}. Log out, then log in with the invited email address.</p>
        @endif
    </div>
</x-guest-layout>
