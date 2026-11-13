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
| Company / multi-company | ✅ | ✅ | ✅ | 1–2 | settings/* (profile, invoice numbering, bank, logo/signature), business switcher |
| Dashboard | ✅ | ✅ | ✅ | 1 | `DashboardSummary` shared: charts, status mix, aging, top dues, GST dates, action items |
| Customers + ledger | ✅ | ✅ | ✅ | 1–2 | List, search, ledger, quick-add in invoice |
| Products | ✅ | ✅ | ✅ | 1 | items/* with stock fields, low-stock filter (`ProductRules`) |
| Invoices | ✅ | ✅ | ✅ | 1 | + duplicate, edit issued details, thermal print (expo-print) |
| Payments | ✅ | ✅ | ✅ | 1 | Record payment, receipt PDF |
| Credit notes | ✅ | ✅ | ✅ | 2 | invoices/[id]/credit-note |
| Quotations | ✅ | ✅ | ✅ | 1 | Create/edit, PDF, send/accept/decline/convert |
| Expenses | ✅ | ✅ | ✅ | 2 | List/filters/exports, create/edit; linked entries read-only |
| Cash memos | ✅ | ✅ | ✅ | 2 | Create/edit/PDF |
| Finance reports (P&L, aging, GSTR-1/3B, HSN, registers) | ✅ | ✅ | ✅ | 2 | GSTR-1 JSON/XLSX/CSV; registers from GET /reports |
| GSTR-2B | ✅ | ✅ | ✅ | 3 | Upload, match, CSV export (`Gstr2bBook`) |
| Purchase bills / vendors | ✅ | ✅ | ✅ | 3 | Same `PurchaseBook` / `VendorBook` as web |
| Inventory / stock | ✅ | ✅ | ✅ | 3 | Stock ledger, adjust/count, restore (`StockLedger`) |
| Bank reconciliation | ✅ | ❌ | ❌ | 3 | |
| Team | ✅ | ✅ | ✅ | 3 | Roles from `/me.can`; server enforces |
| Notifications / search | ✅ | ✅ | ✅ | 3 | `NotificationFeed`, `SearchSuggestions` |
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
| 2026-11-13 | API for vendors, purchase bills + payments, GSTR-2B, stock ledger/adjust, product restore | Screens for these modules (endpoints in 03-API-MAP) | ✅ Done (vendors/, purchases/, gst/gstr2b, items/[id]/stock) |
| 2026-11-13 | API for team, notifications, search suggest, reports (JSON + xlsx/csv/pdf), GSTR-1 JSON/XLSX + HSN rows, invoice duplicate / thermal print (HTML) / templates, receipt / quotation / expense PDFs, cash memo edit, company lut_number / next_invoice_number / logo + signature (`POST companies/{id}/branding`) | Screens and actions for these (routes in `routes/api/workspace.php`); role visibility follows the web route names | ✅ Done (settings/*, notifications, search, reports/[report], invoice actions) |

## Change log

| Date | Change (PR / commit) | Mobile impact |
|---|---|---|
| 2026-11-13 | Old `mobile/` removed; docs created | Fresh start |
| 2026-11-13 | New `mobile/` app (Expo SDK 58): Phase 1 screens on the existing API | Mobile updated |
| 2026-11-13 | API: vendors, purchases (+payments), GSTR-2B, product stock/restore; web logic moved to `VendorBook`, `PurchaseBook`, `Gstr2bBook`, `StockLedger` | API updated; Mobile follow-up |
| 2026-11-13 | API: team, notifications, search, reports, GSTR-1 files, documents, cash memo edit, company settings/branding (`routes/api/workspace.php`); web logic moved to `TeamManager`, `NotificationFeed`, `SearchSuggestions`, `CompanySettings`, `InvoiceDuplicator`, `CashMemoBook`, `ReportExporter::screen` | API updated; Mobile follow-up |
| 2026-11-13 | Dashboard data moved to `DashboardSummary` (web + `/api/dashboard` charts, aging, GST, action items); `ProductRules` shared by web + API (stock fields, `?stock=low`, `GET /units`); `/me` returns `role` + `can`; `GET /expenses/{id}`; expense/cash-memo resources add `purchase_bill_id`, `itc_eligible`, `gst_rate` | API updated; Mobile updated (new Home, More hub, all web modules) |
