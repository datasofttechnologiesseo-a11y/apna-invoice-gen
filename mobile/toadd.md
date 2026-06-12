# To add — web features not yet at parity in the mobile app

Tracking the remaining gaps between the mobile app/API and the web app. Core
modules (customers, products, invoices, quotations, credit notes, cash memos,
expenses, P&L / aging / GSTR-1 / GSTR-3B, referrals, backups, blog, profile /
activity) are already at parity. Admin panel is intentionally web-only.

## Done
- [x] **Invoice PDF — full GST copy set (3 for goods / 2 for services).**
  Web download renders the copy set; the mobile share view rendered 1. Added an
  authed `GET /api/invoices/{invoice}/pdf` (passes `copies = defaultCopyCount()`)
  and pointed the app's "Download PDF" button at it.

## To add (priority order)

### 1. Payment receipt PDF  ⟶ quick (same pattern as invoice PDF)
- Web: `GET payments/{payment}/receipt` → DomPDF receipt (`RCPT-…`).
- App: records payments but can't download the receipt.
- Needs: `GET /api/payments/{payment}/receipt` (mirror web `InvoiceController::receipt`)
  + a "Download receipt" action on each payment row in the invoice detail screen
  (use `downloadAndShare`).

### 2. Expense voucher PDF  ⟶ quick
- Web: `GET /finance/expenses/{expense}/pdf` (redirects to cash-memo PDF if the
  expense came from a memo; else an "Expense Voucher" PDF).
- App: expense screen has no voucher download.
- Needs: `GET /api/expenses/{expense}/pdf` (mirror web `FinanceController::expensePdf`)
  + "Download voucher" button on the expense form/detail.

### 3. Invoice form — missing fields  ⟶ medium (API already supports them)
- Web invoice form collects: transporter name / id / vehicle no / transport mode,
  e-way bill no, ship-to (name/address/city/state/pin/gstin), reverse charge,
  terms, notes, invoice style.
- App `InvoiceEditScreen` only collects the basics (customer, date, items, GST).
- Needs: add these fields to the mobile invoice editor (the API `validateInvoice`
  already accepts them — see `Api\InvoiceController`).

### 4. Quotation form — missing fields  ⟶ medium
- Web quotation form: reference, delivery period, terms, notes, valid-until.
- App `QuotationInput` only sends `subject` (+ customer/date/items).
- Needs: extend `QuotationInput` + `QuotationEditScreen` with reference /
  delivery_period / terms / notes / valid_until (verify the API accepts them).

### 5. Invoice template / style picker  ⟶ medium
- Web: multiple invoice styles (`config/invoice_styles.php`), `invoices/templates`
  preview, `style` saved per invoice.
- App: always uses `classic`.
- Needs: a style picker in the invoice editor; pass `style` to the create/update
  API (already accepted).

### 6. Email-compose share (server SMTP, custom message)  ⟶ optional
- Web: `POST invoices/{invoice}/share/email` + quotations — sends via SMTP with a
  custom to / cc / subject / body and the PDF attached.
- App: uses the native share-sheet + WhatsApp + signed link instead (arguably the
  better mobile pattern). Add the server-email compose only if exact parity wanted.

### 7. Thermal 80mm print  ⟶ optional / niche
- Web: `invoices/{invoice}/print?format=thermal` — 80mm retail-counter layout.
- App: none. Lower value on mobile; revisit if needed.

### 8. Guided onboarding flow  ⟶ optional
- Web: `/setup` business → customer → done wizard.
- App: has the company-edit equivalent; a first-run wizard could be added.

## Notes
- Admin panel (super-admin, impersonation, blog authoring) stays **web-only** by
  decision.
- The custom floating `BottomTabBar` was reverted in the app (default tab bar
  restored); its self-contained copy lives at `d:/projects/library/BottomTabBar.tsx`.
