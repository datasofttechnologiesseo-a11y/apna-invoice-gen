<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 h-10 px-4 bg-brand-700 border border-transparent rounded-md font-semibold text-sm text-white whitespace-nowrap hover:bg-brand-800 focus:bg-brand-800 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150 shadow-sm']) }}>
    {{ $slot }}
</button>
