<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 h-10 px-4 bg-danger-600 border border-transparent rounded-md font-semibold text-sm text-white whitespace-nowrap hover:bg-danger-700 active:bg-danger-800 focus:outline-none focus:ring-2 focus:ring-danger-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
