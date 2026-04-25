// Build "Getting Started with Apna Invoice" PowerPoint deck.
// Run: node build-deck.js  →  apna-invoice-getting-started.pptx
const pptxgen = require("pptxgenjs");

const NAVY = "1E3A8A";
const NAVY_DEEP = "0F1F4F";
const SAFFRON = "FF9933";
const GREEN = "138808";
const TEXT = "111827";
const MUTED = "6B7280";
const BG_SOFT = "F8FAFC";
const RING = "E5E7EB";
const BRAND_50 = "EEF3FC";
const SAFFRON_50 = "FFF7ED";
const GREEN_50 = "ECFDF5";

const HEAD_FONT = "Calibri";
const BODY_FONT = "Calibri";

const pres = new pptxgen();
pres.layout = "LAYOUT_16x9"; // 10 × 5.625
pres.author = "Datasoft Technologies";
pres.title = "Getting Started with Apna Invoice";

// ── Helpers ────────────────────────────────────────────────────────
function addFooter(slide, n, total) {
  // Subtle bottom strip
  slide.addShape(pres.shapes.RECTANGLE, {
    x: 0, y: 5.42, w: 10, h: 0.005, fill: { color: RING }, line: { color: RING },
  });
  slide.addText("Datasoft Technologies · apnainvoice.com", {
    x: 0.45, y: 5.45, w: 6, h: 0.3,
    fontFace: BODY_FONT, fontSize: 9, color: MUTED, align: "left", margin: 0,
  });
  slide.addText(`${n} / ${total}`, {
    x: 8.6, y: 5.45, w: 1, h: 0.3,
    fontFace: BODY_FONT, fontSize: 9, color: MUTED, align: "right", margin: 0,
  });
}

function addStepBadge(slide, stepNo, color = NAVY) {
  // Circle with step number
  slide.addShape(pres.shapes.OVAL, {
    x: 0.45, y: 0.45, w: 0.65, h: 0.65,
    fill: { color }, line: { color },
  });
  slide.addText(String(stepNo), {
    x: 0.45, y: 0.45, w: 0.65, h: 0.65,
    fontFace: HEAD_FONT, fontSize: 22, bold: true, color: "FFFFFF",
    align: "center", valign: "middle", margin: 0,
  });
}

function addStepHeader(slide, eyebrow, title) {
  slide.addText(eyebrow, {
    x: 1.25, y: 0.42, w: 7, h: 0.35,
    fontFace: BODY_FONT, fontSize: 11, bold: true, color: SAFFRON,
    charSpacing: 4, margin: 0,
  });
  slide.addText(title, {
    x: 1.25, y: 0.7, w: 8.3, h: 0.55,
    fontFace: HEAD_FONT, fontSize: 28, bold: true, color: NAVY, margin: 0,
  });
}

const TOTAL = 17;
let n = 0;

// ── 1. TITLE ────────────────────────────────────────────────────────
{
  const slide = pres.addSlide();
  n = 1;
  slide.background = { color: NAVY_DEEP };

  // Tricolour accent bar at top
  slide.addShape(pres.shapes.RECTANGLE, { x: 0, y: 0, w: 3.33, h: 0.18, fill: { color: SAFFRON }, line: { color: SAFFRON } });
  slide.addShape(pres.shapes.RECTANGLE, { x: 3.33, y: 0, w: 3.34, h: 0.18, fill: { color: "FFFFFF" }, line: { color: "FFFFFF" } });
  slide.addShape(pres.shapes.RECTANGLE, { x: 6.67, y: 0, w: 3.33, h: 0.18, fill: { color: GREEN }, line: { color: GREEN } });

  slide.addText("APNA INVOICE", {
    x: 0.6, y: 1.0, w: 9, h: 0.45,
    fontFace: BODY_FONT, fontSize: 14, bold: true, color: SAFFRON, charSpacing: 8, margin: 0,
  });

  slide.addText("Welcome to Apna Invoice", {
    x: 0.6, y: 1.5, w: 9, h: 1.0,
    fontFace: HEAD_FONT, fontSize: 48, bold: true, color: "FFFFFF", margin: 0,
  });

  slide.addText("Your first GST invoice in 60 seconds", {
    x: 0.6, y: 2.55, w: 9, h: 0.55,
    fontFace: HEAD_FONT, fontSize: 24, italic: true, color: "CADCFC", margin: 0,
  });

  slide.addText("A step-by-step guide for new users — built for India's MSMEs, SMEs, startups, freelancers and CAs.", {
    x: 0.6, y: 3.25, w: 8.5, h: 0.6,
    fontFace: BODY_FONT, fontSize: 14, color: "B8C8E9", margin: 0,
  });

  // Tricolour accent bar at bottom
  slide.addShape(pres.shapes.RECTANGLE, { x: 0, y: 5.45, w: 3.33, h: 0.18, fill: { color: SAFFRON }, line: { color: SAFFRON } });
  slide.addShape(pres.shapes.RECTANGLE, { x: 3.33, y: 5.45, w: 3.34, h: 0.18, fill: { color: "FFFFFF" }, line: { color: "FFFFFF" } });
  slide.addShape(pres.shapes.RECTANGLE, { x: 6.67, y: 5.45, w: 3.33, h: 0.18, fill: { color: GREEN }, line: { color: GREEN } });

  slide.addText("Datasoft Technologies · apnainvoice.com", {
    x: 0.6, y: 5.05, w: 9, h: 0.3,
    fontFace: BODY_FONT, fontSize: 11, color: "8FA3C9", margin: 0,
  });
}

// ── 2. AGENDA ───────────────────────────────────────────────────────
{
  const slide = pres.addSlide();
  n = 2;
  slide.background = { color: BG_SOFT };

  slide.addText("WHAT YOU'LL LEARN", {
    x: 0.45, y: 0.45, w: 9, h: 0.35,
    fontFace: BODY_FONT, fontSize: 11, bold: true, color: SAFFRON, charSpacing: 4, margin: 0,
  });
  slide.addText("12 steps from sign-up to your first paid invoice", {
    x: 0.45, y: 0.78, w: 9.1, h: 0.5,
    fontFace: HEAD_FONT, fontSize: 26, bold: true, color: NAVY, margin: 0,
  });

  const items = [
    "1. Sign up free", "2. Add your business", "3. Add your first customer",
    "4. Tour the dashboard", "5. Create an invoice", "6. Auto tax mode",
    "7. Review & finalize", "8. Share with customer", "9. Record payments",
    "10. Issue credit notes", "11. Track on dashboard", "12. Backups & data export",
  ];

  const cols = 3, rows = 4;
  const cardW = 2.95, cardH = 0.75;
  const startX = 0.45, startY = 1.55, gapX = 0.15, gapY = 0.18;

  items.forEach((label, i) => {
    const row = Math.floor(i / cols);
    const col = i % cols;
    const x = startX + col * (cardW + gapX);
    const y = startY + row * (cardH + gapY);
    slide.addShape(pres.shapes.RECTANGLE, {
      x, y, w: cardW, h: cardH,
      fill: { color: "FFFFFF" }, line: { color: RING, width: 1 },
    });
    // Saffron accent strip on the left
    slide.addShape(pres.shapes.RECTANGLE, {
      x, y, w: 0.06, h: cardH, fill: { color: SAFFRON }, line: { color: SAFFRON },
    });
    slide.addText(label, {
      x: x + 0.2, y, w: cardW - 0.25, h: cardH,
      fontFace: HEAD_FONT, fontSize: 14, bold: true, color: TEXT, align: "left", valign: "middle", margin: 0,
    });
  });

  addFooter(slide, n, TOTAL);
}

// ── helper for content slides ───────────────────────────────────────
function bulletSlide(stepNo, eyebrow, title, paragraph, bullets, footerLine) {
  const slide = pres.addSlide();
  slide.background = { color: "FFFFFF" };
  addStepBadge(slide, stepNo);
  addStepHeader(slide, eyebrow, title);

  // Left: paragraph + bullets
  slide.addText(paragraph, {
    x: 1.25, y: 1.5, w: 5.4, h: 0.85,
    fontFace: BODY_FONT, fontSize: 14, color: TEXT, margin: 0,
  });

  const bulletText = bullets.map((b, i) => ({
    text: b,
    options: { bullet: { code: "25CF" }, color: TEXT, fontSize: 14, paraSpaceAfter: 6, breakLine: i < bullets.length - 1 },
  }));
  slide.addText(bulletText, {
    x: 1.25, y: 2.45, w: 5.4, h: 2.6,
    fontFace: BODY_FONT, margin: 0,
  });

  // Right: visual highlight card
  slide.addShape(pres.shapes.RECTANGLE, {
    x: 6.95, y: 1.5, w: 2.6, h: 3.55,
    fill: { color: BRAND_50 }, line: { color: RING, width: 1 },
  });
  // Step indicator inside card
  slide.addText(`STEP ${stepNo}`, {
    x: 6.95, y: 1.65, w: 2.6, h: 0.35,
    fontFace: BODY_FONT, fontSize: 10, bold: true, color: NAVY, charSpacing: 5, align: "center", margin: 0,
  });
  slide.addText(footerLine, {
    x: 7.05, y: 2.05, w: 2.4, h: 2.85,
    fontFace: HEAD_FONT, fontSize: 16, bold: true, color: NAVY, italic: true,
    align: "center", valign: "middle", margin: 0,
  });

  return slide;
}

// ── 3. SIGN UP ──────────────────────────────────────────────────────
{
  n = 3;
  const s = bulletSlide(
    1, "STEP 1 OF 12", "Sign up — free, no card",
    "Head to apnainvoice.com and click 'Start free'. Sign-up takes about 30 seconds and never asks for a credit card.",
    [
      "Enter your name, email and a strong password",
      "Tick 'I agree to Terms & Privacy' (required by DPDP Act)",
      "Optionally opt in to occasional product tips",
      "We log every consent with timestamp + IP for your audit trail",
    ],
    "No credit card.\nNo feature locks.\nFree for India's MSMEs."
  );
  addFooter(s, n, TOTAL);
}

// ── 4. BUSINESS ─────────────────────────────────────────────────────
{
  n = 4;
  const s = bulletSlide(
    2, "STEP 2 OF 12", "Add your business",
    "After sign-up, the onboarding wizard collects your business details once — every invoice after that is auto-filled.",
    [
      "Business name as per GST registration",
      "GSTIN (15 chars) and PAN — both validated",
      "Address, city, PIN and state (drives CGST/SGST vs IGST)",
      "Invoice prefix (e.g. INV) — supports {FY} for auto-reset",
      "Logo upload, default terms, and bank/UPI details",
    ],
    "GSTIN format and PAN are validated server-side before save."
  );
  addFooter(s, n, TOTAL);
}

// ── 5. CUSTOMER ─────────────────────────────────────────────────────
{
  n = 5;
  const s = bulletSlide(
    3, "STEP 3 OF 12", "Add your first customer",
    "Save customer details once and reuse them on every invoice. The state field tells the system how to apply GST.",
    [
      "Customer name (required)",
      "GSTIN — optional, only for B2B customers",
      "Email and phone for sharing invoices",
      "Address + state — state is mandatory for tax mode",
      "Country defaults to India (INR-only product)",
    ],
    "Tip: keep GSTIN blank for retail B2C customers."
  );
  addFooter(s, n, TOTAL);
}

// ── 6. DASHBOARD ────────────────────────────────────────────────────
{
  n = 6;
  const s = bulletSlide(
    4, "STEP 4 OF 12", "Tour the dashboard",
    "Your home base. The dashboard shows the two numbers that actually matter for an Indian MSME: bills issued and money received.",
    [
      "Bills issued (lifetime + this month + drafts pending)",
      "Payments received (lifetime + this month + receipts issued)",
      "Setup checklist with progress %",
      "Outstanding amount to collect",
      "Multi-company switcher in the top-right",
    ],
    "One glance.\n\nIs the month on track?"
  );
  addFooter(s, n, TOTAL);
}

// ── 7. CREATE INVOICE ───────────────────────────────────────────────
{
  n = 7;
  const s = bulletSlide(
    5, "STEP 5 OF 12", "Create your first invoice",
    "Click 'New invoice'. The form is built so you can issue a clean GST invoice in under a minute.",
    [
      "Pick a customer (or click + New to add one)",
      "Invoice date defaults to today; due date is optional",
      "Add line items — description, HSN/SAC, qty, rate, GST%",
      "Goods use HSN (4–8 digits); services use SAC (starts with 99)",
      "Defaults: quantity 1, GST 18% — change as needed",
    ],
    "First-time user tip:\nfill description, HSN, qty, rate.\nThat's it."
  );
  addFooter(s, n, TOTAL);
}

// ── 8. AUTO TAX MODE ────────────────────────────────────────────────
{
  n = 8;
  const s = bulletSlide(
    6, "STEP 6 OF 12", "Auto tax mode — CGST/SGST or IGST",
    "When you pick a customer, Apna Invoice instantly compares states and applies the right tax components. No more manual maths.",
    [
      "Customer state = your state → CGST + SGST split (intra-state)",
      "Customer state ≠ your state → single IGST line (inter-state)",
      "Calculation: line total × GST% (e.g. 18% → 9% + 9% intra-state)",
      "Grand total auto-rounded; round-off captured separately",
      "Reverse charge tick-box for Section 9(3)/9(4) supplies",
    ],
    "Place of supply,\nplace of deduction —\nhandled."
  );
  addFooter(s, n, TOTAL);
}

// ── 9. FINALIZE ─────────────────────────────────────────────────────
{
  n = 9;
  const s = bulletSlide(
    7, "STEP 7 OF 12", "Review & finalize",
    "Drafts are editable; final invoices are legally locked. When you click Finalize, the invoice gets a permanent number and the PDF is generated.",
    [
      "Sequential invoice number — INV-0001, INV-0002…",
      "Auto-resets every 1 April with the {FY} format",
      "Amount in words: Lakhs and Crores (Indian number system)",
      "PDF generated server-side, A4-sized, ink-saver by default",
      "Edits to amounts/items are blocked after finalize",
    ],
    "Numbering complies with\nCGST Rule 46(b)."
  );
  addFooter(s, n, TOTAL);
}

// ── 10. SHARE ───────────────────────────────────────────────────────
{
  n = 10;
  const s = bulletSlide(
    8, "STEP 8 OF 12", "Share with your customer",
    "Once finalized, share the invoice the way Indian businesses actually share — WhatsApp first, email second.",
    [
      "WhatsApp — pre-filled message with invoice number + amount",
      "Email — attaches the PDF automatically",
      "Public link — secure 30-day signed URL for the customer",
      "Download PDF — ink-saver mono or full-colour",
      "UPI QR auto-attached if you've added a UPI ID",
    ],
    "WhatsApp + UPI QR.\n\nGet paid faster."
  );
  addFooter(s, n, TOTAL);
}

// ── 11. PAYMENTS ────────────────────────────────────────────────────
{
  n = 11;
  const s = bulletSlide(
    9, "STEP 9 OF 12", "Record payments & issue receipts",
    "When the customer pays, log it. Apna Invoice auto-generates a sequential receipt number and updates the invoice balance.",
    [
      "Methods: UPI, NEFT, RTGS, IMPS, Cheque, Card, Cash, Other",
      "Reference field for transaction ID / cheque number",
      "Receipt number auto-assigned (RCPT-0001…)",
      "Balance recalculated; status moves to 'partially paid' or 'paid'",
      "Printable receipt PDF, customer-shareable",
    ],
    "Part-payments and\nover-payments\nhandled cleanly."
  );
  addFooter(s, n, TOTAL);
}

// ── 12. CREDIT NOTES ────────────────────────────────────────────────
{
  n = 12;
  const s = bulletSlide(
    10, "STEP 10 OF 12", "Issue credit notes — Section 34 CGST",
    "Goods returned, rate corrected, or post-sale discount agreed? Issue a credit note to adjust the invoice for GSTR-1.",
    [
      "Choose date, amount and reason code",
      "CGST/SGST/IGST automatically pro-rated by amount ratio",
      "Sequential credit-note number (CRN-0001…)",
      "Invoice balance + status auto-recomputed",
      "Credit-note PDF, GSTR-1-ready",
    ],
    "Compliant with\nSection 34 of\nthe CGST Act."
  );
  addFooter(s, n, TOTAL);
}

// ── 13. TRACKING ────────────────────────────────────────────────────
{
  n = 13;
  const s = bulletSlide(
    11, "STEP 11 OF 12", "Track everything on the dashboard",
    "Run the month. The dashboard plus the Finance section give you the whole picture without spreadsheets.",
    [
      "Bills issued / received this month — at a glance",
      "Outstanding (receivables) drill-down by customer",
      "P&L: income (accrual) minus expenses logged in Finance",
      "Drafts pending — click to finish them",
      "Filter invoices by status, date range or customer",
    ],
    "Close the month\nin minutes,\nnot days."
  );
  addFooter(s, n, TOTAL);
}

// ── 14. BACKUPS ─────────────────────────────────────────────────────
{
  n = 14;
  const s = bulletSlide(
    12, "STEP 12 OF 12", "Backups & data export",
    "Your data is yours. Apna Invoice lets you download or email a complete ZIP at any time — under your DPDP Act rights.",
    [
      "On-demand ZIP — invoices, customers, products, payments, expenses (CSV)",
      "Optional weekly auto-backup, emailed every Sunday",
      "Account deletion is a single click in Profile (DPDP §11)",
      "Servers in India — your data never leaves the country",
      "Consent log viewable on request",
    ],
    "Your data.\nYour rights.\nYour rules."
  );
  addFooter(s, n, TOTAL);
}

// ── 15. POWER TIPS ──────────────────────────────────────────────────
{
  const slide = pres.addSlide();
  n = 15;
  slide.background = { color: BG_SOFT };

  slide.addText("LEVEL UP", {
    x: 0.45, y: 0.45, w: 9, h: 0.35,
    fontFace: BODY_FONT, fontSize: 11, bold: true, color: SAFFRON, charSpacing: 4, margin: 0,
  });
  slide.addText("Three tips for power users", {
    x: 0.45, y: 0.78, w: 9.1, h: 0.55,
    fontFace: HEAD_FONT, fontSize: 28, bold: true, color: NAVY, margin: 0,
  });

  const tips = [
    {
      title: "Save products",
      body: "Store frequently billed items once — name, HSN/SAC, unit, rate, GST%. Auto-fill on every new invoice line. Cuts a 60-second invoice down to 20.",
      accent: NAVY,
      bg: BRAND_50,
    },
    {
      title: "UPI QR everywhere",
      body: "Add your UPI ID under Company settings. Every invoice and receipt PDF gets a scan-and-pay QR code that works with any UPI app — PhonePe, GPay, Paytm.",
      accent: SAFFRON,
      bg: SAFFRON_50,
    },
    {
      title: "Multi-GSTIN support",
      body: "Run more than one business? Add each as a separate company with its own GSTIN, invoice series and customer book. Switch with the dropdown in the top-right.",
      accent: GREEN,
      bg: GREEN_50,
    },
  ];

  const cardW = 3.0, cardH = 3.4, gap = 0.25;
  const startX = (10 - (3 * cardW + 2 * gap)) / 2;
  const startY = 1.6;

  tips.forEach((t, i) => {
    const x = startX + i * (cardW + gap);
    slide.addShape(pres.shapes.RECTANGLE, {
      x, y: startY, w: cardW, h: cardH,
      fill: { color: "FFFFFF" }, line: { color: RING, width: 1 },
      shadow: { type: "outer", color: "000000", blur: 12, offset: 3, angle: 90, opacity: 0.06 },
    });
    // Top accent strip
    slide.addShape(pres.shapes.RECTANGLE, {
      x, y: startY, w: cardW, h: 0.12,
      fill: { color: t.accent }, line: { color: t.accent },
    });
    // Number badge
    slide.addShape(pres.shapes.OVAL, {
      x: x + 0.3, y: startY + 0.35, w: 0.5, h: 0.5,
      fill: { color: t.bg }, line: { color: t.accent, width: 1 },
    });
    slide.addText(String(i + 1), {
      x: x + 0.3, y: startY + 0.35, w: 0.5, h: 0.5,
      fontFace: HEAD_FONT, fontSize: 18, bold: true, color: t.accent,
      align: "center", valign: "middle", margin: 0,
    });
    slide.addText(t.title, {
      x: x + 0.3, y: startY + 1.0, w: cardW - 0.6, h: 0.5,
      fontFace: HEAD_FONT, fontSize: 18, bold: true, color: NAVY, margin: 0,
    });
    slide.addText(t.body, {
      x: x + 0.3, y: startY + 1.55, w: cardW - 0.6, h: 1.7,
      fontFace: BODY_FONT, fontSize: 12, color: TEXT, margin: 0,
    });
  });

  addFooter(slide, n, TOTAL);
}

// ── 16. HELP & SUPPORT ──────────────────────────────────────────────
{
  const slide = pres.addSlide();
  n = 16;
  slide.background = { color: "FFFFFF" };

  slide.addText("WE'RE HERE TO HELP", {
    x: 0.45, y: 0.45, w: 9, h: 0.35,
    fontFace: BODY_FONT, fontSize: 11, bold: true, color: SAFFRON, charSpacing: 4, margin: 0,
  });
  slide.addText("Help & support — India-based team", {
    x: 0.45, y: 0.78, w: 9.1, h: 0.55,
    fontFace: HEAD_FONT, fontSize: 28, bold: true, color: NAVY, margin: 0,
  });

  const channels = [
    { label: "In-app help guide", value: "Visit /help inside your account — 9 sections covering setup → dashboard with Indian-context examples." },
    { label: "Email support", value: "support@datasofttechnologies.com — replies within one business day, usually the same day." },
    { label: "Sales & custom plans", value: "sales@datasofttechnologies.com — for businesses billing 100+ invoices/month or running multiple GSTINs." },
    { label: "Partner with us", value: "CAs, consultants, agencies — earn revenue share by referring Indian SMEs. Visit /partners to enrol." },
  ];

  const cardW = 4.45, cardH = 1.65, gap = 0.2;
  const startX = 0.45, startY = 1.6;

  channels.forEach((c, i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = startX + col * (cardW + gap);
    const y = startY + row * (cardH + gap);
    slide.addShape(pres.shapes.RECTANGLE, {
      x, y, w: cardW, h: cardH,
      fill: { color: BG_SOFT }, line: { color: RING, width: 1 },
    });
    slide.addShape(pres.shapes.RECTANGLE, {
      x, y, w: 0.08, h: cardH, fill: { color: NAVY }, line: { color: NAVY },
    });
    slide.addText(c.label, {
      x: x + 0.3, y: y + 0.2, w: cardW - 0.45, h: 0.4,
      fontFace: HEAD_FONT, fontSize: 14, bold: true, color: NAVY, margin: 0,
    });
    slide.addText(c.value, {
      x: x + 0.3, y: y + 0.65, w: cardW - 0.45, h: 0.95,
      fontFace: BODY_FONT, fontSize: 12, color: TEXT, margin: 0,
    });
  });

  addFooter(slide, n, TOTAL);
}

// ── 17. CLOSING CTA ─────────────────────────────────────────────────
{
  const slide = pres.addSlide();
  n = 17;
  slide.background = { color: NAVY_DEEP };

  // Tricolour accent bar at top
  slide.addShape(pres.shapes.RECTANGLE, { x: 0, y: 0, w: 3.33, h: 0.18, fill: { color: SAFFRON }, line: { color: SAFFRON } });
  slide.addShape(pres.shapes.RECTANGLE, { x: 3.33, y: 0, w: 3.34, h: 0.18, fill: { color: "FFFFFF" }, line: { color: "FFFFFF" } });
  slide.addShape(pres.shapes.RECTANGLE, { x: 6.67, y: 0, w: 3.33, h: 0.18, fill: { color: GREEN }, line: { color: GREEN } });

  slide.addText("READY?", {
    x: 0.6, y: 1.1, w: 9, h: 0.4,
    fontFace: BODY_FONT, fontSize: 14, bold: true, color: SAFFRON, charSpacing: 8, margin: 0,
  });
  slide.addText("Issue your first GST invoice", {
    x: 0.6, y: 1.6, w: 9, h: 0.85,
    fontFace: HEAD_FONT, fontSize: 40, bold: true, color: "FFFFFF", margin: 0,
  });
  slide.addText("in 60 seconds.", {
    x: 0.6, y: 2.45, w: 9, h: 0.85,
    fontFace: HEAD_FONT, fontSize: 40, bold: true, color: SAFFRON, margin: 0,
  });

  slide.addText("apnainvoice.com", {
    x: 0.6, y: 3.7, w: 9, h: 0.55,
    fontFace: HEAD_FONT, fontSize: 26, bold: true, color: "FFFFFF", margin: 0,
  });
  slide.addText("Free for India's MSMEs, SMEs and startups · No credit card · Unlimited invoices", {
    x: 0.6, y: 4.25, w: 9, h: 0.4,
    fontFace: BODY_FONT, fontSize: 14, color: "B8C8E9", margin: 0,
  });

  // Tricolour accent bar at bottom
  slide.addShape(pres.shapes.RECTANGLE, { x: 0, y: 5.45, w: 3.33, h: 0.18, fill: { color: SAFFRON }, line: { color: SAFFRON } });
  slide.addShape(pres.shapes.RECTANGLE, { x: 3.33, y: 5.45, w: 3.34, h: 0.18, fill: { color: "FFFFFF" }, line: { color: "FFFFFF" } });
  slide.addShape(pres.shapes.RECTANGLE, { x: 6.67, y: 5.45, w: 3.33, h: 0.18, fill: { color: GREEN }, line: { color: GREEN } });

  slide.addText("Datasoft Technologies · Made in India", {
    x: 0.6, y: 5.05, w: 9, h: 0.3,
    fontFace: BODY_FONT, fontSize: 11, color: "8FA3C9", margin: 0,
  });
}

pres.writeFile({ fileName: "apna-invoice-getting-started.pptx" })
  .then(name => console.log("Saved:", name));
