<x-app-layout :title="'Import ' . $importer->title()">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Import {{ $importer->title() }}</h1>
            <a href="{{ $importer->listRoute() }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to {{ $importer->title() }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            {{-- Three steps, numbered, so it is obvious nothing is saved until step 3. --}}
            <ol class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                @foreach ([['1', 'Download the template', 'Excel sheet with the column headings and a sample row.'], ['2', 'Fill it in and upload', 'Your own Excel or CSV file also works if the headings match.'], ['3', 'Check the preview', 'Nothing is saved until you confirm. Existing records are not changed.']] as [$n, $t, $d])
                    <li class="flex gap-3 bg-white rounded-xl ring-1 ring-gray-200 p-4">
                        <span class="shrink-0 w-7 h-7 rounded-full bg-brand-700 text-white font-bold flex items-center justify-center text-sm">{{ $n }}</span>
                        <span><span class="block font-semibold text-gray-900">{{ $t }}</span><span class="block text-xs text-gray-500 mt-0.5">{{ $d }}</span></span>
                    </li>
                @endforeach
            </ol>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm p-6 space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold text-gray-900">Template</div>
                        <div class="text-xs text-gray-500">Includes an Instructions sheet for every column.</div>
                    </div>
                    <a href="{{ route('imports.template', $importer->key()) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white ring-1 ring-brand-300 text-brand-800 hover:bg-brand-50 text-sm font-semibold rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download Excel template
                    </a>
                </div>

                <form method="POST" action="{{ route('imports.preview', $importer->key()) }}" enctype="multipart/form-data" class="border-t border-gray-100 pt-5 space-y-3" x-data="{ name: '' }">
                    @csrf
                    <label for="file" class="block font-semibold text-gray-900">Upload your file</label>
                    <label for="file" class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 hover:border-brand-400 rounded-xl px-4 py-8 cursor-pointer text-center transition">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span class="text-sm text-gray-700" x-text="name || 'Choose an Excel (.xlsx) or CSV file'"></span>
                        <span class="text-xs text-gray-500">Up to {{ number_format(\App\Imports\Importer::MAX_ROWS) }} rows · 5 MB</span>
                        <input id="file" name="file" type="file" accept=".xlsx,.csv,.ods" required class="sr-only" @change="name = $event.target.files[0]?.name || ''">
                    </label>
                    @error('file')<p class="text-sm text-danger-700">{{ $message }}</p>@enderror
                    <div class="flex justify-end">
                        <x-primary-button>Upload and preview</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-900 text-sm">Columns</div>
                <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-left text-xs uppercase tracking-wider">
                        <tr><th class="px-4 py-2">Column heading</th><th class="px-4 py-2">Required</th><th class="px-4 py-2">What to enter</th><th class="px-4 py-2">Example</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($importer->fields() as $f)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $f['label'] }}</td>
                                <td class="px-4 py-2">{!! ! empty($f['required']) ? '<span class="text-danger-700 font-semibold">Yes</span>' : '<span class="text-gray-500">No</span>' !!}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $f['help'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $f['example'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
