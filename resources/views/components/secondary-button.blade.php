<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 h-10 px-4 bg-white border-0 ring-1 ring-inset ring-gray-300 rounded-md font-semibold text-sm text-gray-800 whitespace-nowrap hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
