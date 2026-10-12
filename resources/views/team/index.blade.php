<x-app-layout title="Team">
    <x-slot name="header">
        <div>
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Team</h1>
            <p class="text-sm text-gray-500 mt-1">People with access to {{ $company->name }}. The owner has full access.</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Invite --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 p-5" x-data="{ role: @js(old('role', 'staff')) }">
                <h2 class="text-sm font-bold text-gray-900">Invite a team member</h2>
                <p class="text-xs text-gray-500 mt-0.5">Each member logs in with their own Apna Invoice account.</p>
                @if ($errors->any())
                    <div class="mt-3 p-3 rounded bg-danger-50 border border-danger-200 text-danger-800 text-sm" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
                @endif
                <form method="POST" action="{{ route('team.invite') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="invite-email" class="text-xs font-semibold text-gray-600">Email address</label>
                        <input id="invite-email" type="email" name="email" required value="{{ old('email') }}" placeholder="name@example.com"
                               class="mt-1 block w-full sm:w-96 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    <fieldset>
                        <legend class="text-xs font-semibold text-gray-600">Role</legend>
                        <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-3">
                            @foreach ($roles as $key => $r)
                                <label class="block p-3 rounded-xl ring-1 cursor-pointer transition" :class="role === '{{ $key }}' ? 'ring-2 ring-brand-600 bg-brand-50' : 'ring-gray-200 hover:bg-gray-50'">
                                    <input type="radio" name="role" value="{{ $key }}" x-model="role" class="sr-only">
                                    <span class="block text-sm font-bold text-gray-900">{{ $r['label'] }}</span>
                                    <span class="block text-xs text-gray-600 mt-1 leading-snug">{{ $r['help'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <button class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-md text-sm font-semibold">Send invitation</button>
                </form>
            </section>

            {{-- Members --}}
            <section class="bg-white rounded-2xl shadow-card ring-1 ring-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">Team members <span class="text-gray-500 font-normal">· {{ $members->count() }}</span></h2>
                </div>
                @if ($members->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-gray-500">No team members yet.</div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($members as $m)
                            @php $link = route('team.join', $m->token); @endphp
                            <li class="px-5 py-4 flex flex-col md:flex-row md:items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">{{ $m->user?->name ?? $m->email }}</div>
                                    <div class="text-xs text-gray-500 truncate">
                                        @if ($m->user?->name)
                                            {{ $m->email }} ·
                                        @endif
                                        @if ($m->isAccepted())
                                            <span class="text-money-700 font-semibold">Joined {{ $m->accepted_at->format('d M Y') }}</span>
                                        @else
                                            <span class="text-accent-800 font-semibold">Invitation pending</span>
                                        @endif
                                    </div>
                                    @unless ($m->isAccepted())
                                        <div class="mt-2 flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                                            <input type="text" readonly value="{{ $link }}" class="w-full sm:w-80 text-xs font-mono border-gray-200 bg-gray-50 rounded" aria-label="Invite link" @focus="$el.select()">
                                            <button type="button" @click="navigator.clipboard?.writeText(@js($link)); copied = true; setTimeout(() => copied = false, 1500)"
                                                    class="px-2.5 py-1 rounded bg-gray-100 hover:bg-gray-200 text-xs font-semibold" x-text="copied ? 'Copied' : 'Copy link'"></button>
                                            <a href="https://wa.me/?text={{ rawurlencode('Join ' . $company->name . ' on Apna Invoice: ' . $link) }}" target="_blank" rel="noopener"
                                               class="px-2.5 py-1 rounded bg-[#0f7540] hover:bg-[#0c5f34] text-white text-xs font-semibold">Share on WhatsApp</a>
                                        </div>
                                    @endunless
                                </div>
                                <form method="POST" action="{{ route('team.update', $m) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <select name="role" onchange="this.form.submit()" aria-label="Role" class="border-gray-300 rounded-md shadow-sm text-sm">
                                        @foreach ($roles as $key => $r)
                                            <option value="{{ $key }}" @selected($m->role === $key)>{{ $r['label'] }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <x-confirm-form :action="route('team.destroy', $m)" method="DELETE" title="Remove {{ $m->email }}?"
                                                message="They lose access immediately. Entries they made are kept." confirmLabel="Remove" confirmClass="bg-danger-600 hover:bg-danger-700" tone="danger">
                                    <button type="button" class="text-sm text-danger-600 hover:underline">Remove</button>
                                </x-confirm-form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <p class="text-xs text-gray-500">Changes by team members are recorded in the <a href="{{ route('profile.activity') }}" class="underline">activity log</a> under their name.</p>
        </div>
    </div>
</x-app-layout>
