# 06 — Open Decisions

Answer these before coding starts. Each has a recommendation; change any you disagree with.

| # | Question | Recommendation | Your decision |
|---|---|---|---|
| 1 | Platforms at launch | Android first (Play Store), iOS right after — most target users are on Android | |
| 2 | Phase 1 scope | As in `01-PRODUCT-BRIEF.md` (daily selling only) | |
| 3 | Sign-in methods at launch | Email + password now; phone OTP soon after (Indian users expect it) — needs new API endpoints | |
| 4 | Offline | v1 online-only with clear offline banner; read-only cache of recent data; offline drafts later | |
| 5 | Languages | English at launch, Hindi in Phase 2; all strings through i18n from day one | |
| 6 | Pricing / plans in app | Same plan as web; no in-app purchase in v1 (avoids store billing rules) | |
| 7 | Styling approach | Decide after designs: theme tokens + StyleSheet, or NativeWind | |
| 8 | Design direction | Match web brand (logo, colours) but phone-native patterns — decide in the design phase | |
| 9 | Push notifications | Phase 2: overdue invoice, payment received, quote accepted | |
| 10 | Store identity | App name "Apna Invoice", bundle id e.g. `com.apnainvoice.app`, Datasoft developer accounts | |
| 11 | Analytics / crash reporting | Sentry for crashes; analytics TBD (privacy: DPDP consent like the web) | |
| 12 | Where new API endpoints go | Same Laravel repo, `routes/api.php`, with a test in `tests/Feature/Api` for each | |
| 13 | Admin in the app? | One app for business users, role-based (Owner / Admin / Accountant / Staff — already enforced by the API). Platform super-admin (users, impersonation, blog, breaches) stays web-only; revisit a read-only "admin pulse" later if needed | |
| 14 | Design direction | **B — Teal Gradient Fintech** | ✅ chosen 2026-11-13 |

## Design phase plan

1. Pick 5 key screens from `04-SCREENS-AND-FLOWS.md` (Home, Invoice list, Invoice create,
   Invoice detail, Sign in).
2. Generate 2–3 visual directions for those 5 (Claude Design / image generation),
   using the web brand (logo: `Apnainvoice logo.jpg`).
3. Choose one direction → turn it into tokens (colour, type, spacing, radius) and a
   component list.
4. Design the remaining screens in that direction.
5. Freeze → start coding.
