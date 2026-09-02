<x-app-layout title="Edit post">
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Blogs · Admin', 'href' => route('admin.blog.index')],
            ['label' => $post->exists ? $post->title : 'New post'],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
                {{ $post->exists ? 'Edit post' : 'New post' }}
            </h1>
            @if ($post->exists && $post->isPublished())
                <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Preview live
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            @endif
        </div>
    </x-slot>

    @php
        $titleVal = old('title', $post->title);
        $slugVal = old('slug', $post->slug);
        $excerptVal = old('excerpt', $post->excerpt);
        $bodyVal = old('body', $post->body);
        $metaTitleVal = old('meta_title', $post->meta_title);
        $metaDescVal = old('meta_description', $post->meta_description);
        $metaKeywordsVal = old('meta_keywords', $post->meta_keywords);
        $statusVal = old('status', $post->status ?: 'draft');
        $publishedAtVal = old('published_at', $post->published_at?->format('Y-m-d\TH:i'));
        $altVal = old('featured_image_alt', $post->featured_image_alt);
    @endphp

    <div class="py-8" x-data="postEditor({
        initialTitle: @js($titleVal ?? ''),
        initialSlug: @js($slugVal ?? ''),
        initialBody: @js($bodyVal ?? ''),
        initialMetaTitle: @js($metaTitleVal ?? ''),
        initialMetaDescription: @js($metaDescVal ?? ''),
        initialKeywords: @js($metaKeywordsVal ?? ''),
        slugLocked: @js((bool) ($post->slug ?? null)),
    })">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @if ($errors->any())
                <div class="p-4 bg-danger-50 border border-danger-200 text-danger-800 rounded-lg">
                    <ul class="list-disc pl-6 text-sm">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}"
                  enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
                @csrf
                @if ($post->exists) @method('PATCH') @endif

                {{-- ─── MAIN COLUMN - title, slug, body ──────────────────── --}}
                <div class="space-y-6">

                    {{-- Title + auto-slug --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 sm:p-6 space-y-4">
                        <div>
                            <x-input-label for="title" value="Title *" />
                            <input id="title" name="title" type="text" required maxlength="200"
                                   x-model="title" @input="onTitleChange"
                                   placeholder="e.g. How to file GSTR-1 from your invoices in 5 minutes"
                                   class="mt-1 block w-full text-2xl font-display font-bold border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                   value="{{ $titleVal }}">
                            <div class="mt-1 text-[10px] text-gray-500 flex items-center justify-between">
                                <span>The H1 on the article + the default <code>&lt;title&gt;</code> tag.</span>
                                <span x-text="title.length + ' / 200'"></span>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <x-input-label for="slug" value="Slug" />
                                <label class="flex items-center gap-1.5 text-[11px] text-gray-500 cursor-pointer">
                                    <input type="checkbox" x-model="slugLocked" class="rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                                    Lock slug (manual)
                                </label>
                            </div>
                            <div class="mt-1 flex items-center gap-1 font-mono text-sm">
                                <span class="px-3 py-2 bg-gray-100 text-gray-600 rounded-l-md border border-r-0 border-gray-300">/blog/</span>
                                <input id="slug" name="slug" type="text" pattern="[a-z0-9-]+" maxlength="220"
                                       x-model="slug" :readonly="!slugLocked"
                                       :class="slugLocked ? 'bg-white' : 'bg-gray-50'"
                                       class="flex-1 block w-full border-gray-300 rounded-r-md shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                       value="{{ $slugVal }}">
                            </div>
                            <p class="mt-1 text-[10px] text-gray-500">Auto-generated from the title. Tick "Lock slug" to override. Lowercase letters, digits, dashes only.</p>
                        </div>

                        <div>
                            <x-input-label for="excerpt" value="Excerpt (shown on the blog index card)" />
                            <textarea id="excerpt" name="excerpt" rows="2" maxlength="500"
                                      placeholder="One-two sentences summarising the post. Leave blank to auto-generate from the body."
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">{{ $excerptVal }}</textarea>
                        </div>
                    </div>

                    {{-- Body editor: toolbar + textarea + AJAX preview modal.
                         The toolbar inserts markdown around the user's selection
                         instead of forcing them to memorise the syntax. --}}
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-5 sm:px-6 py-3 border-b flex items-center justify-between gap-3 flex-wrap">
                            <div>
                                <h2 class="font-medium text-gray-900">Body</h2>
                                <p class="text-[11px] text-gray-500">Markdown - <code class="bg-gray-100 px-1 rounded">## Heading</code>, <code class="bg-gray-100 px-1 rounded">**bold**</code>, or use the toolbar. The post title is the page's H1; <code class="bg-gray-100 px-1 rounded">#</code> in the body renders as a section heading. <a href="https://www.markdownguide.org/cheat-sheet/" target="_blank" rel="noopener" class="text-brand-700 hover:underline">Cheat sheet</a></p>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-600">
                                <span><strong x-text="wordCount"></strong> words</span>
                                <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                <span><strong x-text="readingMinutes"></strong> min read</span>
                                {{-- Autosave indicator - set to "Saving…" then "Saved {time}"
                                     while autosaving to localStorage. Restored on page reload. --}}
                                <span class="w-1 h-1 rounded-full bg-gray-300" x-show="autosaveLabel" x-cloak></span>
                                <span x-show="autosaveLabel" x-cloak class="text-money-700" x-text="autosaveLabel"></span>
                            </div>
                        </div>

                        {{-- Formatting toolbar - clicking a button wraps the textarea
                             selection in the matching markdown. Keyboard friendly:
                             still works while the textarea is focused (mousedown is on
                             the button so the textarea selection is preserved). --}}
                        <div class="px-3 py-2 border-b bg-gray-50 flex items-center gap-0.5 flex-wrap text-sm">
                            <button type="button" @mousedown.prevent="format('bold')" title="Bold (Ctrl+B)"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900 font-bold">B</button>
                            <button type="button" @mousedown.prevent="format('italic')" title="Italic (Ctrl+I)"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900 italic">I</button>
                            <span class="w-px h-5 bg-gray-300 mx-1"></span>
                            <button type="button" @mousedown.prevent="format('h2')" title="Heading 2"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900 font-bold text-base">H2</button>
                            <button type="button" @mousedown.prevent="format('h3')" title="Heading 3"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900 font-bold text-sm">H3</button>
                            <span class="w-px h-5 bg-gray-300 mx-1"></span>
                            <button type="button" @mousedown.prevent="format('link')" title="Link (Ctrl+K)"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            </button>
                            {{-- Image - clicking opens a hidden file picker. On choose,
                                 we upload via AJAX and insert ![](url) at the caret.
                                 No prompt, no placeholder URL nonsense - the writer
                                 picks a file, sees their image inline. --}}
                            <button type="button" @click.prevent="$refs.bodyImagePicker.click()"
                                    :disabled="imageUploading"
                                    title="Insert image (upload)"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900 disabled:opacity-50 disabled:cursor-wait">
                                <svg x-show="!imageUploading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <svg x-show="imageUploading" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                            </button>
                            <input type="file" x-ref="bodyImagePicker" class="hidden"
                                   accept="image/jpeg,image/png,image/webp,image/gif"
                                   @change="onBodyImagePick($event)">
                            <span class="w-px h-5 bg-gray-300 mx-1"></span>
                            <button type="button" @mousedown.prevent="format('ul')" title="Bulleted list"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </button>
                            <button type="button" @mousedown.prevent="format('ol')" title="Numbered list"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900 text-xs font-mono">1.</button>
                            <button type="button" @mousedown.prevent="format('quote')" title="Blockquote"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                            </button>
                            <button type="button" @mousedown.prevent="format('code')" title="Inline code"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900 font-mono text-xs">&lt;/&gt;</button>
                            <button type="button" @mousedown.prevent="format('codeblock')" title="Code block"
                                    class="px-2.5 py-1.5 rounded hover:bg-white text-gray-700 hover:text-gray-900 font-mono text-xs">```</button>

                            <div class="flex-1"></div>

                            {{-- Preview button - opens a modal rendering the markdown
                                 via the same server-side renderer as production. --}}
                            <button type="button" @click="openPreview()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded font-semibold text-xs transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Preview
                            </button>
                        </div>

                        <textarea id="body" name="body" x-ref="bodyTextarea" rows="22"
                                  x-model="body" @input="updateBodyStats(); scheduleAutosave()"
                                  @keydown="onKeydown($event)"
                                  @paste="onPaste($event)"
                                  placeholder="## Introduction&#10;&#10;Write your post here…&#10;&#10;Tip: Paste an image directly from your clipboard (Ctrl+V) - it'll auto-upload."
                                  class="block w-full border-0 focus:ring-0 font-mono text-sm leading-relaxed p-5 sm:p-6 resize-y">{{ $bodyVal }}</textarea>
                    </div>

                    <div x-show="pasteNotice" x-cloak x-transition
                         class="bg-money-50 border border-money-200 text-money-800 rounded-lg px-4 py-3 text-sm flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span x-text="pasteNotice"></span>
                    </div>

                    {{-- Restore-from-localStorage banner - shown if a newer autosave
                         exists than what the server gave us. One-click to restore. --}}
                    <div x-show="autosaveAvailable" x-cloak
                         class="bg-accent-50 border border-accent-200 text-accent-900 rounded-lg p-4 flex items-center justify-between gap-3">
                        <div class="text-sm">
                            <strong>Unsaved draft from <span x-text="autosaveAge"></span></strong>
                            found on this browser. Restore it?
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="discardAutosave()" class="text-xs text-accent-800 hover:underline px-2 py-1">Discard</button>
                            <button type="button" @click="restoreAutosave()" class="px-3 py-1.5 bg-accent-600 hover:bg-accent-700 text-white text-xs font-semibold rounded">Restore</button>
                        </div>
                    </div>

                    {{-- Preview modal - full-screen iframe-style overlay with the
                         rendered markdown styled the same way the public blog renders it. --}}
                    <div x-show="previewOpen" x-cloak
                         @keydown.escape.window="previewOpen = false"
                         class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4">
                        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden" @click.outside="previewOpen = false">
                            <div class="px-5 py-3 border-b flex items-center justify-between bg-gray-50">
                                <div>
                                    <h2 class="font-semibold text-gray-900">Live preview</h2>
                                    <p class="text-xs text-gray-500">Same renderer as the public blog. Press Esc to close.</p>
                                </div>
                                <button type="button" @click="previewOpen = false"
                                        class="text-gray-500 hover:text-gray-700 p-1" aria-label="Close preview">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="overflow-y-auto p-8">
                                <article class="prose prose-lg max-w-none
                                                [&_h2]:font-bold [&_h2]:text-3xl [&_h2]:mt-10 [&_h2]:mb-4
                                                [&_h3]:font-bold [&_h3]:text-2xl [&_h3]:mt-8 [&_h3]:mb-3
                                                [&_p]:my-4 [&_p]:leading-relaxed
                                                [&_a]:text-brand-700 [&_a]:underline
                                                [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-4 [&_ul]:space-y-2
                                                [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-4 [&_ol]:space-y-2
                                                [&_blockquote]:border-l-4 [&_blockquote]:border-brand-300 [&_blockquote]:pl-4 [&_blockquote]:italic [&_blockquote]:my-4
                                                [&_code]:bg-gray-100 [&_code]:px-1.5 [&_code]:rounded [&_code]:text-sm
                                                [&_pre]:bg-gray-900 [&_pre]:text-gray-100 [&_pre]:p-4 [&_pre]:rounded-lg [&_pre]:overflow-x-auto
                                                [&_img]:rounded-lg [&_img]:my-4"
                                         x-html="previewHtml || '<p class=\'text-gray-500 text-center py-20\'>Loading preview…</p>'"></article>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── SIDEBAR - publish, SEO, images ───────────────────── --}}
                <aside class="space-y-6">

                    {{-- Publish controls --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 space-y-4">
                        <h2 class="font-display font-bold text-gray-900">Publish</h2>
                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" x-model="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">
                                <option value="draft" @selected($statusVal === 'draft')>Draft (only you can see)</option>
                                <option value="published" @selected($statusVal === 'published')>Published (live on /blog)</option>
                            </select>
                        </div>
                        <div x-show="status === 'published'" x-cloak>
                            <x-input-label for="published_at" value="Publish at" />
                            <input id="published_at" name="published_at" type="datetime-local" value="{{ $publishedAtVal }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">
                            <p class="mt-1 text-[10px] text-gray-500">Leave blank to publish immediately. Set a future date to schedule.</p>
                        </div>
                        <button type="submit" class="w-full inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-md font-semibold shadow-sm transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span x-text="status === 'published' ? 'Save & publish' : 'Save draft'"></span>
                        </button>

                        @if ($post->exists)
                            <x-confirm-form
                                :action="route('admin.blog.destroy', $post)"
                                method="DELETE"
                                title="Delete this post?"
                                message="This permanently removes the post and any uploaded images. This cannot be undone."
                                confirm-label="Delete post"
                                confirm-class="bg-danger-600 hover:bg-danger-700"
                                tone="danger">
                                <button type="button" class="w-full inline-flex items-center justify-center min-h-[40px] px-4 py-2 bg-white ring-1 ring-danger-300 hover:bg-danger-50 text-danger-700 rounded-md text-sm font-medium transition">
                                    Delete post
                                </button>
                            </x-confirm-form>
                        @endif
                    </div>

                    {{-- SEO panel - the heart of the "smart" editor --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="font-display font-bold text-gray-900">SEO</h2>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                  :class="seoScore.tone === 'good' ? 'bg-money-100 text-money-800' :
                                          seoScore.tone === 'warn' ? 'bg-accent-100 text-accent-800' :
                                          'bg-danger-100 text-danger-800'">
                                <span x-text="seoScore.label"></span>
                            </span>
                        </div>

                        <div>
                            <x-input-label for="meta_title" value="Meta title" />
                            <input id="meta_title" name="meta_title" type="text" maxlength="70"
                                   x-model="metaTitle"
                                   placeholder="Falls back to the post title"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                   value="{{ $metaTitleVal }}">
                            <div class="mt-1 text-[10px] flex items-center justify-between"
                                 :class="metaTitleEffective.length > 60 ? 'text-accent-700' : 'text-gray-500'">
                                <span>Google truncates around 60 chars.</span>
                                <span><span x-text="metaTitleEffective.length"></span> / 60</span>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="meta_description" value="Meta description" />
                            <textarea id="meta_description" name="meta_description" rows="3" maxlength="200"
                                      x-model="metaDescription"
                                      placeholder="The text that appears under your title in Google search results."
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600">{{ $metaDescVal }}</textarea>
                            <div class="mt-1 text-[10px] flex items-center justify-between"
                                 :class="metaDescription.length > 160 ? 'text-accent-700' : metaDescription.length < 80 ? 'text-accent-700' : 'text-money-700'">
                                <span x-text="metaDescription.length === 0 ? 'Recommended: 150-160 chars' : metaDescription.length > 160 ? 'Too long - Google will truncate' : metaDescription.length < 80 ? 'Too short - aim for 150-160' : 'Looks good'"></span>
                                <span><span x-text="metaDescription.length"></span> / 160</span>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="meta_keywords" value="Keywords" />
                            <input id="meta_keywords" name="meta_keywords" type="text" maxlength="255"
                                   x-model="metaKeywords"
                                   placeholder="GST invoice, HSN SAC, GSTR-1"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                   value="{{ $metaKeywordsVal }}">
                            <p class="mt-1 text-[10px] text-gray-500">Comma-separated. Render as topic tags below the article and feed Bing/Yandex search engines.</p>
                        </div>

                        <div class="pt-3 border-t border-gray-100 space-y-2 text-[11px]">
                            <div class="font-bold uppercase tracking-wider text-gray-500">Search preview</div>
                            <div class="rounded border border-gray-200 p-3 bg-gray-50">
                                <div class="text-[12px] text-gray-600 truncate">{{ url('/blog/') }}/<span x-text="slug || 'your-post-slug'"></span></div>
                                <div class="text-[14px] font-medium text-brand-700 leading-tight mt-0.5 line-clamp-1" x-text="metaTitleEffective || 'Your post title'"></div>
                                <div class="text-[12px] text-gray-700 leading-snug mt-0.5 line-clamp-2" x-text="metaDescription || 'A 150-160 character description here helps your post stand out in Google.'"></div>
                            </div>
                        </div>

                        {{-- Live SEO checklist - every check Google actually rewards,
                             recomputed as you type. Green = done, hint tells you the fix. --}}
                        <div class="pt-3 border-t border-gray-100 space-y-1.5 text-[11px]">
                            <div class="font-bold uppercase tracking-wider text-gray-500">SEO checklist</div>
                            <template x-for="check in seoChecks" :key="check.label">
                                <div class="flex items-start gap-2 py-0.5">
                                    <span class="mt-0.5 inline-flex items-center justify-center w-4 h-4 rounded-full shrink-0"
                                          :class="check.ok ? 'bg-money-100 text-money-700' : 'bg-gray-100 text-gray-600'">
                                        <svg x-show="check.ok" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        <svg x-show="!check.ok" class="w-2 h-2" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <span :class="check.ok ? 'text-gray-700' : 'text-gray-900 font-medium'" x-text="check.label"></span>
                                        <span x-show="!check.ok && check.hint" class="block text-gray-500" x-text="check.hint"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Featured image - with instant preview the moment a file is
                         picked (not waiting until form submit). URL.createObjectURL
                         is revoked on the next pick to avoid memory leaks. --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 space-y-3">
                        <h2 class="font-display font-bold text-gray-900">Featured image</h2>
                        {{-- Preview: shows the picked-but-not-saved file first; falls back
                             to whatever's saved on the server. --}}
                        <template x-if="featuredPreview">
                            <img :src="featuredPreview" alt="New featured image preview"
                                 class="w-full rounded-lg ring-1 ring-gray-200 aspect-[16/9] object-cover">
                        </template>
                        @if ($post->featured_image_path)
                            <img x-show="!featuredPreview" src="{{ asset('storage/' . $post->featured_image_path) }}" alt="{{ $altVal }}"
                                 class="w-full rounded-lg ring-1 ring-gray-200 aspect-[16/9] object-cover">
                        @endif
                        <div>
                            <x-input-label for="featured_image" value="Upload (max 4 MB)" />
                            <input id="featured_image" name="featured_image" type="file" accept="image/jpeg,image/png,image/webp"
                                   @change="onFilePick($event, 'featured')"
                                   class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-brand-700 file:text-white file:font-semibold hover:file:bg-brand-800">
                            <p class="mt-1 text-[10px] text-gray-500">Recommended: 1200×675 (16:9), JPEG or WebP.</p>
                            <p x-show="featuredPreview" x-cloak class="mt-1 text-[10px] text-money-700 font-medium">✓ New image selected. Click Save to publish.</p>
                        </div>
                        <div>
                            <x-input-label for="featured_image_alt" value="Alt text (for SEO + accessibility)" />
                            <input id="featured_image_alt" name="featured_image_alt" type="text" maxlength="200"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                   value="{{ $altVal }}"
                                   placeholder="Describe what's in the image">
                        </div>
                    </div>

                    {{-- Social card override - same instant-preview pattern. --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 space-y-3">
                        <h2 class="font-display font-bold text-gray-900">Social-card image</h2>
                        <template x-if="ogPreview">
                            <img :src="ogPreview" alt="New OG image preview"
                                 class="w-full rounded-lg ring-1 ring-gray-200 aspect-[1200/630] object-cover">
                        </template>
                        @if ($post->og_image_path)
                            <img x-show="!ogPreview" src="{{ asset('storage/' . $post->og_image_path) }}" alt="OG override"
                                 class="w-full rounded-lg ring-1 ring-gray-200 aspect-[1200/630] object-cover">
                        @endif
                        <div>
                            <x-input-label for="og_image" value="Override (1200×630 ideal)" />
                            <input id="og_image" name="og_image" type="file" accept="image/jpeg,image/png,image/webp"
                                   @change="onFilePick($event, 'og')"
                                   class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-gray-700 file:text-white file:font-semibold hover:file:bg-gray-800">
                            <p class="mt-1 text-[10px] text-gray-500">Optional. Falls back to the featured image.</p>
                            <p x-show="ogPreview" x-cloak class="mt-1 text-[10px] text-money-700 font-medium">✓ New image selected. Click Save to publish.</p>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function slugify(s) {
            return (s || '')
                .toLowerCase()
                .normalize('NFKD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .substring(0, 220);
        }

        function postEditor({ initialTitle, initialSlug, initialBody, initialMetaTitle, initialMetaDescription, initialKeywords, slugLocked }) {
            // localStorage key - namespaced per post-id so different posts don't
            // clobber each other's drafts on the same browser.
            const postId = @json($post->id ?? 'new');
            const autosaveKey = 'apnainvoice.blog-draft.' + postId;

            return {
                title: initialTitle,
                slug: initialSlug,
                body: initialBody,
                metaTitle: initialMetaTitle,
                metaDescription: initialMetaDescription,
                metaKeywords: initialKeywords || '',
                status: document.getElementById('status')?.value || 'draft',
                slugLocked,                              // when true, manual slug entry; when false, auto-derive from title
                wordCount: 0,
                readingMinutes: 1,

                // Preview state - opens a modal with server-rendered HTML.
                previewOpen: false,
                previewHtml: '',

                // Autosave state - stored in localStorage, restored on next load
                // if newer than the server's saved copy.
                autosaveLabel: '',
                autosaveTimer: null,
                autosaveAvailable: false,
                autosaveAge: '',
                _pendingAutosave: null,

                // Instant-preview of picked-but-not-saved featured / OG images.
                // null until a file is chosen; then a blob: URL via createObjectURL.
                featuredPreview: null,
                ogPreview: null,
                // Body-image upload state - disables the toolbar Image button
                // while a request is in flight (prevents double-uploads).
                imageUploading: false,
                // Confirmation that a rich-text paste kept its structure, so
                // the author knows the conversion happened rather than
                // wondering whether the formatting survived.
                pasteNotice: '',

                init() {
                    this.updateBodyStats();
                    this.checkAutosave();
                },

                onTitleChange() {
                    if (! this.slugLocked) {
                        this.slug = slugify(this.title);
                    }
                    this.scheduleAutosave();
                },

                updateBodyStats() {
                    const plain = (this.body || '').replace(/[#*_`>\[\]\(\)]/g, ' ');
                    const words = plain.trim() ? plain.trim().split(/\s+/).length : 0;
                    this.wordCount = words;
                    this.readingMinutes = Math.max(1, Math.ceil(words / 200));
                },

                get metaTitleEffective() {
                    return this.metaTitle || this.title;
                },

                /**
                 * The live, actionable SEO checklist. Each check mirrors what
                 * Google actually rewards: intent-matched title/description
                 * lengths, real content depth, scannable heading structure,
                 * the focus keyword where it counts, internal links, and
                 * accessible images.
                 */
                get seoChecks() {
                    const t = this.metaTitleEffective;
                    const d = this.metaDescription;
                    const body = this.body || '';
                    // Focus keyword = first entry of the Keywords field.
                    const kw = (this.metaKeywords || '').split(',')[0]?.trim().toLowerCase() || '';
                    const h2Count = (body.match(/^##\s/gm) || []).length;
                    const first100 = body.replace(/[#*_`>\[\]\(\)]/g, ' ').trim().split(/\s+/).slice(0, 100).join(' ').toLowerCase();
                    const hasInternalLink = /\]\((\/|https?:\/\/(www\.)?apnainvoice)/i.test(body);
                    const imgs = [...body.matchAll(/!\[([^\]]*)\]\(/g)];
                    const hasImage = imgs.length > 0;
                    const imgsHaveAlt = hasImage && imgs.every(m => m[1].trim().length > 0);

                    return [
                        { ok: t.length >= 30 && t.length <= 60,
                          label: 'Title 30–60 chars', hint: t.length < 30 ? 'Too short - add the benefit or year' : t.length > 60 ? 'Too long - Google truncates it' : '' },
                        { ok: d.length >= 120 && d.length <= 160,
                          label: 'Meta description 120–160 chars', hint: d.length === 0 ? 'Write one - it is your ad in the results page' : '' },
                        { ok: this.wordCount >= 600,
                          label: '600+ words (' + this.wordCount + ')', hint: 'Thin pages rarely rank; 900+ is even better' },
                        { ok: h2Count >= 2,
                          label: '2+ section headings (## )', hint: 'Headings make posts scannable and win featured snippets' },
                        { ok: kw !== '' && t.toLowerCase().includes(kw),
                          label: 'Focus keyword in title', hint: kw === '' ? 'Add keywords below - the first one is your focus keyword' : 'Use "' + kw + '" in the title' },
                        { ok: kw !== '' && first100.includes(kw),
                          label: 'Keyword in first 100 words', hint: 'Google weighs the opening heavily' },
                        { ok: hasInternalLink,
                          label: 'At least one internal link', hint: 'Link to the calculator, a guide, or another post' },
                        { ok: imgsHaveAlt,
                          label: hasImage ? 'Images have alt text' : 'Add an image with alt text', hint: 'Alt text ranks in Google Images and helps accessibility' },
                    ];
                },

                get seoScore() {
                    const failed = this.seoChecks.filter(c => !c.ok).length;
                    if (failed === 0) return { tone: 'good', label: 'SEO ✓ ready' };
                    if (failed <= 2) return { tone: 'good', label: failed + ' to go' };
                    if (failed <= 4) return { tone: 'warn', label: failed + ' checks left' };
                    return { tone: 'bad', label: failed + ' checks left' };
                },

                // ──── Formatting toolbar - wraps selection in markdown ─────────────
                /**
                 * Replace the textarea selection with markdown syntax. For inline
                 * formats (bold, italic, code, link, image) we wrap the selection;
                 * for block formats (h2, h3, ul, ol, quote, codeblock) we prefix
                 * the line. Always restores caret position so the user keeps typing.
                 */
                format(kind) {
                    const ta = this.$refs.bodyTextarea;
                    if (!ta) return;
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    const value = ta.value;
                    const selected = value.slice(start, end);
                    let before = '', after = '', placeholder = '', isBlock = false;
                    switch (kind) {
                        case 'bold':      before = '**'; after = '**'; placeholder = 'bold text'; break;
                        case 'italic':    before = '*';  after = '*';  placeholder = 'italic text'; break;
                        case 'code':      before = '`';  after = '`';  placeholder = 'code'; break;
                        case 'link':      before = '['; after = '](https://)'; placeholder = 'link text'; break;
                        case 'image':     before = '![';after = '](image-url)'; placeholder = 'alt text'; break;
                        case 'h2':        before = '\n## '; after = '\n'; placeholder = 'Heading'; isBlock = true; break;
                        case 'h3':        before = '\n### '; after = '\n'; placeholder = 'Subheading'; isBlock = true; break;
                        case 'ul':        before = '\n- '; after = '\n'; placeholder = 'list item'; isBlock = true; break;
                        case 'ol':        before = '\n1. '; after = '\n'; placeholder = 'list item'; isBlock = true; break;
                        case 'quote':     before = '\n> '; after = '\n'; placeholder = 'quote'; isBlock = true; break;
                        case 'codeblock': before = '\n```\n'; after = '\n```\n'; placeholder = 'your code'; isBlock = true; break;
                    }
                    const text = selected || placeholder;
                    const replacement = before + text + after;
                    const newValue = value.slice(0, start) + replacement + value.slice(end);
                    this.body = newValue;
                    ta.value = newValue;
                    // Restore caret to highlight the inserted placeholder (or end of insertion).
                    this.$nextTick(() => {
                        ta.focus();
                        const caretStart = start + before.length;
                        const caretEnd = caretStart + text.length;
                        ta.setSelectionRange(caretStart, caretEnd);
                    });
                    this.updateBodyStats();
                    this.scheduleAutosave();
                },

                /** Keyboard shortcuts inside the textarea: Ctrl+B/I/K for bold/italic/link. */
                onKeydown(e) {
                    if (!(e.ctrlKey || e.metaKey)) return;
                    const key = e.key.toLowerCase();
                    if (key === 'b') { e.preventDefault(); this.format('bold'); }
                    else if (key === 'i') { e.preventDefault(); this.format('italic'); }
                    else if (key === 'k') { e.preventDefault(); this.format('link'); }
                },

                // ──── Preview ──────────────────────────────────────────────────────
                async openPreview() {
                    this.previewOpen = true;
                    this.previewHtml = '';
                    try {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const res = await fetch('{{ route("admin.blog.preview") }}', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'text/html',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ body: this.body }),
                        });
                        if (res.ok) {
                            this.previewHtml = await res.text();
                        } else {
                            this.previewHtml = '<p class="text-danger-600">Preview failed (HTTP ' + res.status + ').</p>';
                        }
                    } catch (e) {
                        this.previewHtml = '<p class="text-danger-600">Network error generating preview.</p>';
                    }
                },

                // ──── Autosave ─────────────────────────────────────────────────────
                /**
                 * Schedule a debounced autosave to localStorage. Called on every
                 * input change. Real save happens 1.5s after the user stops typing,
                 * so we don't thrash localStorage on every keystroke.
                 */
                scheduleAutosave() {
                    if (this._pendingAutosave) clearTimeout(this._pendingAutosave);
                    this._pendingAutosave = setTimeout(() => this.doAutosave(), 1500);
                },
                doAutosave() {
                    try {
                        const snapshot = {
                            title: this.title,
                            slug: this.slug,
                            body: this.body,
                            metaTitle: this.metaTitle,
                            metaDescription: this.metaDescription,
                            savedAt: Date.now(),
                        };
                        localStorage.setItem(autosaveKey, JSON.stringify(snapshot));
                        const t = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        this.autosaveLabel = 'Saved locally at ' + t;
                    } catch (e) {
                        // localStorage full / disabled - fail silently.
                    }
                },
                /**
                 * On mount, see if there's a newer local snapshot than what the
                 * server gave us. Shows the "Restore" banner if so.
                 */
                checkAutosave() {
                    try {
                        const raw = localStorage.getItem(autosaveKey);
                        if (!raw) return;
                        const snap = JSON.parse(raw);
                        if (!snap || !snap.savedAt) return;
                        // Only show banner if the snapshot is meaningfully different
                        // from the server-provided body - otherwise it's just noise.
                        if ((snap.body || '') === (this.body || '')) return;
                        this.autosaveAvailable = true;
                        // Human-readable age - "2 minutes ago", "1 hour ago".
                        const mins = Math.round((Date.now() - snap.savedAt) / 60000);
                        this.autosaveAge = mins < 1 ? 'just now'
                            : mins < 60 ? mins + ' minute' + (mins > 1 ? 's' : '') + ' ago'
                            : Math.round(mins / 60) + ' hour' + (Math.round(mins / 60) > 1 ? 's' : '') + ' ago';
                    } catch (e) {}
                },
                restoreAutosave() {
                    try {
                        const snap = JSON.parse(localStorage.getItem(autosaveKey) || '{}');
                        if (snap.title) this.title = snap.title;
                        if (snap.slug) this.slug = snap.slug;
                        if (snap.body !== undefined) this.body = snap.body;
                        if (snap.metaTitle !== undefined) this.metaTitle = snap.metaTitle;
                        if (snap.metaDescription !== undefined) this.metaDescription = snap.metaDescription;
                        this.updateBodyStats();
                        this.autosaveAvailable = false;
                        this.autosaveLabel = 'Restored from local draft';
                    } catch (e) {}
                },
                discardAutosave() {
                    localStorage.removeItem(autosaveKey);
                    this.autosaveAvailable = false;
                },

                // ──── Featured / OG image instant-preview ──────────────────────────
                /**
                 * When the user picks a featured or OG image, show it instantly
                 * via a blob: URL (no upload, no server round-trip). The real
                 * upload happens on form submit. We revoke any previous blob URL
                 * to avoid memory leaks.
                 */
                onFilePick(ev, kind) {
                    const file = ev.target?.files?.[0];
                    if (!file) return;
                    // Revoke old preview's blob URL (if any).
                    if (kind === 'featured' && this.featuredPreview) URL.revokeObjectURL(this.featuredPreview);
                    if (kind === 'og' && this.ogPreview) URL.revokeObjectURL(this.ogPreview);
                    const url = URL.createObjectURL(file);
                    if (kind === 'featured') this.featuredPreview = url;
                    else if (kind === 'og') this.ogPreview = url;
                },

                // ──── Body-image upload (toolbar + paste) ──────────────────────────
                /**
                 * Toolbar Image button: file picker → AJAX upload → insert markdown.
                 * Inserts `![](url)` at the caret so the user can type the alt text
                 * inside the brackets.
                 */
                async onBodyImagePick(ev) {
                    const file = ev.target?.files?.[0];
                    if (!file) return;
                    await this.uploadAndInsertImage(file);
                    // Reset the input so picking the same file again still fires change.
                    ev.target.value = '';
                },

                /**
                 * Paste-from-clipboard handler on the body textarea. If the paste
                 * contains an image (screenshot, copied web image), we intercept,
                 * upload it, and insert the markdown. Text pastes fall through to
                 * normal behaviour.
                 */
                async onPaste(ev) {
                    const items = ev.clipboardData?.items;
                    if (!items) return;
                    for (const item of items) {
                        if (item.kind === 'file' && item.type.startsWith('image/')) {
                            ev.preventDefault();
                            const file = item.getAsFile();
                            if (file) await this.uploadAndInsertImage(file);
                            return;
                        }
                    }

                    // Rich text: a textarea only receives the text/plain half of
                    // a paste, so an article written in Docs, Word or ChatGPT
                    // arrived here with every heading, bold run, list and link
                    // flattened. That is why posts had no structure. Convert the
                    // text/html half to Markdown instead.
                    const html = ev.clipboardData?.getData('text/html');
                    if (html && html.trim() && window.htmlToMarkdown) {
                        const md = window.htmlToMarkdown(html);
                        const plain = (ev.clipboardData.getData('text/plain') || '').trim();

                        // Only take over when the conversion actually recovered
                        // structure. Pasting a plain sentence should stay a
                        // plain sentence, not gain escapes it never needed.
                        // Multiline flag rather than an inline newline: a regex literal
                        // cannot span lines, and writing one that did took the whole
                        // script block down with it.
                        const hasBlockSyntax = /^(#{2,6} |[-*] |\d+\. |> |```)/m.test(md);
                        const hasInlineSyntax = md.includes('**') || /\[[^\]]+\]\(/.test(md);
                        const gainedStructure = hasBlockSyntax || hasInlineSyntax;
                        if (md && gainedStructure && md !== plain) {
                            ev.preventDefault();
                            this.insertAtCaret(md);
                            this.updateBodyStats();
                            this.scheduleAutosave();
                            this.pasteNotice = 'Pasted with formatting kept - headings, lists and links converted to Markdown.';
                            setTimeout(() => { this.pasteNotice = ''; }, 6000);
                            return;
                        }
                    }
                    // Nothing structured to recover → let the textarea handle it.
                },

                /**
                 * Upload an image File to the server and insert the resulting URL
                 * as a Markdown image at the textarea's current caret. Used by
                 * both the Image toolbar button and the paste-from-clipboard handler.
                 */
                async uploadAndInsertImage(file) {
                    if (this.imageUploading) return;
                    this.imageUploading = true;
                    try {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const fd = new FormData();
                        fd.append('image', file);
                        const res = await fetch('{{ route("admin.blog.upload-image") }}', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: fd,
                        });
                        if (res.status === 201) {
                            const data = await res.json();
                            this.insertAtCaret('![](' + data.url + ')', { selectAlt: true });
                            this.scheduleAutosave();
                        } else if (res.status === 422) {
                            const body = await res.json();
                            const firstErr = Object.values(body.errors || {})[0]?.[0] || 'Invalid image.';
                            alert('Could not upload image: ' + firstErr);
                        } else {
                            alert('Image upload failed (status ' + res.status + ').');
                        }
                    } catch (e) {
                        alert('Network error uploading image.');
                    } finally {
                        this.imageUploading = false;
                    }
                },

                /**
                 * Insert text at the textarea's current caret position. If
                 * `selectAlt` is true (we just inserted `![](url)`), highlight
                 * the empty `[]` so the user immediately types the alt text.
                 */
                insertAtCaret(text, opts = {}) {
                    const ta = this.$refs.bodyTextarea;
                    if (!ta) return;
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    const value = ta.value;
                    const newValue = value.slice(0, start) + text + value.slice(end);
                    this.body = newValue;
                    ta.value = newValue;
                    this.$nextTick(() => {
                        ta.focus();
                        if (opts.selectAlt) {
                            // Find the empty `![](` brackets we just inserted and
                            // place caret inside them so the writer can type alt.
                            const altPos = start + 2; // after "!["
                            ta.setSelectionRange(altPos, altPos);
                        } else {
                            const caretPos = start + text.length;
                            ta.setSelectionRange(caretPos, caretPos);
                        }
                    });
                    this.updateBodyStats();
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
