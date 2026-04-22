<x-layouts.marketing
    title="Talk to sales"
    eyebrow="Contact"
    lead="Running 100+ invoices a month, managing multiple GSTINs, or need a custom plan? We're happy to help."
    description="Contact Apna Invoice sales and support. Indian SMEs, high-volume invoicers, and multi-GSTIN businesses — email us for custom plans and priority onboarding."
    keywords="Apna Invoice contact, invoice software support India, GST invoice sales, Datasoft Technologies contact, enterprise GST invoicing India">

    <h2>Get in touch</h2>
    <div class="not-prose mt-4 grid sm:grid-cols-2 gap-4">
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
                <input id="subject" name="subject" type="text" required minlength="3" maxlength="150" value="{{ old('subject') }}"
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
        Datasoft Technologies<br>
        India<br>
        <a href="https://www.datasofttechnologies.com/" target="_blank" rel="noopener noreferrer">datasofttechnologies.com</a>
    </p>

    @push('scripts')
        {{-- Turnstile needs this on marketing pages too. --}}
    @endpush
</x-layouts.marketing>
