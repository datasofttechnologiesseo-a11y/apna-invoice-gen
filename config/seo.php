<?php

return [

    'name' => 'Apna Invoice',

    'legal_name' => 'Datasoft Technologies',

    // Appended after the site name when a page passes no title of its own -
    // must NOT contain the brand again (the component prepends it).
    'title_suffix' => ' | Best Free GST Invoice & Bill Generator for India',

    // Kept under ~160 chars - Google truncates around there, so the value
    // proposition and CTA must fit inside the visible snippet.
    'description' => 'Free GST invoice generator for India. Auto CGST/SGST/IGST, HSN/SAC, UPI QR & WhatsApp share in 60 seconds. Unlimited invoices, no card needed.',

    'keywords' => implode(', ', [
        // Primary head terms - what users search for to find a tool
        'best invoice generator India',
        'top invoice generator',
        'best bill generator online',
        'best GST invoice generator',
        'top GST bill generator',
        'best billing software India',
        'free GST invoice generator India',
        'free invoice generator India',
        'free bill generator online',
        'online bill generator',
        'online invoice maker',
        'GST invoice generator',
        'GST bill generator',
        'GST 2.0 invoice',
        'invoice maker online free',
        'invoice generator online India',
        'bill maker app',
        // Exact long-tail queries
        'create GST invoice online free India',
        'how to create GST invoice online free India',
        'online invoice generator with GST free',
        'invoice generator with GST calculation India free',
        'invoice generator with GST and HSN code',
        'GST compliant invoice generator India',
        'GST invoice generator for small business India',
        'free invoice maker for small business India',
        'simple GST billing software free India',
        'free billing software for small shop India GST',
        'billing software for SME India free',
        'invoice generator for MSME India',
        'GST invoice format download for MSME',
        'best free GST invoice generator for freelancers India',
        'tax invoice generator India',
        // Compliance / format keywords
        'GST compliant invoice',
        'GST invoice format',
        'tax invoice format India',
        'HSN code invoice',
        'SAC code invoice',
        'CGST SGST IGST invoice',
        'credit note GST Section 34',
        'invoice PDF generator',
        // Audience keywords
        'invoice software India',
        'invoice generator for small business',
        'invoice tool for CA',
        'billing software for MSME',
        'SME invoicing India',
        'MSME invoicing India',
        'startup invoicing India',
        'freelancer invoice India',
        // GST calculator (free home-page tool)
        'GST calculator',
        'free GST calculator',
        'free GST calculator India',
        'online GST calculator',
        'GST calculator online India',
        'GST tax calculator',
        'CGST SGST IGST calculator',
        'GST inclusive calculator',
        'GST exclusive calculator',
        'reverse GST calculator',
        'GST calculator 5 12 18 28',
        'GST calculator with HSN code',
        'intra-state GST calculator',
        'inter-state GST calculator',
        'GST percentage calculator',
        'GST amount calculator India',
        // India-payment rails
        'WhatsApp invoice India',
        'UPI QR invoice',
        'UPI invoice generator',
        // Migration intent (category-based, no competitor names)
        'switch from Excel invoicing',
        'replace spreadsheet invoicing India',
        'cloud GST invoicing software',
        'online billing software no installation',
        'free desktop billing alternative',
        // Geography long-tail
        'Delhi NCR invoice software',
        'Mumbai invoice software',
        'Bangalore invoice software',
        'Pune invoice software',
        'Hyderabad invoice software',
        'Chennai invoice software',
        'Apna Invoice',
    ]),

    // Search-engine ownership verification (paste the token from Google Search
    // Console → Settings → Ownership verification → "HTML tag", and Bing too).
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'bing_site_verification' => env('BING_SITE_VERIFICATION'),

    // Dedicated 1200x630 social share card (the ideal OG aspect ratio).
    // Regenerate with: php scripts/make-og-card.php
    'og_image' => '/brand/og-card.png',

    'og_image_width' => 1200,

    'og_image_height' => 630,

    'locale' => 'en_IN',

    'twitter_handle' => '@datasofttech',

    // Official social profiles. Rendered as icons in the footer and emitted as
    // Organization.sameAs so search engines can connect them to the business.
    'social' => [
        'facebook'  => 'https://www.facebook.com/profile.php?id=61590370831849',
        'instagram' => 'https://www.instagram.com/apnainvoice/',
        'linkedin'  => 'https://www.linkedin.com/showcase/apna-invoice/',
    ],

    'organization' => [
        'name' => 'Datasoft Technologies',
        'legal_name' => 'Datasoft Technologies',
        'url' => 'https://www.datasofttechnologies.com/',
        'logo' => '/brand/dst-logo.png',
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
