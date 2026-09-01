<x-app-layout title="Blog admin">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">Blogs · Admin</h1>
            <a href="{{ route('admin.blog.create') }}" class="inline-flex items-center gap-1 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-md shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New post
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white shadow sm:rounded-lg">
                <form method="GET" class="p-4 border-b flex flex-wrap gap-3 items-center">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by title or slug" class="border-gray-300 rounded-md shadow-sm w-full sm:w-80">
                    <select name="status" class="border-gray-300 rounded-md shadow-sm" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="published" @selected(request('status') === 'published')>Published</option>
                    </select>
                    <button class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 bg-brand-700 hover:bg-brand-800 text-white rounded text-sm">Filter</button>
                    @if (request('search') || request('status'))
                        <a href="{{ route('admin.blog.index') }}" class="text-gray-500 text-sm">clear</a>
                    @endif
                </form>

                @if ($posts->isEmpty())
                    <x-empty-state
                        icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        :title="request('search') || request('status') ? 'No posts match' : 'No blog posts yet'"
                        :description="request('search') || request('status') ? 'Try a different filter.' : 'Write your first article to start driving SEO traffic. Use clear titles, meta descriptions, and keywords.'"
                        :actionHref="request('search') || request('status') ? route('admin.blog.index') : route('admin.blog.create')"
                        :actionLabel="request('search') || request('status') ? 'Clear filters' : 'Write first post'"
                    />
                @else
                    <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Title</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Published</th>
                                <th class="px-4 py-3 text-right">Views</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($posts as $p)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.blog.edit', $p) }}" class="font-semibold text-gray-900 hover:text-brand-700">{{ $p->title }}</a>
                                        <div class="text-xs text-gray-500 font-mono mt-0.5">/blog/{{ $p->slug }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($p->isPublished())
                                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-money-100 text-money-800">Published</span>
                                        @elseif ($p->isScheduled())
                                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-brand-100 text-brand-800">Scheduled</span>
                                        @else
                                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">Draft</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $p->published_at?->format('d M Y') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-sm">{{ number_format($p->view_count) }}</td>
                                    <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                        @if ($p->isPublished())
                                            <a href="{{ route('blog.show', $p->slug) }}" target="_blank" class="text-gray-600 hover:underline">View</a>
                                            <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                                        @endif
                                        <a href="{{ route('admin.blog.edit', $p) }}" class="text-brand-700 hover:underline">Edit</a>
                                        <span class="text-gray-400 mx-1" aria-hidden="true">·</span>
                                        <form method="POST" action="{{ route('admin.blog.toggle', $p) }}" class="inline">
                                            @csrf
                                            <button class="text-gray-600 hover:underline">{{ $p->isDraft() ? 'Publish' : 'Unpublish' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    <div class="p-4">{{ $posts->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
