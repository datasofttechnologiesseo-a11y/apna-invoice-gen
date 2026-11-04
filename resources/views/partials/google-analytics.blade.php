{{--
    Google tag (gtag.js): GA4 analytics and Google Ads, loaded once for both.
    Included in the <head> of every page layout. Settings: config/services.php
    'google_tags' (production only unless GOOGLE_TAGS_ENABLED is set; never
    while an admin is impersonating a user).

    Consent follows the cookie banner (partials/cookie-banner): analytics as
    before unless the visitor turned it off; Google Ads cookies only with
    marketing consent. Until then Ads runs in consent mode and still models
    conversions without cookies. A choice made on the page updates it at once.

    A new account fires "sign_up" (GA4) and, when a label is configured, the
    Google Ads sign-up conversion, once, on the first page after sign-up
    (AccountCreator puts it in the session).
--}}
@php
    $tags = config('services.google_tags', []);
    $tagIds = array_values(array_filter([$tags['ga4'] ?? null, $tags['ads'] ?? null]));
    $signupMethod = session()->pull('analytics_signup');
    $signupSendTo = filled($tags['ads'] ?? null) && filled($tags['ads_signup_label'] ?? null)
        ? $tags['ads'] . '/' . $tags['ads_signup_label']
        : null;
@endphp
@if (($tags['enabled'] ?? false) && ! session('impersonator_id') && $tagIds)
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        (function () {
            function consent() {
                var p = null;
                try { p = JSON.parse(localStorage.getItem('cookiePrefs') || 'null'); } catch (e) {}
                var ads = p && p.marketing ? 'granted' : 'denied';
                return {
                    analytics_storage: p && p.analytics === false ? 'denied' : 'granted',
                    ad_storage: ads,
                    ad_user_data: ads,
                    ad_personalization: ads
                };
            }
            var c = consent();
            c.wait_for_update = 500;
            gtag('consent', 'default', c);
            window.addEventListener('cookie-choice-made', function () { gtag('consent', 'update', consent()); });
        })();
        gtag('js', new Date());
        {!! json_encode($tagIds) !!}.forEach(function (id) { gtag('config', id); });
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $tagIds[0] }}"></script>
    @if ($signupMethod)
        <script>
            gtag('event', 'sign_up', { method: {!! json_encode((string) $signupMethod) !!} });
            (function (sendTo) {
                if (sendTo) { gtag('event', 'conversion', { send_to: sendTo }); }
            })({!! json_encode($signupSendTo) !!});
        </script>
    @endif
@endif
