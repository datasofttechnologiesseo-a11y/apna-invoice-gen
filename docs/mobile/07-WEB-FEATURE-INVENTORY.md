# 07 — Web Feature Inventory & QA (2026-11-13)

What the website actually does today, checked by running it locally with dummy data, and where
each feature lives in the mobile app. **This is the source of truth for app scope** — the app
copies the web's features, wording and rules; the design only changes the layout for a phone.

## How it was checked

- Local server: `php artisan serve --port=8741` (`.claude/launch.json` → `apnainvoice-web`), SQLite.
- Login: `LocalTestUserSeeder` (local-only user, credentials in the seeder file).
- Data was created **through the mobile JSON API** (so the API was tested too), then every web
  page was opened from the signed-in session:
  6 customers · 8 items (goods + services) · 9 invoices (draft, issued, paid, part-paid, overdue,
  cancelled) · 5 payments · 1 credit note · 4 quotations (draft / sent / accepted→converted /
  declined) · 5 expenses · 2 cash memos · 1 vendor · 1 purchase invoice (part paid) · 2 team invites.

### Results
- **All 44 web pages return 200, no errors.** All PDFs (invoice, quotation, receipt, expense
  voucher, aging), GSTR-1 JSON/XLSX, thermal print, and every API read endpoint work.
- API validation is strict (invalid GSTIN checksums are rejected — use real-format GSTINs in tests).
- **Local setup problems fixed (not code bugs):** `vendor/` was on Laravel 11.51 while
  `composer.lock` pins 12.70 → `composer install`; a stale route cache hid `finance.gstr1` and
  crashed the dashboard → `php artisan optimize:clear`. 24 pending migrations were run on the
  local SQLite (backup kept outside the repo).

## Authentication — the app must match this exactly

| Web behaviour | App | API today |
|---|---|---|
| **Sign in:** email + password, "Remember me", "Forgot your password?" | Same; token kept in secure storage replaces "Remember me" | ✅ `POST auth/login` |
| **Sign in with Google** (shown when Google keys are set) | Same button | ❌ needs a token-exchange endpoint |
| **Sign up:** name, email, mobile (10-digit, +91), password, referral code, accept terms, marketing opt-in | Same fields | 🟡 `auth/register` has no terms/marketing fields |
| **Verify:** 6-digit code sent **by email**, resend after 30 s, 10-min expiry | Same screen | ❌ API registers without verification — **must add** (security + parity) |
| **Sign up with Google** → enter mobile → verify | Same | ❌ |
| **Forgot / reset password** (email link) | Request screen in app; link opens web reset | ❌ needs `auth/forgot-password` |
| No mobile-OTP login | **No OTP login in the app** | — |

## Navigation — web sidebar → app

| Web section | Web pages | App placement |
|---|---|---|
| Home | Dashboard | **Home** tab |
| Sales | Invoices · Quotations · Credit notes · Payments received | **Invoices** tab, **Quotes** tab; credit notes & receipts in More → Sales |
| Purchases | Purchase invoices · Payments made · Expenses · Cash memos | More → Purchases |
| Parties | Customers · Vendors | **Parties** tab (Customers / Vendors) |
| Inventory | Items · Stock summary | More → Items & stock |
| Banking | Bank reconciliation | More → Banking (statement upload is desk work — view + match only) |
| GST returns | GSTR-1 · GSTR-2B match · GSTR-3B · HSN summary | More → GST returns |
| Reports | P&L · Receivables aging · Payables aging · All reports (15 reports × Excel/CSV/PDF/Print) | More → Reports (one shared report screen) |
| Settings | Company · Team · Invoice templates · Backups · Profile · Activity log · Data & privacy · Refer a friend · Help | More → Settings |
| Top bar | Global search · company switcher · **+ New** menu · help · WhatsApp chat · notifications · profile | App header: search, company switcher, notifications; **＋** in tab bar opens the New menu |

## Dashboard (web) — the app Home must show the same

1. KPIs: **Sales this month** (incl. GST, net of credit notes) · **Received this month** ·
   **Outstanding receivables** (with overdue) · **Payables**.
2. **Needs attention** — overdue invoices, GSTR-1 / GSTR-3B due with amount, each with an action.
3. **GST returns** for the period — GSTR-1, GSTR-2B match, GSTR-3B with due dates.
4. **Who owes you** — top customers with **Remind** (WhatsApp).
5. **Recent invoices** and **Trends** (sales / purchases, monthly / yearly).

## Forms — fields the app must offer (same names as web)

- **Invoice:** customer (+ quick-add: name, state, GSTIN, phone, email), invoice no., date, due
  date, items (item, qty, rate, discount, GST), bill discount ₹/%, **paid amount + paid by +
  reference** (advance at issue), terms, notes, supply type (incl. export/SEZ: port code,
  shipping bill no./date), reverse charge, PDF style, transport (name, ID, vehicle, mode, e-way
  bill), ship-to (consignee, address, city, state, PIN, GSTIN).
- **Invoice actions:** record payment, download PDF, print, thermal print (80 mm), WhatsApp,
  email, copy link, send reminder, edit, **duplicate**, issue credit note, cancel, activity.
- **Quotation:** customer, quote no., date, valid until, tax mode (auto), subject, customer
  reference, delivery period, items, terms, notes, discount; send / accept / decline / convert.
- **Item:** name, kind, item code, description, active, HSN/SAC, unit (UQC), sale price, GST %,
  **track stock, opening stock, low-stock alert, purchase price**.
- **Purchase invoice:** vendor, bill no., bill date, due date, interstate, reverse charge, ITC
  eligible, category, items, bill discount, **paid now + method + date + reference**.
- **Company settings:** identity, contact, address, logo & signature, numbering, invoice
  defaults, bank & UPI, exports/SEZ, lock books.
- **Team:** invite by email with role Admin / Accountant-CA / Billing staff; pending invites can
  be copied, shared on WhatsApp, resent, re-roled or removed.

## API gaps this inventory adds (on top of `03-API-MAP.md`)

Email-code verification on register · Google token exchange · forgot password · purchases &
vendors · payments made · stock summary · team · bank reconciliation (read/match) · GSTR-2B ·
HSN summary · reports hub exports · invoice duplicate · receipt PDF · quotation PDF ·
notifications · global search · privacy/consent.
