<x-layouts.marketing
    title="Security at DST"
    eyebrow="Trust"
    lead="How we protect your invoices, customer data, and account. Transparent practices, no marketing theatre."
    description="How Apna Invoice secures your invoice and customer data — TLS in transit, AES-256 at rest, India data residency, access controls, backups, and responsible disclosure."
    keywords="Apna Invoice security, invoice software security India, SaaS encryption India, GST data security, DPDP Act security, responsible disclosure Datasoft"
    type="article">

    <h2>In transit</h2>
    <p>
        All traffic to Apna Invoice is encrypted over <strong>TLS 1.2+ with HSTS</strong>. HTTP is redirected to
        HTTPS. We use modern cipher suites and disable known-weak primitives.
    </p>

    <h2>At rest</h2>
    <p>
        Database volumes and backups are encrypted with <strong>AES-256</strong>. Passwords are hashed with
        bcrypt. Uploaded files (e.g. business logos) are stored on encrypted object storage.
    </p>

    <h2>Data residency</h2>
    <p>
        Primary and backup data for Apna Invoice is stored on servers located in <strong>India</strong>. We do
        not replicate your business records outside the country.
    </p>

    <h2>Access controls</h2>
    <ul>
        <li>Production access is limited to a named set of engineers.</li>
        <li>Access requires SSO with hardware-backed MFA.</li>
        <li>All privileged actions are logged and reviewed.</li>
    </ul>

    <h2>Application security</h2>
    <ul>
        <li>CSRF protection on all state-changing requests.</li>
        <li>Content Security Policy and secure cookie flags.</li>
        <li>Parameterised queries and ORM-level protection against SQL injection.</li>
        <li>Dependency scanning on every build; critical vulnerabilities patched within 7 days.</li>
    </ul>

    <h2>Backups &amp; recovery</h2>
    <p>
        Automated daily backups with 30-day retention. Restore drills are performed quarterly. Target RPO: 24h,
        target RTO: 4h for a region-level incident.
    </p>

    <h2>Payments</h2>
    <p>
        When paid plans launch, payments will be processed by <strong>PCI-DSS Level 1</strong> certified
        providers. We do not store card numbers on our servers.
    </p>

    <h2>Responsible disclosure</h2>
    <p>
        If you believe you've found a security issue, please report it to
        <a href="mailto:security@datasoft.example">security@datasoft.example</a>. We commit to:
    </p>
    <ul>
        <li>Acknowledging receipt within <strong>2 business days</strong>.</li>
        <li>Keeping you informed as we investigate.</li>
        <li>Not pursuing legal action for good-faith research that follows this policy.</li>
    </ul>
    <p>
        Please do not publicly disclose the issue until we've had a chance to remediate (typically 90 days).
    </p>

    <h2>Status &amp; incidents</h2>
    <p>
        In the event of a material security incident affecting your data, we will notify affected users within
        72 hours, consistent with the DPDP Act.
    </p>
</x-layouts.marketing>
