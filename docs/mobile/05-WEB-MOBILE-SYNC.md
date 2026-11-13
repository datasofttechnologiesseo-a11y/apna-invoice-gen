# 05 — Web ↔ Mobile Sync

The web app moves fast. This file is how the mobile app keeps up, and the **parity matrix**
is the single record of where each module stands. The `mobile-sync` skill
(`.claude/skills/mobile-sync/SKILL.md`) follows this process and edits this file.

## The rule

> Any change to a **business rule, data field, status, permission or endpoint** on the web
> is also a mobile change until proven otherwise.

The change is not done until one of these is true and written in the change log below:

- **API updated** — the `Api\*` controller/resource/test was changed in the same PR, and
- **Mobile updated** — the app code was changed in the same PR, **or**
- **Mobile follow-up** — a row was added to "Pending mobile work" with what to do, **or**
- **No mobile impact** — with a one-line reason (e.g. "web-only admin page", "CSS only").

## How to tell what a change affects

| If you changed… | Check on the API side | Check in the app |
|---|---|---|
| `app/Services/*` (GST math, numbering, aging, P&L…) | API controller uses the same service? API test still passes? | Any client-side *preview* that copies the rule |
| `app/Models/*` / a migration (new column) | Matching `app/Http/Resources/*Resource` exposes it? Validation in `Api\*Controller` accepts it? | `src/api/types/<module>.ts`, the form schema, the detail screen |
| A web controller's validation rules | Same rules in `Api\*Controller` | zod schema in `src/features/<module>/` |
| Status values / lifecycle (draft → issued …) | Resource returns the new status | Status chips, badges, allowed actions |
| `CompanyMember::RULES` (team roles) | Route names for API covered? | Action visibility per role |
| A new web route / feature | Is there an API route? If not → add to `03-API-MAP.md` as ❌ | Parity matrix row |
| PDF Blade templates | `/pdf` endpoints render the same view | Nothing (app shows server PDF) |
| Text only / CSS / landing page / SEO / admin | — | Usually no impact |

Module name mapping (keep 1:1): web `InvoiceController` ↔ `Api\InvoiceController` ↔
`mobile/src/features/invoices/` ↔ `mobile/src/api/endpoints/invoices.ts`.

## Parity matrix

Legend: ✅ done · 🟡 partial · ❌ not started · ⛔ web-only by decision

| Module | Web | API | Mobile | Phase | Notes |
|---|---|---|---|---|---|
| Auth (email/password, sign-up) | ✅ | 🟡 | ✅ | 1 | API sign-up lacks email-code verification |
| Auth (Google, reset) | ✅ | ❌ | 🟡 | 4 | Forgot password opens the web page; Google behind `FEATURES.googleSignIn` |
| Account deletion | ✅ | ✅ | ✅ | 1 | Store requirement |
| Company / multi-company | ✅ | ✅ | ❌ | 1–2 | Opens web settings for now |
| Dashboard | ✅ | 🟡 | ✅ | 1 | API has no "received this month" / payables yet |
| Customers + ledger | ✅ | ✅ | ✅ | 1–2 | List, search, ledger, quick-add in invoice |
| Products | ✅ | 🟡 | 🟡 | 1 | Search + pick in invoice; no item screen yet |
| Invoices | ✅ | 🟡 | ✅ | 1 | List/filter/search, create/edit draft, issue, cancel, delete, PDF, WhatsApp, remind |
| Payments | ✅ | 🟡 | ✅ | 1 | Record payment; no receipt PDF |
| Credit notes | ✅ | ✅ | ❌ | 2 | |
| Quotations | ✅ | 🟡 | 🟡 | 1 | List, detail, send/accept/decline/convert; create on web |
| Expenses | ✅ | 🟡 | ❌ | 2 | No voucher PDF |
| Cash memos | ✅ | 🟡 | ❌ | 2 | No exports |
| Finance reports (P&L, aging, GSTR-1/3B) | ✅ | 🟡 | ❌ | 2 | No GSTR-1 JSON/XLSX |
| GSTR-2B | ✅ | ❌ | ❌ | 3 | |
| Purchase bills / vendors | ✅ | ❌ | ❌ | 3 | |
| Inventory / stock | ✅ | ❌ | ❌ | 3 | |
| Bank reconciliation | ✅ | ❌ | ❌ | 3 | |
| Team | ✅ | ❌ | ❌ | 3 | Roles already enforced on API |
| Notifications / search | ✅ | ❌ | ❌ | 3 | |
| Import | ✅ | ❌ | ❌ | 3 | |
| Reminders | ✅ | ✅ | ❌ | 2 | |
| Referrals | ✅ | ✅ | ❌ | 4 | |
| Backups | ✅ | ✅ | ❌ | 4 | |
| Blog (read) | ✅ | ✅ | ❌ | 4 | |
| Admin panel, blog authoring, breaches | ✅ | ⛔ | ⛔ | — | Web-only |

## Pending mobile work

Items created by web changes that the app still has to absorb.

| Date | Web change | What the app needs | Status |
|---|---|---|---|
| — | — | — | — |

## Change log

| Date | Change (PR / commit) | Mobile impact |
|---|---|---|
| 2026-11-13 | Old `mobile/` removed; docs created | Fresh start |
| 2026-11-13 | New `mobile/` app (Expo SDK 58): Phase 1 screens on the existing API | Mobile updated |
