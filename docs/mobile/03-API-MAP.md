# 03 — API Map

Which endpoint each app feature uses. Source: `routes/api.php` as of 2026-11-13.
All routes below sit under `/api`. ✅ = endpoint exists · 🟡 = partly · ❌ = needs a new endpoint.

The web counterpart is the controller in `app/Http/Controllers/` with the same name;
the API version is in `app/Http/Controllers/Api/`. Both must call the **same services**
(`app/Services/*`) so numbers match.

## Auth & account

| Feature | Endpoint | Status |
|---|---|---|
| Register (name, phone, email, password, referral code) | `POST auth/register` | 🟡 no terms / marketing consent, and **no email-code verification** (web verifies a 6-digit code by email) |
| Verify email code / resend | — | ❌ must add (mirror `RegisteredUserController::verify/resend`) |
| Login (email + password) | `POST auth/login` | ✅ |
| Current user + companies | `GET me` | ✅ |
| Logout (revoke token) | `POST auth/logout` | ✅ |
| Google sign-in / sign-up (+ mobile step) | — | ❌ needs token-exchange endpoint |
| Forgot password | — | ❌ (web Breeze only) |
| ~~Login with phone OTP~~ | — | Not on web → not in app |
| Edit profile / change password | `PUT profile`, `PUT profile/password` | ✅ |
| Activity log | `GET profile/activity` | ✅ |
| Delete account | `DELETE profile` | ✅ |
| Privacy / marketing consent | — | ❌ (web `/profile/privacy`) |

## Company

| Feature | Endpoint | Status |
|---|---|---|
| List / create / show / update | `GET/POST companies`, `GET/PUT companies/{id}` | ✅ |
| Active company | `GET companies/active` | ✅ |
| Switch company | `POST companies/{id}/switch` | ✅ |
| States lookup (public) | `GET states` | ✅ |
| First-run setup wizard (web `/business → /customer → /done`) | use company update | 🟡 |

## Dashboard

| Feature | Endpoint | Status |
|---|---|---|
| KPIs, recent invoices | `GET dashboard` | ✅ — check it has the same action items / charts as the web dashboard |
| Notifications | `GET notifications`, `POST notifications/seen` | ✅ |
| Global search | `GET search/suggest?q=&scope=invoices\|customers` | ✅ |

## Customers & products

| Feature | Endpoint | Status |
|---|---|---|
| Customers CRUD | `apiResource customers` | ✅ |
| Customer ledger | `GET customers/{id}/ledger` | ✅ |
| Products CRUD | `apiResource products` | ✅ |
| Product search (invoice picker) | `GET products/search` | ✅ |
| Restore archived product | `POST products/{id}/restore` | ✅ |
| Stock ledger / adjust / count | `GET|POST products/{id}/stock` | ✅ |
| Bulk import (CSV/XLSX) | — | ❌ (web `ImportController`) |

## Invoices & payments

| Feature | Endpoint | Status |
|---|---|---|
| List / filter / search | `GET invoices` | ✅ |
| Create / update draft / delete | `POST`, `PUT`, `DELETE invoices/{id}` | ✅ |
| Issue (finalize) | `POST invoices/{id}/finalize` | ✅ |
| Cancel | `POST invoices/{id}/cancel` | ✅ |
| PDF (full GST copy set) | `GET invoices/{id}/pdf` | ✅ |
| Public share link | `GET invoices/{id}/share-link` | ✅ |
| Payment reminder (email / WhatsApp / SMS) | `POST invoices/{id}/remind` | ✅ |
| Record payment / reverse payment | `POST invoices/{id}/payments`, `DELETE payments/{id}` | ✅ |
| Payment receipt PDF | `GET payments/{id}/receipt` | ✅ |
| Duplicate invoice | `POST invoices/{id}/duplicate` | ✅ |
| Invoice templates (+ sample PDF) | `GET invoice-templates`, `GET invoice-templates/{key}/preview` | ✅ |
| Invoice style / template picker | `style` field accepted on save; no list endpoint | 🟡 (web `invoices/templates`) |
| Email share with custom message | — | ❌ (optional; share sheet may be enough) |
| Thermal 80mm print | `GET invoices/{id}/print` (JSON `html` for expo-print) | ✅ |
| Transport / e-way / ship-to / reverse-charge fields | accepted by API validation | ✅ (app must expose them) |

## Credit notes

| Feature | Endpoint | Status |
|---|---|---|
| List / create per invoice | `GET/POST invoices/{id}/credit-notes` | ✅ |
| Delete | `DELETE credit-notes/{id}` | ✅ |
| PDF | `GET credit-notes/{id}/pdf` | ✅ |

## Quotations

| Feature | Endpoint | Status |
|---|---|---|
| CRUD | `GET/POST/PUT/DELETE quotations` | ✅ |
| Send / accept / decline / convert | `POST quotations/{id}/send|accept|decline|convert` | ✅ |
| Share link | `GET quotations/{id}/share-link` | ✅ |
| Quotation PDF (authed) | `GET quotations/{id}/pdf` | ✅ |

## Finance

| Feature | Endpoint | Status |
|---|---|---|
| P&L | `GET finance/pnl` | ✅ |
| Aging + CSV/PDF | `GET finance/aging[/export/csv|pdf]` | ✅ |
| GSTR-3B + CSV/PDF | `GET finance/gstr3b[/export/csv|pdf]` | ✅ |
| GSTR-1 + CSV | `GET finance/gstr1[/export/csv]` | ✅ |
| GSTR-1 JSON / XLSX (portal upload) | `GET finance/gstr1/export/json\|xlsx?month=YYYY-MM&frequency=` | ✅ (HSN rows also in `GET finance/gstr1` → `hsn`) |
| GSTR-2B upload & match + CSV | `GET|POST finance/gstr2b`, `GET finance/gstr2b/export` | ✅ |
| Expenses CRUD + CSV/PDF | `expenses`, `finance/expenses/export/*` | ✅ |
| Expense voucher PDF | `GET expenses/{id}/pdf` | ✅ |
| Cash memo edit | `PUT cash-memos/{id}` | ✅ |
| Cash memos list/create/show/delete/PDF | `cash-memos/*` | ✅ |
| Cash memo exports | — | ❌ |
| Reports hub | `GET reports`, `GET reports/{key}?from=&to=`, `GET reports/{key}/export/xlsx\|csv\|pdf` | ✅ |
| Bank reconciliation | — | ❌ |

## Purchases

| Feature | Endpoint | Status |
|---|---|---|
| Purchase bills CRUD + payments | `purchases`, `purchases/{id}/payments[/{paymentId}]` | ✅ |
| Vendors CRUD + search | `vendors` | ✅ |

## Team, growth, utilities

| Feature | Endpoint | Status |
|---|---|---|
| Team invite / roles / remove / leave | `GET/POST team`, `PATCH/DELETE team/{id}`, `POST team/{id}/resend`, `POST team/leave/{id}` | ✅ |
| Company logo / signature | `POST companies/{id}/branding` (multipart) | ✅ |
| Referrals | `GET referrals` | ✅ |
| Blog (public) | `GET blog`, `GET blog/{slug}` | ✅ |
| Backups (info / download / email / toggle) | `backup/*` | ✅ |
| Push notifications (device token register) | — | ❌ (new) |

## Before Phase 1 coding — verify

The API was built alongside the previous app. Before relying on it:

1. Run `php artisan test tests/Feature/Api` and confirm green.
2. Diff each `Api\*Controller` against its web controller for rules added since
   (GST 2.0 amendments of Oct 2026: credit-note numbering, cancel rules, advance receipts,
   UTGST, 16-character invoice numbers). `AMENDMENTS-ROADMAP-2026-10.md` #16 says
   "API guards" were done — confirm each item.
3. Check `GET me` returns the user's **team role** so the app can hide forbidden actions.
