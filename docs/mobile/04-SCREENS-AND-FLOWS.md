# 04 — Screens and Flows

Input for the design phase. Lists **what** each screen must contain and do — not how it
looks. Phase numbers match `01-PRODUCT-BRIEF.md`.

## Navigation (proposed)

Bottom tabs: **Home · Invoices · Quotes · Customers · More**
A floating / centre **＋ New** action (invoice, quote, customer, product, payment, expense).
"More" holds products, finance, credit notes, cash memos, company, team, settings.

## Screen inventory

### Onboarding & auth (P1)
| Screen | Must have |
|---|---|
| Welcome | Value in one line, Sign in, Create account |
| Sign in | Email + password, forgot password link, (P4: phone OTP, Google) |
| Register | Name, phone (+91), email, password ×2, referral code (optional) |
| Business setup | Business name, state (drives GST), GSTIN (optional, validated), address, bank/UPI (optional, skippable) |

### Home (P1)
Outstanding, overdue, this month's sales; recent invoices; quick actions; P2: action items
(overdue reminders, drafts to issue). Company switcher in header (P2).

### Invoices (P1)
| Screen | Must have |
|---|---|
| List | Status filter chips (draft / issued / partially paid / paid / overdue / cancelled), search, amount + due, pull to refresh |
| Detail | Header (number, status, customer, dates), lines, tax split (CGST+SGST or IGST), totals, payments list, actions: issue, record payment, share (WhatsApp / link / PDF), remind, cancel, delete draft, credit note (P2) |
| Create / edit | Customer picker (with quick-add), date / due date, line items with product search, qty, rate, GST rate quick-picks, discount; live total preview; collapsible "More details": transport, e-way bill, ship-to, reverse charge, terms, notes, style. Save draft / Save & issue |
| Record payment (sheet) | Amount (defaults to balance), date, mode (cash/UPI/bank/cheque), reference |
| PDF viewer | View, share, download |

### Quotations (P1)
List (status chips), detail (send, accept, decline, convert to invoice, share, delete),
create/edit (same item editor as invoice + reference, valid-until, delivery period, terms, notes).

### Customers & products (P1)
Lists with search; customer form (name, phone, email, GSTIN, state, address); product form
(name, HSN/SAC, unit, rate, GST rate, goods/service). Customer detail with ledger (P2).

### Finance (P2)
Finance home → P&L, aging, GSTR-1, GSTR-3B (period picker, summary, export CSV/PDF → share
to CA); expenses list/form; cash memo list/form/PDF.

### More / settings (P1–P2)
Profile, change password, company details, switch company, team (P3), backups (P4),
referrals (P4), activity log, help/handbook, logout, delete account.

## Key flows to design end to end

1. **First invoice in 3 minutes** — register → business setup → ＋ New invoice → add
   customer inline → add item inline → Save & issue → share on WhatsApp.
2. **Counter sale** — ＋ New → existing customer → 2 products → issue → record full
   payment as UPI → share receipt.
3. **Chasing money** — Home "overdue" → invoice → Remind (WhatsApp) → later record
   part payment.
4. **Quote to invoice** — create quote → send → accept → convert → issue.
5. **Month-end for the CA** — Finance → GSTR-1 for last month → export → share.

## States every screen needs

Loading (skeleton), empty (with the next action), error (retry), offline banner,
no-permission (team role), and long data (12-digit ₹ amounts, long customer names).
