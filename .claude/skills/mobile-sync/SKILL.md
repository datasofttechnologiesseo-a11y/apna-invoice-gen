---
name: mobile-sync
description: Check and carry a web/backend change over to the Apna Invoice mobile app and API. Use after changing anything in app/Services, app/Models, database/migrations, app/Http/Controllers, app/Http/Resources, routes/, CompanyMember roles, or PDF templates — or when asked "does mobile need this too?", "sync mobile", or "update parity".
---

# Mobile sync

The mobile app (`mobile/`, React Native + Expo) uses the Laravel JSON API (`routes/api.php`,
`app/Http/Controllers/Api/*`, `app/Http/Resources/*`). Web and API controllers must use the
same services so numbers match. Full process and the parity matrix:
`docs/mobile/05-WEB-MOBILE-SYNC.md`.

## Steps

1. **List what changed.** `git diff --name-only` (plus staged) for the current work. Ignore
   pure CSS, landing/SEO pages, `resources/views/admin`, and docs.

2. **Classify each change** using the table in `05-WEB-MOBILE-SYNC.md` → "How to tell what a
   change affects". Map module names 1:1:
   web `XController` → `app/Http/Controllers/Api/XController.php` → `XResource` →
   `mobile/src/api/endpoints/<module>.ts`, `mobile/src/api/types/<module>.ts`,
   `mobile/src/features/<module>/`.

3. **API side** (do it in the same change when the web change alters behaviour):
   - Service changed → confirm the Api controller calls the same service, not a copy.
   - New/renamed field → add to the Resource and the Api validation rules.
   - New web feature with no API route → add a row to `docs/mobile/03-API-MAP.md` as ❌
     (build the endpoint only if the user asked for it).
   - Run `php artisan test tests/Feature/Api` and the module's feature tests.

4. **Mobile side:**
   - If `mobile/` exists and the affected module is built there, update types, form schema,
     screens and status/role handling; run `npx tsc --noEmit` in `mobile/`.
   - If the module isn't built yet, or the user wants it later, add a row to
     "Pending mobile work".

5. **Record it** in `docs/mobile/05-WEB-MOBILE-SYNC.md`:
   - a "Change log" row: date, short change, and one of *API updated / Mobile updated /
     Mobile follow-up / No mobile impact (reason)*;
   - update the parity matrix cell if a module's status moved.

6. **Report** to the user in two or three lines: what was carried over, what is pending,
   and anything you could not verify.

## Rules

- GST math, numbering, rounding and status rules live only on the server. Never copy them
  into the app beyond a display preview.
- Issued invoices and their PDFs must never change because of a sync.
- Don't build new API endpoints or mobile screens that weren't asked for — record them.
