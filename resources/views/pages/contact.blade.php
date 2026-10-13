@php
    $contactSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'ContactPage',
        'name' => 'Contact Apna Invoice',
        'inLanguage' => 'en-IN',
        'mainEntity' => [
            '@type' => 'Organization',
            // Same entity as the home page's Organization node.
            '@id' => rtrim(config('app.url'), '/') . '#organization',
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
    title="Contact us: India-based support"
    eyebrow="Contact"
    lead="Running 100+ invoices a month, managing several GSTINs, or stuck on a setting? Our India-based team replies within one business day."
    description="Contact our India-based team for help with GST invoicing, multi-GSTIN setup, custom plans and partnerships. Support for MSMEs, startups, freelancers and CAs."
    keywords="Apna Invoice contact, GST invoice support India, invoice software help India, MSME invoicing support, Datasoft Technologies contact, CA partnership India"
    :json-ld="[$contactSchema]">

    @if (session('status'))
        <div class="not-prose mb-6 flex gap-3 rounded-lg bg-money-50 ring-1 ring-money-200 px-4 py-3 text-money-800" role="status" tabindex="-1" x-data x-init="$el.focus()">
            <svg class="mt-0.5 w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <h2>Get in touch</h2>
    <div class="mt-5 grid sm:grid-cols-2 gap-4">
        <a href="{{ config('seo.contact.whatsapp_url') }}?text={{ urlencode('Hi Apna Invoice team, I need help with…') }}" target="_blank" rel="noopener"
           class="group block rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-5 hover:ring-gray-300 hover:shadow transition">
            <svg class="w-6 h-6 text-[#0f7540]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            <div class="mt-3 font-semibold text-gray-900 group-hover:text-brand-700 transition-colors">WhatsApp us</div>
            <div class="mt-0.5 text-[15px] text-gray-700 tabular-nums">{{ config('seo.contact.phone_display') }}</div>
            <div class="mt-2 text-sm text-gray-500">Fastest reply during business hours · 9.30 am – 7 pm IST, Mon–Sat</div>
        </a>
        <a href="tel:{{ config('seo.contact.phone_e164') }}"
           class="group block rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-5 hover:ring-gray-300 hover:shadow transition">
            <svg class="w-6 h-6 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <div class="mt-3 font-semibold text-gray-900 group-hover:text-brand-700 transition-colors">Call us</div>
            <div class="mt-0.5 text-[15px] text-gray-700 tabular-nums">{{ config('seo.contact.phone_display') }}</div>
            <div class="mt-2 text-sm text-gray-500">Sales &amp; support · 9.30 am – 7 pm IST, Mon–Sat</div>
        </a>
        <a href="mailto:sales@datasofttechnologies.com"
           class="group block rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-5 hover:ring-gray-300 hover:shadow transition">
            <svg class="w-6 h-6 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <div class="mt-3 font-semibold text-gray-900 group-hover:text-brand-700 transition-colors">Email sales</div>
            <div class="mt-0.5 text-[15px] text-gray-700 break-all">sales@datasofttechnologies.com</div>
        </a>
        <a href="mailto:support@datasofttechnologies.com"
           class="group block rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-5 hover:ring-gray-300 hover:shadow transition">
            <svg class="w-6 h-6 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="mt-3 font-semibold text-gray-900 group-hover:text-brand-700 transition-colors">Product support</div>
            <div class="mt-0.5 text-[15px] text-gray-700 break-all">support@datasofttechnologies.com</div>
        </a>
    </div>

    <h2>What to expect</h2>
    <ul>
        <li>Replies within <strong>one business day</strong>, typically the same day.</li>
        <li>A reply from a person, not a chatbot. Sales enquiries usually go to the founder.</li>
        <li>For a bug, include your account email and a short description of what you saw.</li>
    </ul>

    <h2>Or send us a message</h2>

    @if ($errors->any())
        {{-- Brought into view, so a failed send is never below the fold. --}}
        <div class="mt-4 rounded-lg bg-danger-50 ring-1 ring-danger-200 px-4 py-3 text-danger-800 scroll-mt-24" role="alert" x-data x-init="$el.scrollIntoView({ block: 'start' })">
            <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('pages.contact.send') }}" id="contact-form" class="mt-5 rounded-xl bg-white ring-1 ring-gray-200 shadow-sm p-5 sm:p-6 space-y-5">
        @csrf
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label for="name" class="block text-sm font-semibold text-gray-800">Your name *</label>
                <input id="name" name="name" type="text" required maxlength="120" value="{{ old('name', auth()->user()?->name) }}"
                       class="mt-1.5 block w-full border-gray-300 rounded-lg shadow-sm focus:border-brand-600 focus:ring-brand-600" autocomplete="name">
            </div>
            <div>
                <label for="email" class="block text-sm font-semibold text-gray-800">Email *</label>
                <input id="email" name="email" type="email" required maxlength="255" value="{{ old('email', auth()->user()?->email) }}"
                       class="mt-1.5 block w-full border-gray-300 rounded-lg shadow-sm focus:border-brand-600 focus:ring-brand-600" autocomplete="email">
            </div>
            <div>
                <label for="phone" class="block text-sm font-semibold text-gray-800">Phone (optional)</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="30" value="{{ old('phone') }}"
                       class="mt-1.5 block w-full border-gray-300 rounded-lg shadow-sm focus:border-brand-600 focus:ring-brand-600" placeholder="+91 98765 43210">
            </div>
            <div>
                <label for="subject" class="block text-sm font-semibold text-gray-800">Subject *</label>
                <input id="subject" name="subject" type="text" required minlength="3" maxlength="150" value="{{ old('subject', request()->query('subject', '')) }}"
                       class="mt-1.5 block w-full border-gray-300 rounded-lg shadow-sm focus:border-brand-600 focus:ring-brand-600" placeholder="Sales enquiry / Bug report / …">
            </div>
        </div>
        <div>
            <label for="message" class="block text-sm font-semibold text-gray-800">Message *</label>
            <textarea id="message" name="message" rows="6" required minlength="10" maxlength="4000" aria-describedby="message-hint"
                      class="mt-1.5 block w-full border-gray-300 rounded-lg shadow-sm focus:border-brand-600 focus:ring-brand-600">{{ old('message') }}</textarea>
            <p id="message-hint" class="text-sm text-gray-500 mt-1.5">10–4000 characters. For bug reports, include your account email and what you saw.</p>
        </div>

        <x-turnstile />

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center gap-2 min-h-[44px] px-5 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-md shadow-sm transition-colors">
                Send message
            </button>
        </div>
    </form>

    <h2>Office</h2>
    <address class="mt-3 not-italic rounded-xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 leading-relaxed">
        <span class="block font-semibold text-gray-900">Datasoft Technologies</span>
        <span class="block">Corporate Office · Delhi NCR, India</span>
        <span class="block">Phone / WhatsApp: <a href="tel:{{ config('seo.contact.phone_e164') }}">{{ config('seo.contact.phone_display') }}</a></span>
        <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener">datasofttechnologies.com</a>
    </address>

    @push('scripts')
        {{-- Turnstile needs this on marketing pages too. --}}
    @endpush
</x-layouts.marketing>
