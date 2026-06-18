<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">{{ __('Data & Privacy') }}</h2>
            <p class="text-sm text-gray-500 mt-1">Your rights under the Digital Personal Data Protection Act, 2023.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            {{-- Marketing consent --}}
            <section class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900">Communication preferences</h3>
                <p class="text-sm text-gray-500 mt-1">
                    Account, security and billing emails are essential and are always sent. Marketing
                    emails (product news and offers) are optional — you can withdraw consent any time.
                </p>

                <form method="POST" action="{{ route('profile.privacy.marketing') }}" class="mt-4 flex items-center justify-between gap-4">
                    @csrf
                    <div class="text-sm">
                        <span class="font-medium text-gray-900">Marketing emails</span>
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $marketingConsent ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $marketingConsent ? 'Opted in' : 'Opted out' }}
                        </span>
                    </div>
                    <input type="hidden" name="marketing_opt_in" value="{{ $marketingConsent ? 0 : 1 }}">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-semibold {{ $marketingConsent ? 'bg-gray-100 text-gray-800 hover:bg-gray-200' : 'bg-brand-600 text-white hover:bg-brand-700' }} transition">
                        {{ $marketingConsent ? 'Withdraw consent' : 'Opt in' }}
                    </button>
                </form>
            </section>

            {{-- Right to access --}}
            <section class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900">Access &amp; portability</h3>
                <p class="text-sm text-gray-500 mt-1">
                    Download a complete copy of your account, invoices, customers and products as a ZIP
                    archive — yours to keep or move elsewhere.
                </p>
                <a href="{{ route('backup.index') }}"
                   class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 text-gray-800 hover:bg-gray-200 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export my data
                </a>
            </section>

            {{-- Consent history --}}
            <section class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900">Consent history</h3>
                <p class="text-sm text-gray-500 mt-1">Every consent decision, time-stamped with the policy version in force. This log is append-only.</p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-gray-400 border-b border-gray-200">
                                <th class="py-2 pr-4 font-semibold">Type</th>
                                <th class="py-2 pr-4 font-semibold">Decision</th>
                                <th class="py-2 pr-4 font-semibold">Version</th>
                                <th class="py-2 pr-4 font-semibold">When</th>
                                <th class="py-2 font-semibold">Where</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($consents as $c)
                                <tr>
                                    <td class="py-2 pr-4 capitalize text-gray-900">{{ str_replace('_', ' ', $c->consent_type) }}</td>
                                    <td class="py-2 pr-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $c->given ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700' }}">
                                            {{ $c->given ? 'Given' : 'Withdrawn' }}
                                        </span>
                                    </td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $c->policy_version }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $c->created_at?->format('d M Y, H:i') }}</td>
                                    <td class="py-2 text-gray-500">{{ str_replace('_', ' ', $c->context) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-center text-gray-400">No consent events recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Right to erasure --}}
            <section class="bg-white shadow sm:rounded-lg p-6 border-l-4 border-rose-300">
                <h3 class="font-semibold text-gray-900">Delete my account</h3>
                <p class="text-sm text-gray-500 mt-1">
                    Permanently delete your account and personal data. Note: where Indian GST law requires
                    invoice records to be kept (up to {{ intdiv(config('legal.gst_retention_months', 72), 12) }} years),
                    those records are retained in de-identified form and automatically purged once the retention
                    period ends — everything else is erased immediately.
                </p>
                <a href="{{ route('profile.edit') }}#delete-account"
                   class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-rose-600 text-white hover:bg-rose-700 transition">
                    Continue to delete account
                </a>
            </section>

            {{-- Grievance officer --}}
            <section class="bg-gray-50 ring-1 ring-gray-200 sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900">Grievance Officer</h3>
                <p class="text-sm text-gray-500 mt-1">
                    Operated by {{ config('legal.operator') }}. For any data protection concern, contact our
                    Grievance Officer — we respond within {{ config('legal.request_response_days', 30) }} days.
                </p>
                <dl class="mt-3 text-sm text-gray-700 space-y-1">
                    <div><dt class="inline font-medium">Name:</dt> <dd class="inline">{{ config('legal.grievance.name') }}</dd></div>
                    <div><dt class="inline font-medium">Email:</dt>
                        <dd class="inline"><a class="text-brand-600 hover:underline" href="mailto:{{ config('legal.grievance.email') }}">{{ config('legal.grievance.email') }}</a></dd>
                    </div>
                </dl>
                <p class="text-xs text-gray-400 mt-3">
                    See our full <a href="{{ route('pages.privacy') }}" class="underline">Privacy Policy</a> for details.
                </p>
            </section>
        </div>
    </div>
</x-app-layout>
