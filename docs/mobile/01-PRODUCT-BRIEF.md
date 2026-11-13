# 01 — Product Brief

## What it is

A phone app for Indian small businesses to make GST invoices, collect payments and keep
their books — the same account and data as apnainvoice.com, not a separate product.
Anything made on the phone appears on the web and the other way round.

## Who uses it

| User | What they do on the phone |
|---|---|
| **Owner / shopkeeper** (primary) | Makes an invoice at the counter or on site, shares it on WhatsApp, checks who owes money, records a payment |
| **Billing staff** (team role `staff`) | Quotes, invoices, records payments, adds customers/items. Cannot see accounts/GST, cannot cancel or delete |
| **Accountant / CA** (team role `accountant`) | Mostly web. On the phone: look up GST summaries, aging, export a report |
| **Admin** (team role `admin`) | Everything except team, backups, deleting the business |

Team roles are enforced by the server (`App\Models\CompanyMember::RULES` via the `team`
middleware). The app must hide what a role can't do, but never relies on hiding alone.

## Principles

1. **The server is the source of truth.** All GST math, numbering, rounding and status
   rules live in Laravel (`InvoiceCalculator`, `PaymentBook`, `CreditNoteBook`, …). The app
   may show a *preview* total but always displays what the server returns after saving.
2. **One account, one data set.** No separate mobile database; no offline writes in v1.
3. **Phone-first, not web-squeezed.** Short flows, big targets, WhatsApp/share-sheet as the
   main way out, works one-handed at a shop counter.
4. **Indian context.** ₹ formatting with lakh/crore grouping (₹1,23,456.00), GSTIN/state
   handling, UPI, Hindi-ready strings (web has only `lang/en` today).
5. **Existing customers are never impacted.** Same rule as the web roadmap: issued invoices
   and their PDFs never change because of the app.

## Phases (proposed — confirm in `06-DECISIONS.md`)

### Phase 1 — Daily selling (MVP)
Sign in / register · company setup · dashboard · customers · products · invoices (create,
edit draft, issue, cancel, PDF, share link, WhatsApp) · record/reverse payment · quotations
(full lifecycle + convert) · settings / profile / logout.

*All endpoints exist today.*

### Phase 2 — Money & compliance
Credit notes · customer ledger · payment reminders · expenses · cash memos · finance
reports (P&L, aging, GSTR-1, GSTR-3B) with CSV/PDF export · payment receipt PDF ·
multi-company switch.

*Mostly exists; receipt PDF and a few exports need new endpoints (see `03-API-MAP.md`).*

### Phase 3 — Business operations
Purchase bills & vendors · inventory / stock · team invites & roles · notifications ·
global search · bulk import · bank reconciliation · GSTR-2B.

*Needs new API endpoints — the web has these, the API does not.*

### Phase 4 — Growth & polish
Referrals · blog · backups · OTP / Google sign-in · invoice templates & styles ·
thermal print · push notifications · home-screen widgets.

### Never in the app
Super-admin panel, impersonation, blog authoring, data-breach register — stay web-only.

## Success measures

- A new user can sign up and send their first invoice on WhatsApp in **under 3 minutes**.
- An invoice made on the phone produces a **byte-identical PDF** to the same invoice on web.
- No feature on the phone shows a number the web would show differently.
