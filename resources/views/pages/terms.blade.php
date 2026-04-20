@php $effective = 'April 18, 2026'; @endphp
<x-layouts.marketing
    title="Terms of Service"
    eyebrow="Legal"
    lead="The rules of the road for using Apna Invoice. Plain English where we can, precise where we must."
    description="Apna Invoice Terms of Service — account rules, acceptable use, GST responsibility, fees, liability, and Indian jurisdiction. Plain English where possible."
    keywords="Apna Invoice terms of service, GST invoice software terms, SaaS terms India, Datasoft Technologies legal, invoice generator terms"
    type="article">

    <p class="text-sm text-gray-500">Effective: {{ $effective }}</p>

    <h2>1. Acceptance of terms</h2>
    <p>
        By creating an Apna Invoice account or using the service ("Service"), you agree to these Terms of Service
        ("Terms"). If you do not agree, do not use the Service. The Service is operated by Datasoft Technologies
        Pvt. Ltd. ("DST", "we", "us").
    </p>

    <h2>2. Accounts &amp; eligibility</h2>
    <p>
        You must be at least 18 years of age and authorised to bind the business you represent. You are responsible
        for safeguarding your credentials and for all activity under your account.
    </p>

    <h2>3. Acceptable use</h2>
    <ul>
        <li>Do not use the Service for unlawful, fraudulent, or infringing activity.</li>
        <li>Do not attempt to disrupt, reverse-engineer, or circumvent security controls.</li>
        <li>Do not upload malicious code or content that violates third-party rights.</li>
    </ul>

    <h2>4. Your content &amp; data</h2>
    <p>
        You retain all rights to the invoices, customer records, and other data you upload ("Your Content"). You
        grant DST a limited licence to host and process Your Content solely to operate the Service. See our
        <a href="{{ route('pages.privacy') }}">Privacy Policy</a> for details.
    </p>

    <h2>5. GST &amp; tax compliance</h2>
    <p>
        Apna Invoice helps you generate GST-compliant invoices, but <strong>you remain solely responsible</strong>
        for the accuracy of tax treatment, HSN/SAC codes, GSTINs, returns, and filings. DST is not a tax advisor
        and the Service is not a substitute for professional advice.
    </p>

    <h2>6. Fees</h2>
    <p>
        The Service is currently free during public beta. We will provide at least 30 days' notice before
        introducing paid features. Continued use after fees take effect constitutes acceptance of the new pricing.
    </p>

    <h2>7. Termination</h2>
    <p>
        You may stop using the Service at any time. We may suspend or terminate accounts that violate these Terms.
        On termination, we will make your data available for export for at least 30 days, subject to Section 4.
    </p>

    <h2>8. Disclaimers</h2>
    <p>
        The Service is provided "as is". To the maximum extent permitted by law, DST disclaims all warranties,
        express or implied, including merchantability, fitness for a particular purpose, and non-infringement.
    </p>

    <h2>9. Limitation of liability</h2>
    <p>
        To the maximum extent permitted by Indian law, DST's aggregate liability for any claim arising out of the
        Service shall not exceed the fees you paid in the 12 months preceding the claim, or INR 1,000 if no fees
        were paid.
    </p>

    <h2>10. Governing law</h2>
    <p>
        These Terms are governed by the laws of India. Disputes are subject to the exclusive jurisdiction of the
        courts of India.
    </p>

    <h2>11. Changes</h2>
    <p>
        We may update these Terms. Material changes will be announced via email or in-product notice at least
        30 days before they take effect.
    </p>

    <h2>Contact</h2>
    <p>Questions about these Terms? Email <a href="mailto:legal@datasoft.example">legal@datasoft.example</a>.</p>
</x-layouts.marketing>
