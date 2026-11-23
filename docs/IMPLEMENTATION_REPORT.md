# Implementation and verification report

Branch `platform-modules-branding-mobile`, against `main` at `cbe44f0`. Nothing was deployed: every push to
`main` deploys to tenders.af, so this work is delivered as a pull request for review.

## How it was verified

- **Automated tests:** 91 tests, 817 assertions, all passing (`php artisan test`). 46 of them are new
  (`BrandingTest`, `ModuleTest`, `MobileNavTest`, `CompanyDirectoryTest`, `CollectionTest`, `EnquiryTest`, and a
  structured-data test in `SiteTest`).
  The 45 existing tests still pass; two assertions were updated on purpose (see "Changed behaviour").
- **Real browser:** Chrome (headless, Playwright) against the app on SQLite. Every public, company-panel and admin
  page was opened at 320, 360, 375, 390, 414, 430, 768, 1024, 1280 and 1440 px and checked for horizontal overflow
  (document width and every element). **Zero pages overflow at any width.** Screens were also inspected by eye:
  map, header, bottom bar, menu sheets, filter drawer, company page, project form, admin pages, and the A4
  document printed to PDF.
- **Migrations:** applied on a database seeded by the existing migrations, rolled back and applied again.

## Implemented and tested

| Area | What works |
|---|---|
| Country (province) map on phones | Fits 100 % of its box at every width: the fixed 700 px width and the sideways scroller are gone; the SVG scales with its `viewBox`; small-province names are hidden and large ones enlarged on phones; a tap shows the province and its count, a second tap opens it. Used on the home page, /provinces and /statistics. |
| Branding & Logo | Admin page (super administrators only): name, tagline, main/dark/mobile logo, icon, favicon, heights, display mode, alt text, preview, replace, remove. Text brand fallback everywhere until a logo exists. Used in the site header and footer, sign-in pages, company panel, admin sidebar, emails, favicon, app icons and the web app manifest. Uploads re-encoded with GD; SVG and disguised files refused. |
| Module Management | 14 modules; 10 built and switchable, 4 listed as Planned. Switches in the settings table, read once per request. Dependencies, menu visibility (site and panel), notifications, paid-only access, route middleware, sitemap and link hiding, explanatory `noindex` 404 page, history with notes and admin log. Products keeps its existing `products_enabled` switch. |
| Mobile navigation | Bottom bar and menu sheet for the website and the company panel; tabs and entries follow modules and permissions (owner-only pages stay hidden from members); safe areas; keyboard handling; Escape, backdrop and swipe to close; focus kept in the sheet. Admin panel keeps its existing tab bar. |
| Filters on phones | Tender, company and product lists: search box plus a "Filters" drawer with Apply / Clear all and removable chips. Desktop unchanged. |
| PWA | Dynamic manifest with the brand's name and icons, standalone mode, service worker that keeps only an offline page (never live data). |
| Business types | 13 types, several per company; shown on the page and in the document; directory filter; manufacturer and importer/exporter/wholesaler filters gated by their modules. |
| Contact privacy | Per-detail approval and visibility (everyone / paid subscribers / nobody). Enforced on the server; restricted details are absent from the HTML, the JSON-LD and the document. Existing companies unchanged (phone and website). |
| Company mini-website | Cover, slogan, services, five templates (order of sections), six approved accent colours, featured projects, similar companies, honest "what was checked" panel. Every template works on phones. No HTML from companies is rendered. |
| Project portfolios | Company CRUD with drafts, publishing, featured, up to 8 pictures and 5 private evidence files; public project pages; admin review queue with search, filters, sorting and pagination; distinct review statuses; rules that stop a project being marked reviewed without evidence; review reset when facts change; notifications to admins and companies. Companies cannot reach each other's projects. |
| Profile completeness | Extended with business type, services, projects and products (when their modules are on); shown on the dashboard and the document page, with no claim that a complete profile proves anything. |
| Supplier Favourites & Collections | Save from a company page into Favourites or a new/existing collection; private or team collections; statuses, private notes, tags, filters; move between collections; compare 2–4 companies on facts only; CSV export that follows contact sharing and neutralises spreadsheet formulas. Paid plans by default (changeable per module). Notes are never visible to the saved companies or other companies. |
| Business Enquiries | Enquiry form on company pages (with optional file); private threads; read/unread per company; replies on any plan; close/reopen; block a company; report a thread; administrators see only reported threads and the reading is logged; 20 new enquiries per company per day plus request throttles; notifications. "Enquiries only" companies get this form instead of the contact page when the module is on. |
| Public flash messages | The public site never showed the result of a form; it now shows a success/error banner. |
| SEO | Fixed a live bug: Blade compiled `"@context"` in the JSON-LD as a directive, so **every public page on `main` (and so, most likely, in production) prints PHP source instead of valid structured data**. Empty company profiles are `noindex` and out of the sitemap; disabled modules leave the sitemap and llms.txt. |

## Implemented but not fully verified

- **Profile document (A4):** short, standard and detailed versions, public and full contact versions, RTL text with
  the bundled Vazirmatn font, page numbers. Verified by printing to PDF in Chrome. It is a print-ready page that the
  browser saves as PDF, not a server-generated PDF file (see below). Dari/Pashto text was not tested with real content.
- **iPhone safe areas and keyboard behaviour** were tested in Chrome's mobile emulation, not on physical iOS/Android devices.

## Dependent on something outside this environment

- **Server-side PDF files and QR codes:** need a PDF library such as `mpdf/mpdf` (with `mpdf/qrcode`). Package
  downloads from Packagist were blocked here, so it could not be added. The document view was written so it can
  be passed to mPDF unchanged.
- **Production checks:** no access to the server; deployment, the real database and real mail were not touched.

## Not implemented (with the reason)

The brief describes several years of product work. This branch delivers the foundation every other part depends
on (modules, branding, mobile navigation), the company-directory core, saved suppliers and business enquiries. Not built yet, and listed as **Planned**
in Module Management so nobody can switch on an empty feature:

- Buyer RFQs and quotations; contact-view logs and a separate "contacts" product (restricted contacts already work
  through contact sharing); saved searches; product favourites; product catalogue documents; international
  marketplace features (currencies, translations beyond the existing setup).
- Enquiries are a separate inbox, not merged into the existing Messages page: that page is a company ↔
  administrators support channel keyed to one user, and folding company threads into it would change how it works.
- Multiple companies per user with company-level roles (the app has one company per user with owner/member).
- Official registry import, company claims and invitations, storage quotas, malware scanning, the admin SEO
  dashboard, `hreflang`, IndexNow, the 5,000 AFN plan (plans are already configurable under Admin > Plans; no plan was
  created or changed, to avoid touching paying customers).
- Admin-side editing of the new company fields (companies edit them in their own panel).

## Changed behaviour to review

1. A company that opens a page of a switched-off module is sent to its dashboard with a note instead of a bare 404
   (one assertion in `ProductTest` updated).
2. The company page says "Business licence checked" instead of "Verified company", and lists separately what was
   checked (one assertion in `CompanyTest` updated).
3. The Products switch moved from Settings to Module Management (same stored value). Saving Settings no longer
   changes it.
4. On phones the header shows only the brand and one button; the links live in the bottom bar and its sheet.
5. Company pages with nothing written about the company are no longer offered to search engines.
6. The public site shows a banner with the result of a form (it showed nothing before).

## Found during the review, not changed

- **The deploy workflow deploys even when tests fail.** In `.github/workflows/deploy.yml` the Tests step has
  `continue-on-error: true` and later steps do not check its outcome. Consider making deployment depend on
  `steps.tests.outcome == 'success'`.
- `database/database.sqlite` is not git-ignored; this branch adds `/database/*.sqlite` to `.gitignore`.

## Deploying

The normal pipeline applies everything: `php artisan migrate` runs the two new, additive migrations (new nullable
columns on `companies`; new tables `module_changes`, `company_projects`, `company_project_files`,
`supplier_collections`, `supplier_collection_items`, `enquiries`, `enquiry_messages`, `company_blocks`). No data is changed.
Before merging: back up the database. After deploying:

1. Sign in as a super administrator and open **Module Management**: check the switches (Directory, Mini-Websites,
   Projects and the profile document are on; Products keeps its current state; Manufacturer and Importer/Exporter
   filters, Collections and Enquiries are off until you switch them on).
2. Open **Branding & Logo** and upload the logo when ready; until then the text brand stays.
3. Rollback: `php artisan migrate:rollback --step=4` removes the new tables and columns (and anything saved in them).
