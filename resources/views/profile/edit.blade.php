<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="bg-brand-50 ring-1 ring-brand-100 shadow-sm sm:rounded-lg p-4 sm:p-6">
                <div class="max-w-xl flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-gray-900">Data &amp; Privacy</h2>
                        <p class="text-sm text-gray-600 mt-1">Manage consent, export your data, and review your DPDP rights.</p>
                    </div>
                    <a href="{{ route('profile.privacy') }}" class="shrink-0 inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold bg-white ring-1 ring-brand-200 text-brand-700 hover:bg-brand-100 transition">Open</a>
                </div>
            </div>

            <div id="delete-account" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg scroll-mt-24">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
