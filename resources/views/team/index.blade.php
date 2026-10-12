<x-app-layout title="Team">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'All settings', 'href' => route('settings.index')],
            ['label' => 'Team'],
        ]" />
        <div>
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Team</h1>
            <p class="text-sm text-gray-500 mt-1">People with access to {{ $company->name }}. The owner has full access.</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            {{-- Invite --}}
            <section class="bg-white rounded-xl ring-1 ring-gray-200 p-5" x-data="{ role: @js(old('role', 'staff')) }">
                <h2 class="text-base font-semibold text-gray-900">Invite a team member</h2>
                <p class="text-sm text-gray-500 mt-0.5">Each member logs in with their own Apna Invoice account.</p>
                <form method="POST" action="{{ route('team.invite') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="invite-email" class="text-sm font-medium text-gray-700">Email address *</label>
                        <input id="invite-email" type="email" name="email" required value="{{ old('email') }}" placeholder="name@example.com"
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                               class="mt-1 block w-full sm:w-96 h-10 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-600 focus:ring-brand-600">
                        <x-input-error :messages="$errors->get('email')" for="email" class="mt-2" />
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Role</legend>
                        <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-3">
                            @foreach ($roles as $key => $r)
                                {{-- The radio is visually hidden; the card shows its focus ring. --}}
                                <label class="block p-3 rounded-xl ring-1 cursor-pointer transition has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-700"
                                       :class="role === '{{ $key }}' ? 'ring-2 ring-brand-600 bg-brand-50' : 'ring-gray-200 hover:bg-gray-50'">
                                    <input type="radio" name="role" value="{{ $key }}" x-model="role" class="sr-only">
                                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                        <span class="w-4 h-4 rounded-full border flex items-center justify-center shrink-0" :class="role === '{{ $key }}' ? 'border-brand-700' : 'border-gray-400'" aria-hidden="true">
                                            <span class="w-2 h-2 rounded-full bg-brand-700" x-show="role === '{{ $key }}'"></span>
                                        </span>
                                        {{ $r['label'] }}
                                    </span>
                                    <span class="block text-xs text-gray-600 mt-1 leading-snug">{{ $r['help'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('role')" for="role" class="mt-2" />
                    </fieldset>
                    <x-primary-button>Send invitation</x-primary-button>
                </form>
            </section>

            {{-- Members --}}
            <section class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Team members <span class="text-gray-500 font-normal">· {{ $members->count() }}</span></h2>
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
                                            <input type="text" readonly value="{{ $link }}" class="w-full sm:w-80 h-9 text-xs font-mono border-gray-200 bg-gray-50 rounded-md" aria-label="Invite link" @focus="$el.select()">
                                            <button type="button" @click="copyText(@js($link)).then(ok => { if (ok) { copied = true; setTimeout(() => copied = false, 1500) } })"
                                                    class="inline-flex items-center h-9 px-3 rounded-md bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-sm font-semibold text-gray-800" x-text="copied ? 'Copied' : 'Copy link'">Copy link</button>
                                            <a href="https://wa.me/?text={{ rawurlencode('Join ' . $company->name . ' on Apna Invoice: ' . $link) }}" target="_blank" rel="noopener"
                                               class="inline-flex items-center h-9 px-3 rounded-md bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-sm font-semibold text-[#0f7540]">Share on WhatsApp</a>
                                            <form method="POST" action="{{ route('team.invite') }}">
                                                @csrf
                                                <input type="hidden" name="resend" value="{{ $m->id }}">
                                                <button type="submit" class="inline-flex items-center h-9 px-3 rounded-md bg-white ring-1 ring-inset ring-gray-300 hover:bg-gray-50 text-sm font-semibold text-gray-800">Resend invite</button>
                                            </form>
                                        </div>
                                    @endunless
                                </div>
                                <form method="POST" action="{{ route('team.update', $m) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <select name="role" onchange="this.form.submit()" aria-label="Role for {{ $m->email }}" class="h-10 border-gray-300 rounded-md shadow-sm text-sm">
                                        @foreach ($roles as $key => $r)
                                            <option value="{{ $key }}" @selected($m->role === $key)>{{ $r['label'] }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <x-confirm-form :action="route('team.destroy', $m)" method="DELETE" title="Remove {{ $m->email }}?"
                                                message="They lose access immediately. Entries they made are kept." confirmLabel="Remove" confirmClass="bg-danger-600 hover:bg-danger-700" tone="danger">
                                    <button type="button" class="inline-flex items-center h-10 px-2 text-sm text-danger-600 hover:underline">Remove</button>
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
