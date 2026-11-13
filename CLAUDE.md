# Apna Invoice

Laravel 12 GST invoicing app (web) + JSON API for the mobile app (`routes/api.php`, Sanctum).
Mobile app (React Native + Expo) plans live in `docs/mobile/`.

## Web ↔ mobile sync

Whenever a change touches business rules, models/migrations, controllers, resources, routes,
team roles (`CompanyMember::RULES`) or PDF templates, run the `mobile-sync` skill before
finishing, and record the result in `docs/mobile/05-WEB-MOBILE-SYNC.md`.
