# Apna Invoice, SEO Launch & Off-Page Playbook

This is the action list for the work that **cannot be done in code**. The site's
on-page and technical SEO is already in good shape (structured data, sitemap,
robots, meta, fast fonts, social card). Rankings now come from getting indexed,
earning authority, and publishing useful content over time.

Honest expectation: a new domain does not rank fast no matter how clean the code
is. Plan for **3 to 6 months** to see meaningful organic movement, faster for
long-tail and brand queries, slower for head terms like "GST invoice generator".
Do the items below in order. The first section is the highest leverage.

---

## 1. Get indexed (do this first, takes one evening)

- [ ] Confirm `APP_URL` in production is the real https domain (no trailing slash).
- [ ] **Google Search Console** (search.google.com/search-console)
  - [ ] Add the property (prefer the Domain property via DNS TXT).
  - [ ] Or use the URL-prefix property and paste the token into `GOOGLE_SITE_VERIFICATION` env, then redeploy. The tag is already wired in `<head>`.
  - [ ] Submit the sitemap: enter `sitemap.xml` under Sitemaps.
  - [ ] Use "URL Inspection" on the homepage, `/gst-calculator`, `/free-gst-invoice-format`, and `/blog` and click "Request indexing" for each.
- [ ] **Bing Webmaster Tools** (bing.com/webmasters)
  - [ ] Add the site, then "Import from Google Search Console" (one click).
  - [ ] Or paste the token into `BING_SITE_VERIFICATION` env. Submit `sitemap.xml`. (Bing also feeds DuckDuckGo and ChatGPT search.)
- [ ] Confirm Google Analytics 4 is recording (the GA tag `G-8RLQRM0KMV` is live in production). Link GA4 to Search Console inside GA Admin so you see organic queries next to behaviour.

## 2. Google Business Profile (big for India, free)

A Business Profile gets you into Maps and the local pack, and is a strong trust
and citation signal.

- [ ] Create a profile at business.google.com for "Datasoft Technologies" / "Apna Invoice".
- [ ] Category: "Software company". Add the website (apnainvoice.com), phone, and hours.
- [ ] Keep **NAP** (Name, Address, Phone) identical everywhere it appears. Use the exact phone in `config/seo.php` (`+91 74286 93901`).
- [ ] Add the logo, a few product screenshots, and a short description with "GST invoice generator for Indian MSMEs".
- [ ] Post an update once a month (a feature, a tip). Profiles that post rank better.

## 3. The single highest-value backlink you control

- [ ] On **datasofttechnologies.com**, add a real, do-follow link to apnainvoice.com.
  - Put it in the main nav or a "Products" section, plus a dedicated product page that describes Apna Invoice and links to it. This passes authority from your own established domain and tells Google the two are related.
  - Use natural anchor text, for example "Apna Invoice, our GST invoicing tool", not "best GST invoice generator".
- [ ] Reciprocate: the Apna Invoice footer already links back to datasofttechnologies.com. Keep it.

## 4. Citations and directory listings (steady authority)

Submit consistent NAP + URL to these. Each is a citation; together they build trust.

India business directories:
- [ ] Justdial
- [ ] IndiaMART
- [ ] Sulekha
- [ ] TradeIndia

Startup and SaaS directories (high relevance for a SaaS):
- [ ] Crunchbase
- [ ] Product Hunt (do a proper launch day, see section 6)
- [ ] G2, Capterra, GetApp (the Gartner network, strong for software queries)
- [ ] SoftwareSuggest and Techjockey (both India-focused, used by buyers searching billing software)
- [ ] SaaSworthy
- [ ] AlternativeTo (list as an alternative to spreadsheet/desktop billing)

General:
- [ ] LinkedIn Company Page (for Datasoft, with Apna Invoice as a product/showcase page)
- [ ] A Google Knowledge Panel will tend to form once GBP + Crunchbase + LinkedIn agree on the facts.

## 5. Content that earns its own traffic (ongoing, the long game)

You already target strong keyword clusters in `config/seo.php`. Turn the biggest
ones into genuinely useful blog posts (the blog is already indexable and in the
sitemap). Aim for one solid post every 1 to 2 weeks. Suggested first set, each
mapped to real search intent:

- [ ] "GST invoice format in India, with a free template" (links to `/free-gst-invoice-format`)
- [ ] "How to calculate GST: inclusive vs exclusive, with examples" (links to `/gst-calculator`)
- [ ] "CGST, SGST and IGST explained simply, with when to use each"
- [ ] "HSN and SAC codes: how many digits your invoice needs by turnover"
- [ ] "Credit note vs debit note under GST Section 34"
- [ ] "GSTR-1 filing: what your invoices need to contain"
- [ ] "Invoice numbering rules under GST (and the financial year reset)"
- [ ] "How a freelancer in India should bill clients with GST"
- [ ] "Best way to send invoices on WhatsApp (and get paid faster)"
- [ ] "Switching from Excel invoicing: a step-by-step guide"

Writing rules that keep quality high:
- Answer the question fully and plainly. Match the human, no-fluff tone of the site.
- Link each post to the relevant tool page and to "create an invoice free".
- Add a short FAQ block to each post (the blog template can carry FAQ schema).
- Do **not** spin out thin, near-identical "city pages" (Mumbai, Delhi, Pune ...). Google treats these as doorway pages and can penalise them. One genuinely useful page beats fifty thin ones.

## 6. Launch moments (links + traffic spikes)

- [ ] **Product Hunt launch.** Prepare assets (the new OG card works), launch on a Tuesday to Thursday, rally early upvotes. A good launch yields a do-follow link and referral traffic.
- [ ] Post the launch on the founder's LinkedIn and any relevant Indian SME / startup communities (without spamming).
- [ ] Answer real questions on Quora and Reddit (r/india, r/IndianStreetBets-adjacent business subs, r/freelanceindia) where "GST invoice" or "billing software" comes up. Be helpful first, link only when it genuinely answers.

## 7. Keep it healthy (monthly, 30 minutes)

- [ ] Check Search Console: which queries show impressions, where you rank, what to improve. Update titles/content for pages stuck on page 2.
- [ ] Run the homepage and `/gst-calculator` through PageSpeed Insights (pagespeed.web.dev). Watch the Core Web Vitals (LCP, CLS, INP). The font and preconnect work is done; fix any new regressions.
- [ ] The sitemap updates itself as you publish blog posts. No action needed.
- [ ] Watch for and disavow any spammy backlinks (rare, but check).

## What NOT to do (these cause penalties)

- Do not buy backlinks or use link farms / PBNs.
- Do not mass-produce thin city or keyword-variant pages.
- Do not stuff keywords into copy or hide text.
- Do not copy competitor content. Write your own.
- Do not name or disparage competitors in your copy.

---

### Quick reference: what is already done in code

- Sitemap at `/sitemap.xml` (auto-includes blog posts), `robots.txt` pointing to it.
- Full meta, canonical, hreflang (en-IN), Open Graph + Twitter cards, geo tags.
- Structured data: Organization, WebSite + SearchAction, SoftwareApplication, FAQ, HowTo, Article, Breadcrumb.
- 1200x630 social share card (`public/brand/og-card.png`, regenerate with `php scripts/make-og-card.php`).
- Performance: crossorigin preconnect, non-blocking fonts, LCP image preload, async analytics.
- Indexable tool/guide pages: `/gst-calculator`, `/free-gst-invoice-format`, linked from the footer.
- Verification tag slots ready: set `GOOGLE_SITE_VERIFICATION` and `BING_SITE_VERIFICATION` env vars.
