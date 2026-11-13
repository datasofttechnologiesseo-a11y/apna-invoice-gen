# 02 — Tech Stack

## Core

| Area | Choice | Why |
|---|---|---|
| Framework | **React Native + Expo**, latest stable SDK at scaffold time | One codebase for iOS + Android; managed native builds |
| Language | **TypeScript** (strict) | API shapes are typed end to end |
| Routing | **Expo Router** (file-based, native stack + tabs) | Deep links (`/i/{invoice}`) come for free; standard for new Expo apps |
| Server data | **TanStack Query** | Caching, refetch on focus, optimistic updates, retry |
| HTTP | `fetch` wrapper (or axios) with token + error normalisation | One place for 401 → logout, 422 → field errors |
| Forms | **react-hook-form + zod** | Invoice form is large; zod mirrors Laravel validation rules |
| Auth token | **expo-secure-store** | Sanctum token in Keychain / Keystore, never AsyncStorage |
| Files | `expo-file-system` + `expo-sharing` | Download PDF/CSV and hand it to the share sheet / WhatsApp |
| PDF view | `react-native-webview` or `expo-print` preview | Show the server PDF in-app |
| Small local state | React context or Zustand | Active company, UI prefs only |
| Lists | **FlashList** | Long invoice/customer lists stay smooth |
| Styling | Decided in the design phase (theme tokens + StyleSheet, or NativeWind) | Kept open until designs exist |
| i18n | `expo-localization` + i18n library, English first, Hindi-ready | Strings never hard-coded in screens |
| Errors / crashes | Sentry (or EAS Observe) | See production problems |
| Build & release | **EAS Build / Submit / Update** | Store builds and over-the-air JS fixes |

**Development builds, not Expo Go.** The last attempt got stuck on "project is
incompatible with this version of Expo Go" and had to downgrade the SDK. A development
build (`expo-dev-client`) removes that coupling.

## Backend

Existing Laravel 12 app, **Sanctum token auth**, routes in `routes/api.php`, controllers in
`app/Http/Controllers/Api`, resources in `app/Http/Resources`.

- Base URL per environment via `app.config.ts` + `EXPO_PUBLIC_API_URL`
  (local LAN IP for dev, `https://apnainvoice.com/api` for production).
- Every request sends `Accept: application/json` and `Authorization: Bearer <token>`.
- Data is scoped to the user's **active company** on the server.
- Team members act in the business they joined, under their role (`team` middleware).
- File endpoints (`/pdf`, `/export/csv`) return binary — download, don't JSON-parse.
- Rate limits exist on login/register/reminders/backups — show a friendly "try again in a
  minute" on HTTP 429.

## Proposed project layout (new `mobile/`)

```
mobile/
  app/                    Expo Router routes
    (auth)/               login, register, forgot
    (tabs)/               home, invoices, quotations, customers, more
    invoices/[id].tsx     detail; invoices/new.tsx, invoices/[id]/edit.tsx
    ...
  src/
    api/                  client.ts, endpoints/<module>.ts, types/<module>.ts
    features/<module>/    hooks (useInvoices…), components, form schemas
    components/ui/        design-system components (from the design phase)
    lib/                  money (₹ + lakh grouping), dates, gstin, files/share
    theme/                tokens
    i18n/
  app.config.ts
  eas.json
```

Rule: **one folder per web module** under `src/features/` and one file per module under
`src/api/endpoints/`. That 1:1 naming is what the sync skill relies on to find the mobile
code affected by a web change.

## Conventions

- API types are written once in `src/api/types/` and match `app/Http/Resources/*` field for
  field. When a Resource changes, its type changes in the same PR.
- Money arrives from the API as the server computed it; the app formats, never re-rounds.
- Dates: send `YYYY-MM-DD`, show `13 Nov 2026`.
- No business rule is re-implemented on the phone except for *preview* (e.g. live total
  while typing an invoice), and the preview is replaced by the server value on save.

## Quality gates (per PR)

`tsc --noEmit` · ESLint · unit tests for `lib/` (money, GSTIN) · API contract tests on the
Laravel side (`tests/Feature/Api/*`) · `expo export` builds for both platforms.
