<?php

return [

    'name' => 'Apna Invoice',

    'legal_name' => 'Datasoft Technologies',

    // Appended after the site name when a page passes no title of its own -
    // must NOT contain the brand again (the component prepends it).
    // No "Best": an unbacked superlative in a title reads as spam.
    'title_suffix' => ' | Free GST Invoice and Bill Generator for India',

    // Kept under ~160 chars - Google truncates around there, so the value
    // proposition and CTA must fit inside the visible snippet.
    'description' => 'Free GST invoice generator for India. Auto CGST/SGST/IGST, HSN/SAC, UPI QR & WhatsApp share in 60 seconds. Unlimited invoices, no card needed.',

    // Fallback meta keywords for indexable pages that pass none. Google
    // ignores the tag and Bing reads a long one as a spam signal, so this is
    // a short list of the phrases the product really serves. It used to be
    // ~90 terms ("best ...", "top ...", six city names), which is stuffing.
    'keywords' => implode(', ', [
        'GST invoice generator',
        'free GST invoice generator India',
        'GST billing software',
        'free billing software India',
        'online invoice generator',
        'GST bill generator',
        'tax invoice format India',
        'HSN SAC invoice',
        'CGST SGST IGST invoice',
        'GST credit note',
        'GSTR-1 export',
        'free GST calculator',
        'UPI QR invoice',
        'WhatsApp invoice',
        'invoice software for MSME',
        'Apna Invoice',
    ]),

    // Search-engine ownership verification (paste the token from Google Search
    // Console → Settings → Ownership verification → "HTML tag", and Bing too).
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'bing_site_verification' => env('BING_SITE_VERIFICATION'),

    // Dedicated 1200x630 social share card (the ideal OG aspect ratio).
    // Regenerate with: php scripts/make-og-card.php
    // og:image:width/height/type are measured from the file by <x-seo>, so
    // a card of any size is announced correctly; no size settings here.
    'og_image' => '/brand/og-card.png',

    'locale' => 'en_IN',

    'twitter_handle' => '@datasofttech',

    // Official social profiles. Rendered as icons in the footer and emitted as
    // Organization.sameAs so search engines can connect them to the business.
    'social' => [
        'facebook'  => 'https://www.facebook.com/profile.php?id=61590370831849',
        'instagram' => 'https://www.instagram.com/apnainvoice/',
        'linkedin'  => 'https://www.linkedin.com/showcase/apna-invoice/',
    ],

    // The organisation Google shows for this site is the Apna Invoice brand
    // (its logo in the knowledge panel), operated by Datasoft Technologies,
    // which is its legal name and parent company.
    'organization' => [
        'name' => 'Apna Invoice',
        'legal_name' => 'Datasoft Technologies',
        'url' => rtrim(env('APP_URL', 'https://apnainvoice.com'), '/') . '/',
        'logo' => '/brand/apna-invoice-logo-512.png',
        'parent' => [
            'name' => 'Datasoft Technologies',
            'url' => 'https://www.datasofttechnologies.com/',
            'logo' => '/brand/dst-logo.png',
        ],
        'country' => 'IN',
        'locality' => 'Delhi NCR',
        'region' => 'Delhi',
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact phone (single source of truth)
    |--------------------------------------------------------------------------
    | Used by:
    |   - Contact, About, Help, Press pages (visible)
    |   - JSON-LD schemas (Organization, ContactPoint)
    |   - WhatsApp click-to-chat links (digits-only)
    |   - Footer if shown
    */

    'contact' => [
        'phone_display'   => '+91 74286 93901',     // human-readable
        'phone_e164'      => '+917428693901',       // for tel: links + schema.org
        'whatsapp_digits' => '917428693901',        // wa.me only takes digits, no '+'
        'whatsapp_url'    => 'https://wa.me/917428693901',
        // Google Business review link. Public, not a secret, so it lives here
        // as the default and needs no deployment config; GOOGLE_REVIEW_URL can
        // still override it if the listing ever moves. Drives the clickable
        // "Write a review" button beside the QR code on the review invite.
        'google_review_url' => env('GOOGLE_REVIEW_URL', 'https://g.page/r/CTvUSP6MtrMaEBM/review'),
    ],

];
