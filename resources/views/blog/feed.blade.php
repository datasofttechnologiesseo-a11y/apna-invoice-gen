{{-- RSS 2.0. Emitted with no leading whitespace: a feed with anything before
     the XML declaration is rejected by strict readers. --}}
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <title>{{ config('seo.name', config('app.name')) }} Blog</title>
        <link>{{ route('blog.index') }}</link>
        <description>GST, invoicing and small-business guides for Indian freelancers, MSMEs and startups.</description>
        <language>en-IN</language>
        <lastBuildDate>{{ $updated->toRfc2822String() }}</lastBuildDate>
        {{-- atom:link self-reference: readers use it to detect the canonical
             feed URL after a redirect or a site move. --}}
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml"/>
        @foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('blog.show', $post->slug) }}</link>
            {{-- isPermaLink=false because the slug URL is the identity, and a
                 later slug change should not create a duplicate item. --}}
            <guid isPermaLink="false">{{ route('blog.show', $post->slug) }}</guid>
            <pubDate>{{ $post->published_at?->toRfc2822String() }}</pubDate>
            @if ($post->author?->name)<dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">{{ $post->author->name }}</dc:creator>@endif
            <description>{{ $post->effectiveMetaDescription() }}</description>
            @foreach (array_filter(array_map('trim', explode(',', (string) $post->meta_keywords))) as $keyword)
            <category>{{ $keyword }}</category>
            @endforeach
        </item>
        @endforeach
    </channel>
</rss>
