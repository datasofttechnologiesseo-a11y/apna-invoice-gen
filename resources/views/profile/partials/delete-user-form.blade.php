<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Delete account') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Your account and personal data will be permanently deleted. Download a backup first if you need to keep any records.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete account') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable aria-labelledby="confirm-user-deletion-title">
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 id="confirm-user-deletion-title" class="text-lg font-medium text-gray-900">
                {{ __('Delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('This cannot be undone. Enter your password to confirm.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-password-input
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    :required="false"
                    class="w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Delete account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
