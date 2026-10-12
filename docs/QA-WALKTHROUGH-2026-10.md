# Flow-by-flow QA walkthrough — October 2026

Hands-on check of every option in the app on the local build, against Indian GST
rules (CGST Act/Rules, IGST Act, CBIC notifications as verified on 10 Oct 2026)
and against what the best Indian invoicing tools do. Each flow is driven in the
browser as a real user would; logic is cross-checked in code and tests.

Legend: ✅ correct · 🔧 fixed during this pass · ⚠️ decision needed · ⏳ not yet checked

Rule applied throughout: **existing customers are never impacted** — fixes apply
to new/draft documents only; issued documents and historical rows are untouched.

## 1. Customers
- ✅ Add customer (Add New → Add Customer): GSTIN format + checksum + state match — a Karnataka GSTIN with Maharashtra selected is refused with a clear message, shown at the top and inline
- ✅ Saved customer lists name, GSTIN, state, email, phone; Ledger / Edit / Delete actions present
- ⏳ Quick-add from invoice form
- ⏳ Edit / delete; what happens to past invoices (snapshot)

## 2. Products / Services
- ✅ Add product with HSN/SAC, unit, rate, GST% — goods/service kind switches HSN vs SAC hint; 12% and 28% already marked "(legacy)", 40% present (GST 2.0)
- ⚠️ "1% · Composition scheme" is offered as a *product GST rate*; a composition dealer may not charge tax on an invoice at all (s.10(4)). Belongs with the composition/unregistered-seller decision (see findings)
- ⏳ Product picked on invoice auto-fills

## 3. Sales invoice
- ✅ Create: picking a Karnataka customer from a Maharashtra company switches the tax mode to "Inter-state (IGST)" automatically; product pick fills HSN, unit, rate and GST%
- ⚠️ Place of supply cannot be overridden (bill-to/ship-to, s.10(1)(b)); always the customer's billing state — roadmap §2 #7
- ⚠️ "Paid amount" on a draft accepts more than the total and shows a negative balance; it also has no payment mode/date — roadmap §4 (Received block)
- ✅ Line maths on the saved draft: taxable ₹49,576.27 · IGST ₹8,923.73 · Amount ₹58,500.00; subtotal/IGST/grand total agree; amount in words in lakh/crore style; advance ₹10,000 → balance ₹48,500
- ✅ Draft page shows supplier + buyer GSTIN and state codes, place of supply with code, preview of the next number (INV-0001), Issue / Edit / PDF / Print / Thermal / Duplicate / Delete actions
- ⚠️ Minor: text typed into the product search before a match ("Intel") was kept as the line Description after the product was picked; picking a product should clear a description that only echoes the search text
- ✅ Draft → issue: assigned INV-0001, status Partially paid; the ₹10,000 advance was booked as receipt RCPT-0001 at issue (the fix); line items locked; first-invoice celebration + "What's next" (WhatsApp / Record receipt / PDF)
- ✅ Cancel is hidden once a receipt exists (credit note offered instead)
- ⚠️ Rule 46(b): no check yet that custom numbers / formats stay ≤ 16 characters — roadmap §2 #8
- ✅ PDF (INV-0001): supplier/buyer GSTIN + state codes, place of supply with code, HSN, qty + UQC, rate, taxable value, rate + tax per line, amount in words, "ORIGINAL FOR RECIPIENT" (copies now Original/Recipient · Duplicate/Transporter · Triplicate/Supplier)
- 🔧 Rule 46(p): the PDF printed nothing about reverse charge on normal invoices; it now states "Reverse charge: No" (and "Yes" with the declaration on RCM invoices)
- ⚠️ Rule 46(q): footer says "does not require a physical signature" — true only for e-invoices; roadmap §2 #9
- ✅ Share: WhatsApp text carries invoice no., total, received, balance and due date with a signed public link (valid 30 days); the public page shows the amount due net of receipts and credit notes (₹23,500) with PDF download; email and copy-link present
- ✅ Payments: ₹20,000 by UPI with reference → receipt RCPT-0002, paid ₹30,000, balance ₹28,500, still Partially paid; receipts table with PDF/Reverse per row; amount field capped at the balance (overpay guard); TDS section available
- ⚠️ Minor: receipts dated today show "14 hours ago" in Activity because the receipt carries a date only (midnight); show the date instead of a relative time
- ⏳ Cancel rules

## 4. Credit notes
- ✅ Create against INV-0001: form shows invoice total / already credited / paid / max creditable, date must be ≥ invoice date, reason list (s.34 cases), tax pro-rated; CRN-0001 issued, balance ₹28,500 → ₹23,500, listed with PDF/Reverse
- ✅ Numbering across financial years fixed (skip-forward loop, test added)
- ⚠️ Activity entry reads "₹0.00 credited back to customer" for a ₹5,000 note — wrong figure in the log text (being fixed)

## 5. Quotations
- ⚠️ UX consistency: the quotation form is an older design than the invoice form — plain customer/product `<select>` lists instead of the search-as-you-type combobox with inline quick-add, GST% list without the "(legacy)" marks, line "Amount" pre-tax while the invoice shows GST-inclusive. Worth aligning in the UX pass (roadmap §4)
- ✅ Good Indian B2B fields: Subject, Customer reference (RFQ/enquiry no.), Delivery period, validity presets (7/15/30/60 days), "not a tax invoice" explainer
- ✅ Create: QT-0001 for 2 × Intel CPU → subtotal ₹99,152.54, IGST ₹17,847.46, grand total ₹1,17,000.00 (amount in words correct); tax mode Inter-state from the customer's state; validity 30 days; share options (email/WhatsApp/public link) assign the number on send
- ✅ Mark as sent: number QT-0001 assigned, status Sent, actions become Mark as accepted / Decline / Convert to invoice; WhatsApp text now uses Indian grouping (₹1,17,000.00 — was ₹117,000.00, fixed for invoices too)
- ✅ Convert to invoice (run through the real route): draft invoice #108 with IGST recomputed from the customer's state (₹17,847.46), place of supply set, line total ₹1,17,000.00 present (the earlier regression printed ₹0.00); quotation marked Converted and linked
- ⚠️ Accept/Decline not exercised in the browser (confirm dialogs need the pane visible); covered by tests

## 6. Purchases
- ✅ Expense entry: clear choice screen (Standard expense with ITC vs Cash memo voucher), category list, taxable + GST split, IGST toggle, §17(5) blocked-credit toggle; ₹10,000 + ₹1,800 IGST (UPI) saved → list shows taxable/GST/cash-out totals and category share; Sales/Purchase strip counts update
- ⚠️ No vendor master, no bill number/date, no due date/payables — this is the gap the Purchase Bills module fills
- ✅ Cash memo form: FY-based memo number with manual override, seller details + state (IGST hint from the company state), line items with HSN/UQC, discount, round-off, payment mode, expense category for the linked P&L entry
- 🔧 Cash-memo GST rate list was 0 / 5 / 12 / 18 / 28 — now the GST 2.0 slabs (0 / 5 / 18 / 40) with 12 / 28 marked legacy
- ✅ Verified in the browser: vendor quick-added inline from the bill form (GSTIN + state validated), bill DC/2026/0117 for 2 × ₹5,000 @ 18% → taxable ₹10,000, CGST ₹900 + SGST ₹900, payable ₹11,800; ₹5,000 NEFT payment → Partly paid, balance ₹6,800; list shows payable/overdue/due-in-7-days/ITC cards; Sales/Purchase strip counts update; GSTR-3B input credit includes the bill once
- 🔧 **Purchase Bills section built** (under Invoices → Purchase Bills, plus Vendors): vendor master with GSTIN/state validation and inline quick-add; bills with the vendor's number/date/due date, line items with HSN/qty/unit/rate/discount/GST%, tax type from the vendor's state (manual override), reverse charge (3.1(d)), blocked-credit toggle (s.17(5)), expense category, "paid already" shortcut, vendor payments with balance/status (unpaid / partly paid / paid / overdue), payables summary (owed, overdue, due in 7 days, input credit this month); every bill mirrors into the expense ledger so P&L and exports keep working; GSTR-3B now takes ITC from bills (no double count with the mirrored expense) and fills 3.1(d) from inward reverse-charge bills

## 7. GST returns
- ✅ GSTR-1 CSV (Oct 2026): INV-0001 as B2B with GSTIN, state, place of supply, reverse charge No, Tax Invoice, taxable/IGST/total; HSN summary row; B2B/B2C counts
- ⚠️ Credit note CRN-0001 is missing from the export (no CDNR/CDNUR section); no B2CL (₹1 lakh) / B2CS split; HSN table lacks UQC and rate, not split B2B/B2C — roadmap §2 #11
- ✅ GSTR-3B (Oct 2026) reconciles by hand: 3.1(a) taxable ₹45,338.98 = ₹49,576.27 − CN ₹4,237.29; IGST ₹8,161.02 = ₹8,923.73 − ₹762.71; ITC IGST ₹1,800 from the expense; net cash ₹6,361.02 after set-off; honest "what this is not" note; §17(5) exclusion explained
- ⚠️ 3.1(d) still shows outward RCM, not inward (needs Purchase Bills); (b)/(c)/(e) hard-coded zero — roadmap §2 #12
- 🔧 Filing window said "October 2026 … due by 20 Dec 2026" — month overflow from the 31st; the 3B for a month is due on the 20th of the next month (20 Nov)

## 8. Reports
- ✅ Aging (Party Outstanding): ₹23,500 current bucket, by customer with GSTIN, oldest-days, Ledger link, PDF/CSV/print
- ⚠️ P&L revenue ₹49,576 ignores the ₹4,237.29 (taxable) credit note issued in the month — revenue should be net of credit notes; expenses should add non-claimable GST (§17(5)) to cost (checking the code)
- ✅ Dashboard: issued/collected today, outstanding ₹23,500, "Where your money stands" ₹53,500 billed (net of the credit note) → 56% collected / 44% due, 30-day receipts chart, 6-month invoiced vs collected, CA exports (GSTR-1 this/last month, GSTR-3B, aging, expenses CSV, backup), recent invoices, review pop-up after the first issued bill
- 🔧 "This month's P&L" on the dashboard showed income ₹49,576 — not net of the credit note (same fix as the Finance P&L)
- ⚠️ "Try a sample" button still shown after real invoices exist — hide once the first invoice is issued (UX)

## 9. Company & settings
- ✅ Settings hub groups Account / Business / Help; "Your firms" lists GSTIN, city/state, counts and invoice prefix, with the active firm marked
- ✅ Company settings: GSTIN + PAN, composition toggle with the exact Rule 5(1)(f) wording, state drives place of supply, logo/signature, FY-reset numbering tokens ({FY} {FY_SHORT} {YYYY} {N}) with "continue a series from another tool", default terms + CBIC declaration, bank/UPI for the PDF, books lock date
- ⚠️ Rule 46(b): numbering format and typed numbers are not checked for ≤ 16 characters / allowed characters (being added as: warn on existing formats, enforce on new formats and typed numbers)
- ⚠️ Composition toggle changes the title/declaration but lines still carry GST% (decision §6 #1 in the roadmap)
- ⏳ Multi-company switch isolation
- ⏳ Profile, backups, notifications

## Findings log
- 🔧 **CGST/SGST split on odd paise** — tax was rounded as a whole and halved, so ₹100.30 @ 18% gave CGST 9.03 / SGST 9.02 and the invoice totals disagreed by a paisa (GSTR-1 rejects this). Each head is now computed at half the rate and rounded on its own, as the NIC e-invoice schema does (`InvoiceCalculator`, invoice and quotation forms). New/draft documents only.
- 🔧 **Rule 48 copy labels were swapped** — goods invoices printed "Duplicate for Supplier / Triplicate for Transporter"; the rule is Duplicate for *Transporter*, Triplicate for *Supplier*. Fixed in the PDF labels and the Download PDF chooser; services-only invoices no longer offer a transporter copy (Rule 48(2)).
- 🔧 **Rule 46(e) threshold** — the "B2C invoice needs recipient name/address/state" warning fired at ₹2.5 lakh (the old B2CL limit); the rule is taxable value ≥ ₹50,000. Fixed.
- 🔧 **GSTR-1 export labelled reverse-charge invoices "Bill of Supply"** because their tax is ₹0; they are tax invoices. Fixed.
- 🔧 **Advance on a draft was wiped by the first payment** — issuing now books the draft's "Paid amount" as a receipt; a migration adds the missing receipt for already-issued invoices.
- 🔧 **Credit-note numbers collided across financial years** (counter reset, no FY in the number → unique-index 500). The number now skips past any already issued.
- 🔧 **Quotation → invoice copied the quote's tax split** — taxes are now recomputed at conversion from the customer's state as it stands today (web and API).
- 🔧 **GSTR-3B set-off** now follows s.49(5)/Rule 88A (IGST credit first, CGST/SGST own head then IGST, never across) via `GstSetOff`; the API GSTR-3B gained the web's ITC guards (no cash-memo double count, blocked credit excluded, credit notes netted).
- 🔧 **Cancelling an issued invoice** is blocked once a receipt or credit note exists against it — the correction is a credit note (s.34).
- 🔧 **UTGST** — Chandigarh, DNH & DD, Lakshadweep, A&N and Ladakh now print UTGST on invoices, quotations, credit notes and the thermal receipt; Delhi, J&K and Puducherry were wrongly flagged as UTs (seeder + data fix).
- 🔧 **GST 2.0 quick-pick rates** on the invoice form are 0 / 5 / 18 / 40; 12% and 28% remain under "Other".
- 🔧 **Credit note on a paid invoice** no longer clamps the balance to zero — the negative balance is shown as "Refund due to customer", so the money owed back is visible.
- 🔧 **API parity guards** — issuing through the API now requires the business state (as the web does); a receipt dated inside a locked period is refused (the lock is on the receipt date, so money received today against an old invoice still records).
