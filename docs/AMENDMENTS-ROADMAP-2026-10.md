# Apna Invoice — amendments to be the best GST billing software for Indian users

Prepared 11 Oct 2026. Based on three inputs gathered this week:

1. Two code audits of the invoice/tax logic and the business flows (findings verified in code).
2. A verified GST rule sheet as of 10 Oct 2026 (CGST Rules 46/48/49/53, IGST ss.10/12/16, GST 2.0 rate
   notifications, e-invoice and e-way thresholds, GSTR-1/3B table rules, IMS, s.34 and s.170).
3. A competitive study of Vyapar, myBillBook, Zoho Books/Invoice, TallyPrime, Swipe, Refrens, Busy, Marg,
   Khatabook, ClearOne, GimBooks, Giddh and Sleek Bill (features, flows, pricing, user complaints).

**Rule for every amendment: existing customers are never impacted.** Issued invoices and their PDFs never
change; fixes apply to new/draft documents; migrations only add; new rules arrive as defaults and warnings,
never as blocks on a format or rate an existing business already uses.

---

## 1. Done this week (uncommitted, on `main`, all tests green)

| Amendment | Why |
|---|---|
| Line **Amount** now includes GST, with Taxable value and the CGST/SGST (or IGST) amount shown per line | Rate and Amount read the same; Indian buyers expect the line to show what they pay |
| **CGST/SGST computed per head** at half the rate, rounded separately | ₹100.30 @ 18% gave 9.03/9.02; GSTR-1 rejects CGST ≠ SGST. Matches the NIC e-invoice schema |
| **Rule 48 copy labels** corrected (Duplicate for *Transporter*, Triplicate for *Supplier*); services get no transporter copy | Were swapped on every goods PDF |
| **Rule 46(e)** warning now at taxable value ≥ ₹50,000 (was ₹2.5 lakh) | Wrong threshold |
| **Advance on a draft is booked as a receipt** at issue; back-fill for issued invoices | First payment used to wipe the advance (₹4,000 advance + ₹2,000 payment showed paid ₹2,000) |
| **Quotation → invoice** now carries the line total (Amount printed ₹0.00 otherwise) + back-fill | Regression guard |
| **GSTR-1 export**: reverse-charge invoices labelled Tax Invoice, not Bill of Supply | Wrong document type in the CA's file |
| "Add New" menu (customers, products, quotations); **Purchase Bills** tab beside Sales Bills; favicon from the logo mark; review pop-up that returns weekly until a review is given; local auto sign-in for testing | Navigation and growth |

---

## 2. Must-fix: compliance and money (Phase 1)

**Progress (11 Oct):** done — #1 credit-note numbering, #2 quotation conversion recompute, #3 set-off order,
#4 API GSTR-3B parity, #5 UTGST, #8 (16-character rule: typed numbers and *new* formats enforced, existing
formats warned on the settings page), #10 customer snapshot, #13 cancel rules, #14 refund due, #15 GST 2.0
quick picks, #16 API guards, Rule 46(p) "Reverse charge: No" on the PDF, P&L/dashboard income net of credit
notes and blocked-credit GST counted as cost, GSTR-3B due-date month overflow. Open — #6 (needs your
decision), #7, #9 (address/rate columns/signature wording), #11, #12 (after Purchase Bills), #17.

Ordered by risk to a real business. "Existing" = effect on existing customers.

| # | Amendment | Rule / evidence | Existing | Effort |
|---|---|---|---|---|
| 1 | **Credit-note numbering across financial years** — counter resets each FY but the number has no FY part, so the first credit note of a new FY hits the unique index and crashes | Reproduced in a probe. Add the same skip-forward loop invoices use; offer an `{FY}` format for new companies | None (old numbers kept) | S |
| 2 | **Quotation → invoice recomputes tax at conversion** (intra/inter-state from the customer's *current* state and place of supply) | Converted invoices can carry IGST with a CGST/SGST place of supply | None | S |
| 3 | **GSTR-3B set-off order** — IGST credit must be used against IGST first, then CGST/SGST; CGST ↔ SGST never. Today each head is netted alone with `max(0, …)` | s.49(5), Rule 88A, Circular 98/17/2019 | Report only | S |
| 4 | **API GSTR-3B parity** — API version counts cash-memo GST twice, ignores blocked credit (`itc_eligible`), ignores credit notes | Mirrors the web guards | Report only | S |
| 5 | **UTGST** — intra-UT supplies in Chandigarh (04), Ladakh (38), Lakshadweep (31), A&N (35), DNH&DD (26) must print UTGST, not SGST; seeder also wrongly flags Delhi, J&K, Puducherry as UTs | UTGST Act s.2(8) | Label only on new PDFs; data fix for the 3 wrong flags | M |
| 6 | **Sellers who cannot collect GST** — a company with no GSTIN, or a composition dealer, still gets 18% on every line. Must default lines to 0%, hide the GST column, title the document "Bill of Supply" with the Rule 5(1)(f) wording, and never collect tax | s.32(1), s.10(4), Rules 49, 5(1)(f) | **Decision needed** (see §6): default for new invoices + warning, not a block on old drafts | M |
| 7 | **Place of supply** — always the customer's billing state; ship-to is stored but ignored, no override. Bill-to/ship-to for goods is s.10(1)(b) (bill-to party's state); services on immovable property follow the property | IGST s.10, s.12(3) | Only new invoices; default stays "customer state" | M |
| 8 | **Invoice number ≤ 16 characters**, only letters, digits, `-` and `/` | Rule 46(b). "SHARMA/2026-27/0001" is 19 characters today | Warn existing formats in Settings; enforce on *new* formats and typed numbers only | S |
| 9 | **Rule 46 completeness on the PDF** — recipient address required for B2B and for B2C ≥ ₹50k (quick-add customers have no address); CGST and SGST rate shown separately per line (Rule 46(l)); signature line wording ("computer-generated, no signature required" is only true for e-invoices) | Rule 46 (d)(e)(l)(q) | New PDFs only | M |
| 10 | **Customer snapshot on issue** — name, GSTIN, address and state frozen on the invoice when issued; editing the customer later must not rewrite history or flip April's B2C invoices to B2B | GSTR-1 integrity | Additive columns; back-fill from current customer | M |
| 11 | **GSTR-1 export fit for the portal** — B2B (4A/4B), B2CL (inter-state B2C > ₹1,00,000 — Nfn 12/2024), B2CS (7), CDNR/CDNUR (9B), exports (6A), nil/exempt (8), HSN summary split B2B/B2C with UQC and rate (Table 12 Phase III, May 2025), documents issued (13); Excel in the offline-tool layout, and JSON | Current CSV has only B2B/B2C and no notes | Report only | L |
| 12 | **GSTR-3B 3.1(d)** must be *inward* RCM purchases (needs the Purchase Bills module); 3.1(b) zero-rated and 3.1(c) nil/exempt from the invoices; stop subtracting credit notes against RCM invoices from 3.1(a) | GSTR-3B tables | Report only | M (after Purchase Bills) |
| 13 | **Cancelling an issued invoice** — block when paid, credited or already in a filed period; direct to a credit note | s.34 practice; CA expectation | Only blocks *future* cancels | S |
| 14 | **Credit note on a fully paid invoice** must show a refund due, not clamp the balance to 0 | Money owed to the customer disappears today | Additive | S |
| 15 | **GST 2.0 rate presets** — quick picks 0 / 5 / 18 / 40; 12% and 28% under "Other" with a warning for invoice dates after 22 Sep 2025 (keep 12% for bricks; keep both for backdated invoices) | Nfn 09/2025-CTR; 28% ended 1 Feb 2026 | Old products keep their rate; warning only | S |
| 16 | **Guards missing in the API** — issue without company state; payment cap read before the row lock; `recordPayment` has no books-lock check | Parity with web | None | S |
| 17 | **HSN validation** — digits only; 4 digits for AATO ≤ ₹5 cr, 6 above (Nfn 78/2020); SAC starts with 99 | Currently `max:10`, accepts "ABC" | Warn on new lines only | S |

Effort: S = hours, M = a day, L = several days.

---

## 3. Features that put Apna Invoice ahead (Phase 2)

What the top tools have and users cite most, in the order that gives the most value per day of work:

1. **Purchase Bills + vendors + payables** (built 11 Oct, v1). Supplier bills with line items, HSN, GST, ITC
   eligibility, reverse charge, due date and vendor payments. Feeds P&L, GSTR-3B 4(A)(5) and 3.1(d), and
   the aging report gains a payables side. Every competitor above Khatabook has this.
2. **GSTIN autofill** on customer/vendor forms (public GSTIN lookup): legal name, address, state and
   registration status pre-filled, shown as a diff to confirm. myBillBook and Refrens' most-praised flow.
3. **Portal-ready GSTR-1** (Excel in the offline-tool layout + JSON) and **"Email to my CA"** in one click.
   Keeps the CA on the user's side; the single biggest driver of retention in the reviews.
4. **Export / SEZ invoices** with LUT: zero-rated path, the Rule 46 endorsement text, currency and
   shipping-bill fields; **state codes 96/97**.
5. **Documents the law expects**: receipt voucher for advances (Rule 50), refund voucher, payment voucher
   for RCM (Rule 52), delivery challan (Rule 55), debit note (Rule 53), proforma. Each is a thin variant of
   the invoice form and PDF.
6. **Party ledger and statement share**: outstanding as of date per party, a statement PDF/WhatsApp link,
   reminder cadence per party with a Hindi/English template carrying the UPI link (the app already prints
   a UPI QR).
7. **Recurring invoices** and a **financial-year close wizard** (new prefix suggestion "26-27/", live
   number preview, no gaps) — Vyapar's and Tally's most-used April feature.
8. **E-invoice (IRN + signed QR)** through a GSP for users above ₹5 cr AATO, with inline error
   explanations (Swipe's wedge); **e-way bill** Part A/B from the invoice.
9. **Tally XML export / CA seat** (read-only login that can download returns and the audit trail).
10. **Hindi / Hinglish UI** and invoice labels; regional languages later.
11. **Inventory basics** (stock on hand from purchase bills minus sales, low-stock flag) — optional; pure
    service users never see it.

Deliberately *not* recommended now: full accounting (journals, bank feeds), POS hardware, multi-godown
stock — these are where Tally/Busy/Marg live and where their users complain most.

---

## 4. UI/UX amendments for Indian users

Concrete, screen by screen. Patterns are borrowed from what reviewers praise and what the audits found.

**Invoice form**
- Keep the one-screen flow; add a **"Received" block** next to Paid amount (mode, date, reference), so the
  advance is a proper receipt from the start, and stop the balance going negative on a draft.
- **Tax mode banner** already states Inter-state/Intra-state; add a **place-of-supply override** with the
  state name and code, and a bill-to/ship-to toggle for goods.
- GST% quick picks **0 / 5 / 18 / 40**, "Other" for the rest; rate warning when 12%/28% is used on a
  post-22-Sep-2025 date.
- Per-line **CGST | SGST** (or IGST) amounts shown as the user types (currently only the totals change).
- Line-level **profit hint** (cost from the product record) that only owners see — Vyapar's most-liked toggle.
- Keyboard flow is already good (F2, Ctrl+S, F9); make **Enter in the last cell add a row**.

**Parties (customers and vendors)**
- One **Parties** list with Customer/Vendor tags, GSTIN autofill, state auto-set from the GSTIN, address
  required when a GSTIN is present (Rule 46(d)) and a **dues-as-of-today** column with a WhatsApp reminder
  action on the row.

**Dashboard**
- Replace generic tiles with what an Indian owner checks daily: **cash in / cash out today**, **who owes me**
  (top 5 parties), **GST payable this month** (3B estimate net of ITC), and a **compliance calendar**
  (GSTR-1 by the 11th, IMS actions before the 14th, GSTR-3B by the 20th, credit-note cut-off 30 Nov).

**Navigation**
- Standard Indian IA: **Sales · Purchases · Parties · Items · Reports · GST**. The new Add New menu and
  the Sales/Purchase strip are the first step; "Your Company" and "P&L" fold into Settings and Reports.

**Documents and PDF**
- Title switches automatically: Tax Invoice / Bill of Supply / Invoice-cum-Bill of Supply (Rule 46A) /
  Export Invoice with the LUT endorsement. UTGST label where it applies. CGST and SGST rate columns.
  "Original for Recipient / Duplicate for Transporter / Triplicate for Supplier" footers (done).
- Thermal 80 mm receipt exists; add a 58 mm variant for small kirana printers.

**Mobile**
- The forms already have card layouts; add a **bottom action bar** (Save draft · Issue · Share) that
  stays visible, and make the product/customer search full-screen on phones.

**Language**
- Hindi toggle for the app UI first (labels and buttons only), then Hinglish invoice phrases
  ("Dhanyavaad", amount in words in Hindi as an option).

---

## 5. Recommended order

- **Now → this week: Phase 1 items 1–8, 13–17** (all S/M, no impact on existing data), continuing the
  flow-by-flow QA walkthrough as each lands.
- **Next: Purchase Bills** (Phase 2 #1), then Phase 1 #12 which depends on it, then #9–11.
- **Then: GSTIN autofill, portal-ready GSTR-1 + CA email, export/SEZ, vouchers, party ledger, recurring +
  FY close, UX batch (invoice form, dashboard, navigation).**
- **Later: e-invoice/e-way via GSP, Tally export, Hindi UI, inventory.**

Unless told otherwise, this is the order I will follow.

---

## 6. Decisions needed from you

1. **Sellers without a GSTIN / composition dealers** — should the invoice form (a) default every line to
   0% and hide GST with a one-line notice (recommended), or (b) hard-block GST rates for such companies?
   (a) protects existing drafts; (b) is stricter.
2. **Invoice number length** — warn existing companies whose format exceeds 16 characters and let them
   change it when they are ready (recommended), or force a new format on their next invoice?
3. **12% / 28% slabs** — keep selectable with a date-aware warning (recommended), or remove from the
   picker for invoice dates after 22 Sep 2025?
4. ~~**Review pop-up**~~ - decided 11 Oct: shown to new and existing users on the first dashboard after registration and after every second invoice issued, until the review is given; then never again.
5. **Navigation rename** to Sales · Purchases · Parties · Items · Reports · GST — now, or after the
   Purchase Bills module lands?
