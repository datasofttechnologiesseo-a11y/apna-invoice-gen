<x-app-layout>
    <x-slot name="header">
        <x-breadcrumbs :items="[
            ['label' => 'Blogs · Admin', 'href' => route('admin.blog.index')],
            ['label' => $post->exists ? $post->title : 'New post'],
        ]" />
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-display font-extrabold text-xl sm:text-2xl text-gray-900 leading-tight">
                {{ $post->exists ? 'Edit post' : 'New post' }}
            </h2>
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
        slugLocked: @js((bool) ($post->slug ?? null)),
    })">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @if ($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
                    <ul class="list-disc pl-6 text-sm">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}"
                  enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
                @csrf
                @if ($post->exists) @method('PATCH') @endif

                {{-- ─── MAIN COLUMN — title, slug, body ──────────────────── --}}
                <div class="space-y-6">

                    {{-- Title + auto-slug --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 sm:p-6 space-y-4">
                        <div>
                            <x-input-label for="title" value="Title *" />
                            <input id="title" name="title" type="text" required maxlength="200"
                                   x-model="title" @input="onTitleChange"
                                   placeholder="e.g. How to file GSTR-1 from your invoices in 5 minutes"
                                   class="mt-1 block w-full text-2xl font-display font-bold border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500"
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
                                    <input type="checkbox" x-model="slugLocked" class="rounded border-gray-300 text-brand-700 focus:ring-brand-500">
                                    Lock slug (manual)
                                </label>
                            </div>
                            <div class="mt-1 flex items-center gap-1 font-mono text-sm">
                                <span class="px-3 py-2 bg-gray-100 text-gray-500 rounded-l-md border border-r-0 border-gray-300">/blog/</span>
                                <input id="slug" name="slug" type="text" pattern="[a-z0-9-]+" maxlength="220"
                                       x-model="slug" :readonly="!slugLocked"
                                       :class="slugLocked ? 'bg-white' : 'bg-gray-50'"
                                       class="flex-1 block w-full border-gray-300 rounded-r-md shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                       value="{{ $slugVal }}">
                            </div>
                            <p class="mt-1 text-[10px] text-gray-500">Auto-generated from the title. Tick "Lock slug" to override. Lowercase letters, digits, dashes only.</p>
                        </div>

                        <div>
                            <x-input-label for="excerpt" value="Excerpt (shown on the blog index card)" />
                            <textarea id="excerpt" name="excerpt" rows="2" maxlength="500"
                                      placeholder="One-two sentences summarising the post. Leave blank to auto-generate from the body."
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ $excerptVal }}</textarea>
                        </div>
                    </div>

                    {{-- Body editor with live word count + reading time --}}
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-5 sm:px-6 py-3 border-b flex items-center justify-between gap-3 flex-wrap">
                            <div>
                                <h3 class="font-medium text-gray-900">Body (Markdown)</h3>
                                <p class="text-[11px] text-gray-500">Headings <code>##</code>, lists <code>-</code>, links <code>[text](url)</code>, code <code>`...`</code>, tables — all GitHub-flavored.</p>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-600">
                                <span><strong x-text="wordCount"></strong> words</span>
                                <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                <span><strong x-text="readingMinutes"></strong> min read</span>
                            </div>
                        </div>
                        <textarea id="body" name="body" required rows="22"
                                  x-model="body" @input="updateBodyStats"
                                  placeholder="## Introduction&#10;&#10;Write your post here…"
                                  class="block w-full border-0 focus:ring-0 font-mono text-sm leading-relaxed p-5 sm:p-6 resize-y">{{ $bodyVal }}</textarea>
                    </div>
                </div>

                {{-- ─── SIDEBAR — publish, SEO, images ───────────────────── --}}
                <aside class="space-y-6">

                    {{-- Publish controls --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 space-y-4">
                        <h3 class="font-display font-bold text-gray-900">Publish</h3>
                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" x-model="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="draft" @selected($statusVal === 'draft')>Draft (only you can see)</option>
                                <option value="published" @selected($statusVal === 'published')>Published (live on /blog)</option>
                            </select>
                        </div>
                        <div x-show="status === 'published'" x-cloak>
                            <x-input-label for="published_at" value="Publish at" />
                            <input id="published_at" name="published_at" type="datetime-local" value="{{ $publishedAtVal }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">
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
                                confirm-class="bg-red-600 hover:bg-red-700"
                                tone="danger">
                                <button type="button" class="w-full inline-flex items-center justify-center min-h-[40px] px-4 py-2 bg-white ring-1 ring-red-300 hover:bg-red-50 text-red-700 rounded-md text-sm font-medium transition">
                                    Delete post
                                </button>
                            </x-confirm-form>
                        @endif
                    </div>

                    {{-- SEO panel — the heart of the "smart" editor --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-display font-bold text-gray-900">SEO</h3>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                  :class="seoScore.tone === 'good' ? 'bg-emerald-100 text-emerald-800' :
                                          seoScore.tone === 'warn' ? 'bg-amber-100 text-amber-800' :
                                          'bg-red-100 text-red-800'">
                                <span x-text="seoScore.label"></span>
                            </span>
                        </div>

                        <div>
                            <x-input-label for="meta_title" value="Meta title" />
                            <input id="meta_title" name="meta_title" type="text" maxlength="70"
                                   x-model="metaTitle"
                                   placeholder="Falls back to the post title"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                   value="{{ $metaTitleVal }}">
                            <div class="mt-1 text-[10px] flex items-center justify-between"
                                 :class="metaTitleEffective.length > 60 ? 'text-amber-700' : 'text-gray-500'">
                                <span>Google truncates around 60 chars.</span>
                                <span><span x-text="metaTitleEffective.length"></span> / 60</span>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="meta_description" value="Meta description" />
                            <textarea id="meta_description" name="meta_description" rows="3" maxlength="200"
                                      x-model="metaDescription"
                                      placeholder="The text that appears under your title in Google search results."
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ $metaDescVal }}</textarea>
                            <div class="mt-1 text-[10px] flex items-center justify-between"
                                 :class="metaDescription.length > 160 ? 'text-amber-700' : metaDescription.length < 80 ? 'text-amber-700' : 'text-emerald-700'">
                                <span x-text="metaDescription.length === 0 ? 'Recommended: 150-160 chars' : metaDescription.length > 160 ? 'Too long — Google will truncate' : metaDescription.length < 80 ? 'Too short — aim for 150-160' : 'Looks good'"></span>
                                <span><span x-text="metaDescription.length"></span> / 160</span>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="meta_keywords" value="Keywords" />
                            <input id="meta_keywords" name="meta_keywords" type="text" maxlength="255"
                                   placeholder="GST invoice, HSN SAC, GSTR-1"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                   value="{{ $metaKeywordsVal }}">
                            <p class="mt-1 text-[10px] text-gray-500">Comma-separated. Render as topic tags below the article and feed Bing/Yandex search engines.</p>
                        </div>

                        <div class="pt-3 border-t border-gray-100 space-y-2 text-[11px]">
                            <div class="font-bold uppercase tracking-wider text-gray-500">Search preview</div>
                            <div class="rounded border border-gray-200 p-3 bg-gray-50">
                                <div class="text-[12px] text-gray-600 truncate">{{ url('/blog/') }}/<span x-text="slug || 'your-post-slug'"></span></div>
                                <div class="text-[14px] font-medium text-blue-700 leading-tight mt-0.5 line-clamp-1" x-text="metaTitleEffective || 'Your post title'"></div>
                                <div class="text-[12px] text-gray-700 leading-snug mt-0.5 line-clamp-2" x-text="metaDescription || 'A 150-160 character description here helps your post stand out in Google.'"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Featured image --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 space-y-3">
                        <h3 class="font-display font-bold text-gray-900">Featured image</h3>
                        @if ($post->featured_image_path)
                            <img src="{{ asset('storage/' . $post->featured_image_path) }}" alt="{{ $altVal }}"
                                 class="w-full rounded-lg ring-1 ring-gray-200 aspect-[16/9] object-cover">
                        @endif
                        <div>
                            <x-input-label for="featured_image" value="Upload (max 4 MB)" />
                            <input id="featured_image" name="featured_image" type="file" accept="image/jpeg,image/png,image/webp"
                                   class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-brand-700 file:text-white file:font-semibold hover:file:bg-brand-800">
                            <p class="mt-1 text-[10px] text-gray-500">Recommended: 1200×675 (16:9), JPEG or WebP.</p>
                        </div>
                        <div>
                            <x-input-label for="featured_image_alt" value="Alt text (for SEO + accessibility)" />
                            <input id="featured_image_alt" name="featured_image_alt" type="text" maxlength="200"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                   value="{{ $altVal }}"
                                   placeholder="Describe what's in the image">
                        </div>
                    </div>

                    {{-- Social card override --}}
                    <div class="bg-white shadow sm:rounded-lg p-5 space-y-3">
                        <h3 class="font-display font-bold text-gray-900">Social-card image</h3>
                        @if ($post->og_image_path)
                            <img src="{{ asset('storage/' . $post->og_image_path) }}" alt="OG override"
                                 class="w-full rounded-lg ring-1 ring-gray-200 aspect-[1200/630] object-cover">
                        @endif
                        <div>
                            <x-input-label for="og_image" value="Override (1200×630 ideal)" />
                            <input id="og_image" name="og_image" type="file" accept="image/jpeg,image/png,image/webp"
                                   class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-gray-700 file:text-white file:font-semibold hover:file:bg-gray-800">
                            <p class="mt-1 text-[10px] text-gray-500">Optional. Falls back to the featured image.</p>
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

        function postEditor({ initialTitle, initialSlug, initialBody, initialMetaTitle, initialMetaDescription, slugLocked }) {
            return {
                title: initialTitle,
                slug: initialSlug,
                body: initialBody,
                metaTitle: initialMetaTitle,
                metaDescription: initialMetaDescription,
                status: document.getElementById('status')?.value || 'draft',
                slugLocked,                              // when true, manual slug entry; when false, auto-derive from title
                wordCount: 0,
                readingMinutes: 1,

                init() {
                    this.updateBodyStats();
                },

                onTitleChange() {
                    if (! this.slugLocked) {
                        this.slug = slugify(this.title);
                    }
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
                 * Lightweight SEO grade — 4 checks: meta description length,
                 * meta title length, body word count, slug presence. Surfaces
                 * one of three traffic-light states the operator can act on.
                 */
                get seoScore() {
                    const issues = [];
                    if (this.metaDescription.length < 80 || this.metaDescription.length > 160) issues.push('description');
                    if (this.metaTitleEffective.length < 30 || this.metaTitleEffective.length > 60) issues.push('title');
                    if (this.wordCount < 300) issues.push('length');
                    if (! this.slug) issues.push('slug');

                    if (issues.length === 0) return { tone: 'good', label: 'SEO ✓ ready' };
                    if (issues.length <= 1) return { tone: 'good', label: 'SEO mostly ready' };
                    if (issues.length <= 2) return { tone: 'warn', label: 'SEO needs work' };
                    return { tone: 'bad', label: 'SEO incomplete' };
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
