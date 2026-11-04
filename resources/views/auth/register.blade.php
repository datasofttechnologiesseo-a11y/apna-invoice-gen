<x-guest-layout
    :title="'Create a free GST invoice account'"
    :description="'Create your free Apna Invoice account: GST invoices with HSN/SAC, Indian number format and payment reminders, for SMEs, freelancers and CAs.'"
    :keywords="'free GST invoice signup, invoice generator signup India, create GST invoice free, Apna Invoice register'">
    <div class="mb-7">
        <h1 class="font-display text-2xl sm:text-[1.75rem] font-bold text-gray-900 tracking-tight">Create your account</h1>
        <p class="mt-2 text-[15px] text-gray-600">Unlimited GST invoices. Free during beta.</p>
    </div>

    {{-- The same form the landing pages use (components/signup-form). --}}
    <x-signup-form :referral-code="$referralCode ?? null" :autofocus="true" />

    <x-signup-help note="New to GST billing? Message us for help with your first invoice, at no charge." />
</x-guest-layout>
