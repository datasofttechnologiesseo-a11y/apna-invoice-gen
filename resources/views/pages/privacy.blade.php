@php $effective = 'April 18, 2026'; @endphp
<x-layouts.marketing
    title="Privacy Policy"
    eyebrow="Legal"
    lead="What data we collect, why we collect it, and the controls you have over it. Your records stay in India."
    description="Apna Invoice Privacy Policy — DPDP Act 2023 compliant. Data residency in India, your rights, retention, security, subprocessors, and grievance contact."
    keywords="Apna Invoice privacy policy, DPDP Act compliance, invoice software privacy India, GST data residency India, SaaS privacy India"
    type="article">

    <p class="text-sm text-gray-500">Effective: {{ $effective }}</p>

    <h2>1. Who we are</h2>
    <p>
        Apna Invoice is operated by Datasoft Technologies ("DST", "we", "us"), the data fiduciary under
        the Digital Personal Data Protection Act, 2023 ("DPDP Act").
    </p>

    <h2>2. Data we collect</h2>
    <ul>
        <li><strong>Account data:</strong> name, email, password hash, phone number.</li>
        <li><strong>Business data:</strong> business name, GSTIN, PAN, address, logo.</li>
        <li><strong>Invoice &amp; customer data:</strong> records you create in the Service.</li>
        <li><strong>Usage data:</strong> IP, device, browser, session events, product analytics.</li>
        <li><strong>Support data:</strong> messages and attachments you send us.</li>
    </ul>

    <h2>3. How we use data</h2>
    <ul>
        <li>To operate, secure, and improve the Service.</li>
        <li>To communicate about your account, updates, and security notices.</li>
        <li>To meet legal and tax obligations.</li>
        <li>For product analytics (aggregated, never sold).</li>
    </ul>

    <h2>4. Where data is stored</h2>
    <p>
        Your account and invoice data is stored on servers located in <strong>India</strong>. Backups are
        encrypted and also retained in-region.
    </p>

    <h2>5. Sharing</h2>
    <p>
        We do not sell your data. We share it only with: (a) subprocessors (hosting, email delivery, analytics)
        bound by contract; (b) authorities when legally required; (c) successors in the event of a merger or
        acquisition, subject to this policy.
    </p>

    <h2>6. Your rights</h2>
    <p>Under the DPDP Act and applicable law, you may:</p>
    <ul>
        <li>Access, correct, or update your personal data.</li>
        <li>Export your invoice and customer data at any time.</li>
        <li>Request deletion of your account and personal data.</li>
        <li>Withdraw consent for non-essential processing.</li>
    </ul>
    <p>
        To exercise these rights, email <a href="mailto:privacy@datasofttechnologies.com">privacy@datasofttechnologies.com</a>.
        We respond within 30 days.
    </p>

    <h2>7. Retention</h2>
    <p>
        We retain your data while your account is active and for up to 7 years after closure to meet tax and
        record-keeping obligations under Indian law. Anonymised analytics may be retained indefinitely.
    </p>

    <h2>8. Security</h2>
    <p>
        We use TLS in transit, AES-256 at rest for backups, role-based access controls, and regular security
        reviews. See our <a href="{{ route('pages.security') }}">Security page</a> for detail.
    </p>

    <h2>9. Cookies</h2>
    <p>
        We use essential cookies for authentication and a small set of analytics cookies. Manage preferences on
        the <a href="{{ route('pages.cookies') }}">Cookie Settings page</a>.
    </p>

    <h2>10. Children</h2>
    <p>
        The Service is not directed to anyone under 18. We do not knowingly collect data from children.
    </p>

    <h2>11. Changes</h2>
    <p>
        We will announce material changes at least 30 days before they take effect.
    </p>

    <h2>Grievance officer</h2>
    <p>
        As required under Indian law, you may contact our grievance officer at
        <a href="mailto:grievance@datasofttechnologies.com">grievance@datasofttechnologies.com</a>.
    </p>
</x-layouts.marketing>
