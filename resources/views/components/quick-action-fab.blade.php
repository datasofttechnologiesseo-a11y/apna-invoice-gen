@props([
    // Primary CTA - defaults to "New invoice", which is the most-pressed
    // button in the app for the target audience (SMEs, freelancers).
    'href' => route('invoices.create'),
    'label' => 'New invoice',
])

{{--
    Retired (Nov 2026). This was the phone floating "+" button. The phone
    tab bar (components/mobile-tab-bar) now has a "New" item that opens the
    whole create list, and the page headers keep their own "New ..." button,
    so a third floating control only covered the last row of each list.

    Kept so the pages that still use <x-quick-action-fab /> need no change;
    it renders nothing. The props stay accepted for the same reason.
--}}

