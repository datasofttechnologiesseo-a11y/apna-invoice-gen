@php
    $contactSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'ContactPage',
        'name' => 'Contact Apna Invoice',
        'inLanguage' => 'en-IN',
        'mainEntity' => [
            '@type' => 'Organization',
            'name' => config('seo.organization.name'),
            'legalName' => config('seo.organization.legal_name'),
            'url' => config('seo.organization.url'),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => config('seo.organization.locality', 'Delhi NCR'),
                'addressRegion' => config('seo.organization.region', 'Delhi'),
                'addressCountry' => 'IN',
            ],
            'telephone' => config('seo.contact.phone_e164'),
            'contactPoint' => [
                [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer support',
                    'telephone' => config('seo.contact.phone_e164'),
                    'email' => 'support@datasofttechnologies.com',
                    'areaServed' => 'IN',
                    'availableLanguage' => ['English', 'Hindi'],
                    'contactOption' => 'TollFree',
                    'hoursAvailable' => [
                        '@type' => 'OpeningHoursSpecification',
                        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                        'opens' => '09:30',
                        'closes' => '19:00',
                    ],
                ],
                [
                    '@type' => 'ContactPoint',
                    'contactType' => 'sales',
                    'telephone' => config('seo.contact.phone_e164'),
                    'email' => 'sales@datasofttechnologies.com',
                    'areaServed' => 'IN',
                    'availableLanguage' => ['English', 'Hindi'],
                ],
            ],
        ],
    ];
@endphp
<x-layouts.marketing
    title="Contact Apna Invoice — India-based Support, Sales & Partnerships"
    eyebrow="Contact"
    lead="Running 100+ invoices a month, managing multiple GSTINs, or just stuck on a setting? Our India-based team replies within one business day."
    description="Contact Apna Invoice — India-based support and sales for MSMEs, SMEs, startups, freelancers and CAs. Help with GST invoicing, multi-GSTIN, custom plans, and partnerships."
    keywords="Apna Invoice contact, GST invoice support India, invoice software help India, MSME invoicing support, Datasoft Technologies contact, CA partnership India"
    :json-ld="[$contactSchema]">

    <h2>Get in touch</h2>
    <div class="not-prose mt-4 grid sm:grid-cols-2 gap-4">
        <a href="{{ config('seo.contact.whatsapp_url') }}?text={{ urlencode('Hi Apna Invoice team — I need help with…') }}" target="_blank" rel="noopener"
           class="block p-6 rounded-xl ring-1 ring-emerald-200 hover:ring-emerald-400 hover:shadow-sm transition bg-emerald-50/40">
            <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            </div>
            <div class="mt-3 font-display font-bold text-gray-900">WhatsApp us</div>
            <div class="text-sm text-emerald-700 font-mono">{{ config('seo.contact.phone_display') }}</div>
            <div class="mt-1 text-xs text-gray-500">Fastest reply during business hours · 9.30 am – 7 pm IST, Mon–Sat</div>
        </a>
        <a href="tel:{{ config('seo.contact.phone_e164') }}"
           class="block p-6 rounded-xl ring-1 ring-gray-200 hover:ring-brand-300 hover:shadow-sm transition bg-white">
            <div class="w-10 h-10 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            </div>
            <div class="mt-3 font-display font-bold text-gray-900">Call us</div>
            <div class="text-sm text-brand-700 font-mono">{{ config('seo.contact.phone_display') }}</div>
            <div class="mt-1 text-xs text-gray-500">Sales &amp; support · 9.30 am – 7 pm IST, Mon–Sat</div>
        </a>
        <a href="mailto:sales@datasofttechnologies.com" class="block p-6 rounded-xl ring-1 ring-gray-200 hover:ring-brand-300 hover:shadow-sm transition bg-white">
            <div class="w-10 h-10 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div class="mt-3 font-display font-bold text-gray-900">Email sales</div>
            <div class="text-sm text-gray-500">sales@datasofttechnologies.com</div>
        </a>
        <a href="mailto:support@datasofttechnologies.com" class="block p-6 rounded-xl ring-1 ring-gray-200 hover:ring-brand-300 hover:shadow-sm transition bg-white">
            <div class="w-10 h-10 rounded-lg bg-accent-50 text-accent-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="mt-3 font-display font-bold text-gray-900">Product support</div>
            <div class="text-sm text-gray-500">support@datasofttechnologies.com</div>
        </a>
    </div>

    <h2>What to expect</h2>
    <ul>
        <li>Replies within <strong>one business day</strong>, typically the same day.</li>
        <li>A real human (not a chatbot) — usually the founder for sales conversations.</li>
        <li>If it's a technical bug, include your account email and a short description of what you saw.</li>
    </ul>

    <h2>Or send us a message</h2>

    @if (session('status'))
        <div class="not-prose mb-4 p-4 bg-money-50 border border-money-200 text-money-800 rounded">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="not-prose mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded">
            <ul class="list-disc pl-6">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('pages.contact.send') }}" class="not-prose mt-4 p-6 rounded-xl ring-1 ring-gray-200 bg-white space-y-4">
        @csrf
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="name" class="block text-sm font-semibold text-gray-700">Your name *</label>
                <input id="name" name="name" type="text" required maxlength="120" value="{{ old('name') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" autocomplete="name">
            </div>
            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700">Email *</label>
                <input id="email" name="email" type="email" required maxlength="255" value="{{ old('email') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" autocomplete="email">
            </div>
            <div>
                <label for="phone" class="block text-sm font-semibold text-gray-700">Phone (optional)</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="30" value="{{ old('phone') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="+91 98765 43210">
            </div>
            <div>
                <label for="subject" class="block text-sm font-semibold text-gray-700">Subject *</label>
                <input id="subject" name="subject" type="text" required minlength="3" maxlength="150" value="{{ old('subject', request()->query('subject', '')) }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Sales enquiry / Bug report / …">
            </div>
        </div>
        <div>
            <label for="message" class="block text-sm font-semibold text-gray-700">Message *</label>
            <textarea id="message" name="message" rows="6" required minlength="10" maxlength="4000"
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('message') }}</textarea>
            <p class="text-xs text-gray-500 mt-1">10–4000 characters. For bug reports, include your account email and what you saw.</p>
        </div>

        <x-turnstile />

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-lg shadow-sm">
                Send message
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>

    <h2>Office</h2>
    <p>
        <strong>Datasoft Technologies</strong><br>
        Corporate Office · Delhi NCR, India<br>
        Phone / WhatsApp: <a href="tel:{{ config('seo.contact.phone_e164') }}" class="font-mono">{{ config('seo.contact.phone_display') }}</a><br>
        <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer">datasofttechnologies.com</a>
    </p>

    @push('scripts')
        {{-- Turnstile needs this on marketing pages too. --}}
    @endpush
</x-layouts.marketing>
