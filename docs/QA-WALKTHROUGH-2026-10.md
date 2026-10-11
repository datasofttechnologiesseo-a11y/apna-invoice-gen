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
- ⏳ Create: intra-state CGST+SGST vs inter-state IGST; place of supply
- ⏳ Line maths: discount, taxable, CGST/SGST split, Amount incl. GST, round-off
- ⏳ Draft → finalize: number (Rule 46(b) ≤16 chars), FY series, lock
- ⏳ PDF: Rule 46 fields, Rule 48 copy labels, amount in words
- ⏳ Share: WhatsApp / email / public link
- ⏳ Payments: partial, advance, TDS, overpay guard, status transitions
- ⏳ Cancel rules

## 4. Credit notes
- ⏳ Create against invoice, cap, s.34(2) date limit, numbering across FY

## 5. Quotations
- ⏳ Create → send → accept → convert to invoice (taxes recomputed, line totals)

## 6. Purchases
- ⏳ Expense entry (ITC eligible, inter-state)
- ⏳ Cash memo → linked expense → P&L
- ⏳ Purchase Bills section (to be built)

## 7. GST returns
- ⏳ GSTR-1 export: B2B / B2CL (₹1 lakh) / B2CS / CDNR / HSN
- ⏳ GSTR-3B: 3.1(a) net of CN, 3.1(d) inward RCM, ITC, set-off order

## 8. Reports
- ⏳ Dashboard figures, P&L, aging, exports

## 9. Company & settings
- ⏳ Company GSTIN/state, composition, numbering format, books lock, bank/UPI
- ⏳ Multi-company switch isolation
- ⏳ Profile, backups, notifications

## Findings log
- 🔧 **CGST/SGST split on odd paise** — tax was rounded as a whole and halved, so ₹100.30 @ 18% gave CGST 9.03 / SGST 9.02 and the invoice totals disagreed by a paisa (GSTR-1 rejects this). Each head is now computed at half the rate and rounded on its own, as the NIC e-invoice schema does (`InvoiceCalculator`, invoice and quotation forms). New/draft documents only.
- 🔧 **Rule 48 copy labels were swapped** — goods invoices printed "Duplicate for Supplier / Triplicate for Transporter"; the rule is Duplicate for *Transporter*, Triplicate for *Supplier*. Fixed in the PDF labels and the Download PDF chooser; services-only invoices no longer offer a transporter copy (Rule 48(2)).
- 🔧 **Rule 46(e) threshold** — the "B2C invoice needs recipient name/address/state" warning fired at ₹2.5 lakh (the old B2CL limit); the rule is taxable value ≥ ₹50,000. Fixed.
- 🔧 **GSTR-1 export labelled reverse-charge invoices "Bill of Supply"** because their tax is ₹0; they are tax invoices. Fixed.
