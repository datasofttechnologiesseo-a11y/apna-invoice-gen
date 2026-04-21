@php
    $roles = [
        ['title' => 'Senior Laravel Engineer', 'location' => 'Remote (India)', 'type' => 'Full-time', 'tag' => 'Engineering'],
        ['title' => 'Product Designer (SaaS)', 'location' => 'Remote (India)', 'type' => 'Full-time', 'tag' => 'Design'],
        ['title' => 'GST Content Specialist', 'location' => 'Remote (India)', 'type' => 'Full-time', 'tag' => 'Content'],
        ['title' => 'Customer Success Associate', 'location' => 'Remote (India)', 'type' => 'Full-time', 'tag' => 'Support'],
    ];
@endphp
<x-layouts.marketing
    title="Careers at DST"
    eyebrow="We're hiring"
    lead="Help us build tools that Indian businesses actually enjoy using. Small team, real ownership, ship-focused culture."
    description="Open roles at Datasoft Technologies — remote-first jobs across engineering, design, content, and support. Build SaaS used by Indian SMEs and startups every day."
    keywords="Datasoft Technologies careers, remote jobs India SaaS, Laravel developer jobs India, product designer SaaS India, GST content writer jobs, DST hiring">

    <h2>Why DST</h2>
    <ul>
        <li><strong>Remote-first.</strong> Work from anywhere in India.</li>
        <li><strong>Small team, big leverage.</strong> Your work ships to real SMEs within days, not quarters.</li>
        <li><strong>Founders-as-colleagues.</strong> Flat structure, no layers.</li>
        <li><strong>Compensation above market.</strong> Plus ESOPs for full-time roles.</li>
    </ul>

    <h2>Open roles</h2>
    <div class="not-prose mt-6 space-y-3">
        @foreach ($roles as $r)
            <div class="p-5 rounded-xl ring-1 ring-gray-200 hover:ring-brand-300 hover:shadow-sm transition bg-white flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-brand-50 text-brand-700 ring-1 ring-brand-100">{{ $r['tag'] }}</span>
                    </div>
                    <h3 class="mt-1 font-display font-bold text-lg text-gray-900">{{ $r['title'] }}</h3>
                    <p class="text-sm text-gray-500">{{ $r['location'] }} · {{ $r['type'] }}</p>
                </div>
                <a href="mailto:careers@datasofttechnologies.com?subject=Application: {{ $r['title'] }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition">
                    Apply →
                </a>
            </div>
        @endforeach
    </div>

    <h2>Don't see your role?</h2>
    <p>
        We're always happy to hear from exceptional people. Send your resume and a short note to
        <a href="mailto:careers@datasofttechnologies.com">careers@datasofttechnologies.com</a>.
    </p>
</x-layouts.marketing>
