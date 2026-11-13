# Mobile design

## Brand tokens (from the web app — `tailwind.config.js`, sidebar)

| Token | Hex | Use |
|---|---|---|
| Primary teal | `#167C88` (dark `#13636C`, tint `#EEFBFC`) | Buttons, active states, links |
| Navy | `#0E2843` | Headings, dark surfaces (web sidebar, logo "Apna") |
| Amber accent | `#F59E0B` | Main ＋ action, highlights (logo ₹ arrow) |
| Money green | `#16A34A` | Paid only |
| Danger red | `#DC2626` | Overdue / destructive only |
| Fonts | Plus Jakarta Sans (headings, amounts) · Inter (body, tabular numbers) | |

## Direction options (round 1, 2026-11-13)

Generated with the `generate-image` skill (Codex imagegen). Each shows Home, Invoices list
and Create invoice.

| Option | File | Summary |
|---|---|---|
| A — Classic Navy & Teal | `options/option-a-classic-navy-teal.png` | Navy header like the web sidebar, white cards, flat tab bar, amber floating ＋ |
| B — Teal Gradient Fintech | `options/option-b-teal-gradient-fintech.png` | Teal→navy gradient hero, navy floating tab bar with raised notch bubble (from `image.png` reference) |
| C — Light Minimal | `options/option-c-light-minimal.png` | Airy white, big navy numbers, dividers not cards, centred amber ＋ in tab bar |

**Chosen:** B — Teal Gradient Fintech (2026-11-13).

Note: these are mood/direction images. Sample data is fictional.

## Screen design (round 2, 2026-11-13)

Claude Design canvas: https://claude.ai/artifact/VmpuTegTfCGPUVKibAcsFy (private to the owner).

Generated art in `assets/` (via `generate-image`): `app-icon` (new logo mark / app icon),
`onboarding-create`, `onboarding-share`, `onboarding-reports`.

| # | Screen | What it shows |
|---|---|---|
| — | Brand sheet | App icon at 4 sizes, wordmark, colour tokens, type, status pills, buttons |
| 01 | Splash | Icon + wordmark on the teal→navy gradient, loader |
| 02 | Onboarding: Create | "GST invoices in 30 seconds", auto CGST/SGST/IGST |
| 03 | Onboarding: Share | WhatsApp sharing, UPI QR on bill, reminders |
| 04 | Onboarding: Reports | GSTR-1/3B/P&L for the CA; Create account / Sign in |
| 05 | Sign in | Mobile OTP (default) or email, Continue with Google |
| 06 | Business setup | GSTIN fetch + validation, name, state, PIN, goods/services, tax hint |
| 07 | Home | Outstanding hero + Collect now, overdue, month sales, quick actions, trend, recent |
| 08 | Invoices list | Search, status chips with counts, grouped list, amber ＋ |
| 09 | Create invoice | Customer (tax type), items with qty stepper + GST, more details, live total, Save & issue |
| 10 | Invoice detail | Balance due + progress, Payment/Remind/Share/PDF, items + IGST, activity |
| 11 | Record payment | Amount with quick chips, UPI/Cash/Bank/Cheque, date, ref, WhatsApp receipt |
| 12 | Issued & share | Success, mini invoice with UPI QR, WhatsApp/link/email/print |
| 13 | Quotations | Pipeline stats, accepted quote → Convert to invoice, statuses |
| 14 | Customers | Customers / Products tabs, search, sorted by amount owed |
| 15 | Customer ledger | Contact actions, to-collect + Remind, running-balance ledger |
| 16 | Reports & GST | Month picker, profit, GST payable + 3B due date, report tiles, send to CA |
| 17 | More | Profile with role badge, Money / Business / Account groups |
| — | Tab bar component | Navy floating bar, amber raised bubble on the active tab |

## Round 3 — matched to the live web app (2026-11-13)

After a full QA of the web (`../07-WEB-FEATURE-INVENTORY.md`) the design was brought in line
with what the web actually does:

- **05 Log in** — email + password + "Sign in with Google" + forgot password. Mobile-OTP
  login removed (the web has none).
- **07 Home** — same blocks as the web dashboard: outstanding receivables (+ overdue), sales
  this month, received, payables, *Needs attention*, *Who owes you* with Remind; header has
  search, notifications and **＋ New**.
- **14 Parties** — Customers / Vendors (web "Parties"); items moved to 28.
- **17 More** — grouped exactly like the web sidebar: Sales, Purchases, Inventory & banking,
  Reports, Settings.
- Tab bar: Home · Invoices · Quotes · Parties · More.

New screens:

| # | Screen | Web source |
|---|---|---|
| 18 | Create account — name, email, mobile, password, referral, terms, marketing, Google | `/register` |
| 19 | Verify email code — 6 digits, resend timer | `/register/verify` |
| 20 | Forgot password | `/forgot-password` |
| 21 | ＋ New menu — invoice, quote, payment in/out, credit note, purchase, expense, cash memo, customer, vendor, item, team member | top-bar "New" |
| 22 | Search — invoices, parties, quotes, pages | top-bar search |
| 23 | Notifications | bell |
| 24 | Invoice actions — WhatsApp, email, link, remind, edit, duplicate, PDF, thermal print, credit note, cancel | invoice page "More" |
| 25 | Issue credit note — reason, returned quantities, total | `/invoices/{id}/credit-notes/create` |
| 26 | Purchases — purchase invoices / payments made, to pay, ITC, Pay | `/purchases`, payments made |
| 27 | Expenses — expenses / cash memos, category split | `/finance/expenses`, cash memos |
| 28 | Items & stock — items / stock summary, low-stock alert | `/products`, stock summary |
| 29 | GST returns — GSTR-1, 2B match, 3B, HSN with exports | GST returns section |
| 30 | Report (shared template) — report picker, period, totals, table, Excel/CSV/PDF/Share | `/reports` (all 15) |
| 31 | Team & roles — invite with role, pending invites | `/team` |
| 32 | Company settings — identity, logo, numbering, defaults, bank/UPI, exports, lock books | `/companies/{id}/edit` |

Screens that need new API endpoints: 05/18/19/20 (Google, email-code verify, forgot
password), 21–23 (search, notifications), 24 (duplicate, receipt/quotation PDF), 26 (purchases,
vendors, payments made), 28 (stock), 29 (2B, HSN), 30 (reports export), 31 (team). See
`../03-API-MAP.md` and `../07-WEB-FEATURE-INVENTORY.md`.
