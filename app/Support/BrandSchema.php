<?php

namespace App\Support;

/**
 * The one description of the Apna Invoice brand for search engines: the
 * Organization, the WebSite and the SoftwareApplication, each with a fixed
 * @id. <x-seo> adds the Organization and WebSite to every indexable page and
 * puts these exact nodes in place of any page's own copy, so Google never
 * reads two different versions of the same entity (one with a logo and
 * social profiles, another with only a name).
 *
 * Built from config/seo.php and config/contacts.php only; nothing here is
 * invented (no ratings, reviews or user counts).
 */
final class BrandSchema
{
    public static function origin(): string
    {
        return rtrim(config('app.url'), '/');
    }

    public static function id(string $node): string
    {
        return self::origin() . '#' . $node;
    }

    /** A reference to a node, for publisher/author/creator fields. */
    public static function ref(string $node): array
    {
        return ['@id' => self::id($node)];
    }

    public static function organization(): array
    {
        $o = self::origin();
        $c = config('seo.contact', []);

        $contactPoints = array_values(array_filter([
            [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'telephone' => $c['phone_e164'] ?? null,
                'email' => config('contacts.support'),
                'areaServed' => 'IN',
                'availableLanguage' => ['English', 'Hindi'],
            ],
            config('contacts.sales') ? [
                '@type' => 'ContactPoint',
                'contactType' => 'sales',
                'telephone' => $c['phone_e164'] ?? null,
                'email' => config('contacts.sales'),
                'areaServed' => 'IN',
                'availableLanguage' => ['English', 'Hindi'],
            ] : null,
        ]));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => self::id('organization'),
            'name' => config('seo.organization.name', 'Apna Invoice'),
            'alternateName' => 'ApnaInvoice',
            'legalName' => config('seo.organization.legal_name'),
            'url' => config('seo.organization.url'),
            'logo' => [
                '@type' => 'ImageObject',
                '@id' => self::id('logo'),
                'url' => $o . config('seo.organization.logo'),
                'width' => 512,
                'height' => 512,
                'caption' => config('seo.organization.name', 'Apna Invoice'),
            ],
            'image' => ['@id' => self::id('logo')],
            'description' => 'Free GST billing software for Indian businesses: GST invoices, purchases, stock, bank matching and GST returns.',
            // Profiles only: the site itself is already `url`.
            'sameAs' => array_values(array_filter((array) config('seo.social', []))),
            'parentOrganization' => array_filter([
                '@type' => 'Organization',
                'name' => config('seo.organization.parent.name'),
                'url' => config('seo.organization.parent.url'),
                'logo' => config('seo.organization.parent.logo') ? $o . config('seo.organization.parent.logo') : null,
            ]),
            'foundingLocation' => ['@type' => 'Country', 'name' => 'India'],
            'areaServed' => ['@type' => 'Country', 'name' => 'India'],
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => config('seo.organization.locality'),
                'addressRegion' => config('seo.organization.region'),
                'addressCountry' => 'IN',
            ]),
            'telephone' => $c['phone_e164'] ?? null,
            'contactPoint' => $contactPoints,
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => self::id('website'),
            // name + alternateName feed the site name Google prints above a result.
            'name' => config('seo.name', 'Apna Invoice'),
            'alternateName' => ['ApnaInvoice', 'apnainvoice.com'],
            'url' => self::origin() . '/',
            'inLanguage' => 'en-IN',
            'publisher' => self::ref('organization'),
            // No SearchAction: the site has no public search page.
        ];
    }

    public static function software(): array
    {
        $o = self::origin();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            '@id' => self::id('software'),
            'name' => config('seo.name', 'Apna Invoice'),
            // Two real alternative names, not a list of search phrases.
            'alternateName' => ['ApnaInvoice', 'Apna Invoice GST billing software'],
            'url' => $o . '/',
            'image' => $o . '/brand/icon-512.png',
            'screenshot' => [$o . '/brand/screens/invoice-view.webp', $o . '/brand/screens/dashboard.webp'],
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Billing and invoicing',
            'operatingSystem' => 'Web',
            'browserRequirements' => 'Requires JavaScript',
            'inLanguage' => 'en-IN',
            'isAccessibleForFree' => true,
            'description' => 'Free GST billing software for India. GST invoices with CGST, SGST and IGST, quotations, credit notes, purchases, stock, bank reconciliation, GSTR-1 JSON, GSTR-2B match and GSTR-3B.',
            'featureList' => [
                'GST tax invoices with CGST, SGST, UTGST and IGST from the place of supply',
                'HSN and SAC codes, five PDF styles, original, duplicate and triplicate copies',
                'Share on WhatsApp or email, with a UPI QR for payment',
                'Quotations, credit notes, receipts and payment reminders',
                'Purchase invoices, input tax credit, expenses and cash memos',
                'Stock tracking with low-stock alerts',
                'Bank statement matching',
                'GSTR-1 JSON and Excel, GSTR-2B match, GSTR-3B',
                'Profit and loss, aging and registers in Excel, CSV and PDF',
                'Several businesses and team roles under one login',
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'INR',
                'availability' => 'https://schema.org/InStock',
                'url' => $o . '/pricing',
                'description' => 'Free during beta, with no limit on invoices.',
            ],
            'creator' => self::ref('organization'),
            'publisher' => self::ref('organization'),
        ];
    }

    /**
     * A page's JSON-LD with the brand nodes put right: any top-level copy of
     * the Organization, WebSite or SoftwareApplication is replaced by the one
     * above, and an indexable page always carries the Organization and
     * WebSite.
     */
    public static function normalise(array $nodes, bool $indexable): array
    {
        $canonical = [
            self::id('organization') => fn () => self::organization(),
            self::id('website') => fn () => self::website(),
            self::id('software') => fn () => self::software(),
        ];
        $seen = [];
        $out = [];
        foreach ($nodes as $node) {
            $id = is_array($node) ? ($node['@id'] ?? null) : null;
            if ($id && isset($canonical[$id])) {
                if (! isset($seen[$id])) {
                    $out[] = $canonical[$id]();
                    $seen[$id] = true;
                }
                continue;
            }
            $out[] = $node;
        }
        if ($indexable) {
            foreach (['website', 'organization'] as $n) {
                if (! isset($seen[self::id($n)])) {
                    array_unshift($out, $canonical[self::id($n)]());
                }
            }
        }

        return $out;
    }
}
