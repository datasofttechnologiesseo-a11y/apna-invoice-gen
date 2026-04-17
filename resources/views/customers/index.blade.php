<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Customers') }}</h2>
            <a href="{{ route('customers.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">+ New customer</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded">
                    <ul class="list-disc pl-6">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg">
                <form method="GET" class="p-4 border-b">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email" class="w-full md:w-80 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </form>

                @if ($customers->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        No customers yet. <a href="{{ route('customers.create') }}" class="text-indigo-600 hover:underline">Add your first customer</a>.
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">GSTIN</th>
                                <th class="px-4 py-3">State</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($customers as $c)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $c->name }}</td>
                                    <td class="px-4 py-3 text-gray-600 font-mono text-sm">{{ $c->gstin ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $c->state?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600 text-sm">{{ $c->email }}<br><span class="text-gray-400">{{ $c->phone }}</span></td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <a href="{{ route('customers.edit', $c) }}" class="text-indigo-600 hover:underline text-sm">Edit</a>
                                        <form method="POST" action="{{ route('customers.destroy', $c) }}" class="inline" onsubmit="return confirm('Delete this customer?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline text-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="p-4">{{ $customers->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
