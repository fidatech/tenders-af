# Tenders.af

Every tender in Afghanistan, in one place, with a free directory of companies. By Fida Technologies.

- **Companies register for free.** A registered company gets a public profile in the directory and reads
  the summary and the deadline of every tender.
- **Tenders are paid.** A paid plan opens the full details and the documents of every tender, the instant
  alerts and the bid price calculator.
- **Two currencies.** Afghan companies pay in AFN; NGOs and international clients, from any country, pay in
  USD. The kind chosen at registration decides the currency.

Built with Laravel 13 on the base of `fidatech/duebook`: the same panel design (fida-admin), plans,
payment checking, receipts and administration.

## What is built

**Public website** (`SiteController`, `resources/views/site`, `public/css/site.css`)

- Home page with search, the latest tenders, sectors, a map of the 34 provinces (each province is a link to
  its tenders), verified companies and questions.
- Tenders: the full list with search and filters, a page for every sector (`/tenders/sector/...`), every
  province (`/tenders/province/...`) and every tender (`/tender/...`). A visitor reads the summary; the rest
  is listed as locked.
- Company directory (`/companies`) with a page for every company.
- Pricing (AFN and USD), how it works, questions, contact (also "post a tender"), privacy, terms.
- For search engines and AI assistants: canonical addresses, structured data (Organization, WebSite with
  search, BreadcrumbList, FAQPage), `/sitemap.xml` with every tender, sector, province and company,
  `/robots.txt` and `/llms.txt`. What the site says about itself is in `config/site.php`.

**Company panel** (after sign-in)

- Dashboard, all tenders, tenders matching the company's alerts, saved tenders.
- Tender page: full details, documents and the bid price calculator on a paid plan; summary otherwise.
- Company profile with logo, sectors and categories; verification by sending the business licence.
- Tender alerts: email and WhatsApp, and the kinds of tender wanted.
- Team, and the Subscription page: plans in the company's currency, the ways to pay, "I have paid" with a
  screenshot, receipts.

**Administration** (`/admin`)

- Tenders: entered as drafts, then published; documents; budget (amount, range or not disclosed, with an
  estimated size); cancelled and awarded.
- Sectors and categories; companies; the verification queue (verify, or refuse with a reason from a list).
- Subscriptions (confirm or reject a payment), plans, **payment methods** (bank accounts, HesabPay, cash,
  others: name, number, QR code, details, currency), expenses, reports, administrators, activity log,
  settings.

**Alerts** (`app/Console/Commands`)

- `tenders:alerts`, every ten minutes: a newly published tender is emailed to the companies whose sectors,
  categories and tender types match and whose plan has instant alerts, and sent by WhatsApp to those that
  asked for it. Every company is told once per tender (`tender_alerts`).
- `tenders:digest`, every Saturday morning: companies on the free plan get one email with the titles of
  the week's matching tenders.
- WhatsApp goes through your own sender service: one POST with `to` and `message` to the address saved under
  /admin > Settings (or `WHATSAPP_API_URL` in `.env`). With no address, no WhatsApp message is sent.

## Buyers and suppliers

An account says what it does on the site, at registration and later in its company profile:

- **Find tenders** (supplier): alerts, saved tenders, full tenders on a paid plan.
- **Post tenders** (buyer): a ministry, a government office, an NGO or a company posts its own tenders under *Posted tenders*.
- One account can do both.

A tender posted by a buyer is saved as a draft or sent for approval. It waits under **Admin > Tenders > Waiting for approval**
until an administrator publishes it or sends it back with a reason; the buyer gets a notification and an email either way.
After publishing, the buyer can extend the deadline, add documents, cancel the tender or name the winner. The public page of
the buyer lists its tenders.

Posting is free while **Admin > Settings > "Posting tenders is free for every buyer"** is on. To start charging: give the
seeded `Buyer` plans (AFN and USD, monthly, switched off) their prices, switch them on, and switch the setting off. From then
on a buyer needs a plan with "May post tenders". Registration stays free and published tenders stay online.

## First steps after installing

1. `php artisan migrate --seed` creates the plans (Free; Basic, Pro, Business in AFN and USD, monthly and
   yearly), the 34 provinces, the sectors with their categories, and two HesabPay payment methods that are
   switched off.
2. `php artisan app:create-admin`, then sign in at `/admin`.
3. **Payment methods**: write your HesabPay number and add your bank accounts, each with its currency
   (AFN or USD), and switch them on. A company sees the methods in its own currency.
4. **Plans**: change the prices if you want. The seeded prices are a starting point.
5. **Settings**: support email and WhatsApp, the billing email, the WhatsApp sender service.
6. **Tenders**: enter the open tenders and publish them.

## Running it on your computer

```
composer install
cp .env.example .env        # set the DB_* lines to a MySQL database
php artisan key:generate
php artisan migrate --seed
php artisan app:create-admin
php artisan serve
```

The stylesheet, the script and the icons of the panel are built without Node: `composer assets`
(`tools/build-assets.php`) after changing views that use new classes or icons. The website's own stylesheet
(`public/css/site.css`) is written by hand.

## Still to come

- Dari and Pashto (the site and the panel are in English today).
- Paying online by card; buying a single tender.
- Online bid submission for buyers, award history and price data.

## Putting it online (WHM / cPanel, tenders.af)

Once, in **cPanel > Terminal** of the account that holds tenders.af:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/fidatech/tenders-af/main/tools/cpanel-setup.sh)
```

It asks for a GitHub token (fine-grained, only this repository, permission **Secrets: Read and write**), your name
and email, and the SSH port. It then makes a deploy key and allows it to sign in, the MySQL database and user, and
the mailbox `noreply@tenders.af`, and saves everything as encrypted secrets of the repository. It prints the
admin password and keeps a copy in `~/.tenders-setup.txt`. The cPanel account needs SSH (shell) access, which is
switched on in WHM > Manage Shell Access.

After that, **every push to `main` deploys by itself** (`.github/workflows/deploy.yml`):

1. GitHub installs the packages and runs the tests.
2. It copies the project to `~/tenders-af` on the server (never `storage/` or `.env`, which it writes from the secrets).
3. It runs `deploy.sh` there: migrations (and, the first time, the sectors, provinces and plans), the administrator
   from the setup, the caches, `public_html` pointing at `tenders-af/public` (the old folder is kept beside it), and the
   cron line for the scheduler.
4. It opens the main pages of the site and writes everything (tests, deploy, page checks, the end of Laravel's log) to
   the branch **`deploy-log`**.

The server needs PHP 8.3 or newer with mbstring, pdo_mysql, intl, gd, zip, bcmath and sodium (WHM > EasyApache 4), and
an SSL certificate for tenders.af (cPanel > SSL/TLS Status > Run AutoSSL).

## Tests

```
php artisan test
```

## Products for wholesale (switched off by default)

Factories and producers show what they make, with up to 6 pictures and one short video, for NGOs, UN agencies,
government offices and traders. Every product needs a unit and a smallest order; buyers contact the company by
WhatsApp or phone, and the site takes no commission.

- Switch it on in **Admin > Settings > Products for wholesale**. While it is off there is no Products page,
  no "My products" in the panel of companies, and nothing is deleted.
- A company lists a limited number of products for free (5 by default). Each product is approved under
  **Admin > Products** before it is shown; a change to a published product is checked again.
- **Sponsoring is paid**: a week or a month, priced in AFN and USD in the settings. The company pays to one of
  the payment methods, uploads the proof, and an administrator confirms it. A sponsored product is shown first
  on the Products page and on the home page, with a "Sponsored" label.
- The company sees views and contact clicks for each product.

## Statistics

`/statistics` shows live counts (tenders, buyers, companies, products) and tables by province and by sector,
with the province map. A figure below **Admin > Settings > "Smallest number that is shown"** (10 by default)
stays hidden, so a new site does not show tiny numbers.

## Pick lists (Select2)

Every `<select>` on the public website, the sign-in and register pages, the company panel and the admin panel is a Select2 list
(jQuery 3.7.1 and Select2 4.0.13, self-hosted under `public/vendor`). `public/js/select2-init.js` sets them up
and `public/css/select2-fida.css` gives them the look of the form boxes in the panels; the website's look for them is in `public/css/site.css`. A list with
8 choices or more gets a search box. Add `data-native` to a `<select>` to keep the plain browser list.

Sign in, register and the password pages use `layouts/gate.blade.php`, in the look of the website. The admin sign-in keeps the look of the panel.

## Upload limits

A tender can get up to 20 documents in one save (50 in all), 20 MB each, chosen or dropped in several rounds.
`public/.user.ini` raises PHP's limits on servers that read it (cPanel with PHP-FPM). With `php artisan serve` on your
own computer, PHP's own `php.ini` decides: set `upload_max_filesize = 25M`, `post_max_size = 420M` and
`max_file_uploads = 30` there (`php --ini` shows which file), or files above 2 MB are dropped.

## Panel of companies and buyers

The panel that companies and buyers use (`layouts/app.blade.php`) sits inside the website: the site's header and
links, a dark band with the company's name, verification and plan, the account menu on the left and the page on the
right, and the site's footer. Its frame and colours are in `public/css/portal.css` (hand-written, not built); the parts
inside the pages still come from `app.css`. The admin panel keeps the fida-admin look (`layouts/admin.blade.php`).

## Module Management

**Admin > Platform > Module Management** switches parts of the platform on and off (`config/modules.php`,
`App\Support\Modules`). A switched-off module is hidden from the menus, its pages answer with an explanatory
page (status 404, `noindex`) or send a company back to its dashboard, and it leaves the sitemap. Nothing saved in
it is deleted. Modules that need another module (Mini-Websites, Projects, the profile document and the business-type
directories need the Company Directory) stop with it and come back with it.

- Tender Management is always on. Products keeps its old setting (`products_enabled`) as its switch, so a site
  that already used Products is unchanged.
- Each module can also be hidden from the website menus or the company panel, have its notifications silenced,
  or be limited to companies on a paid plan.
- Only a super administrator changes modules; every change is kept in the history on that page and in the
  activity log.
- RFQs, supplier contacts, product catalogue documents and international features are listed as **Planned**:
  they are not built yet, so there is nothing to switch on.

In code: `module_on('directory')` in views, `->middleware('module:directory')` on routes,
`'module' => 'products'` on an entry of `config/menu.php`.

## Branding & Logo

**Admin > Platform > Branding & Logo**: the website name and tagline, the main logo, a version for dark
backgrounds, a mobile logo, a square icon and a favicon, the logo height on computers and phones, and the alt text.
Until a logo is uploaded the site keeps the text brand "Tenders.af". Pictures (PNG, JPG or WebP, up to 2 MB) are
decoded and saved again as PNG with GD, which drops metadata and anything hidden in the file; SVG is refused
because it can carry scripts. They are kept in `storage/app/private/branding` and served from `/brand/{asset}` with
a version in the address, so `storage:link` is not needed. The icon also makes the phone app icons (192 and 512
px) listed in `/site.webmanifest`.

## Phones

Below 768 px the website and the company panel get a bottom bar (Home, Tenders, Companies or Products when those
modules are on, Messages in the panel, and Menu/Account) with a menu sheet for everything else
(`partials/mobile-nav.blade.php`, `public/css/mobile-nav.css`, `public/js/mobile-nav.js`). It keeps clear of the
iPhone and Android safe areas and steps aside while the keyboard is open. The filter boxes of the list pages become
a "Filters" drawer with chips (`public/js/filters.js`); on a computer they look as before. The province map always
fits the screen width (`site/_map.blade.php`, `public/js/afmap.js`): a tap shows the province first, a second tap
opens it. A service worker (`/sw.js`) keeps only an offline page; tenders, payments and messages are never served
from a copy.

## Company pages, projects and the profile document

- **Company profile** (owner): kinds of business (manufacturer, importer, exporter ...), the public page (slogan,
  cover picture, services, template and one of six approved colours) and **contact sharing**: the company ticks
  each detail it allows (phone, WhatsApp, email, website) and chooses who sees them: everyone, signed-in companies on
  a paid plan, or nobody (enquiries only). Restricted details are decided on the server and are left out of the page,
  its structured data and the profile document. Companies that existed before keep showing their phone and website.
- **Projects** (`/my/projects`): drafts and published projects with pictures, and private evidence documents. A
  project shows "Company submitted" until a reviewer under **Admin > Company projects** records what was checked
  (pending, evidence reviewed, client reference confirmed, not accepted). "Evidence reviewed" needs documents; a
  refusal needs a reason; changing the facts of a checked project sends it back to "Company submitted".
- The company page shows separately what Tenders.af checked: profile claimed, business licence, project references.
- **Profile document** (`/my/profile-pdf`): short, standard and detailed versions as a print-ready A4 page that the
  browser saves as a PDF; a public version (what visitors may see) and a full version for the company itself.
  Visitors open the public version from the company page.
- The directory can be filtered by kind of business; manufacturer and importer/exporter/wholesaler filters appear
  when their modules are on. Profiles with nothing written about the company are `noindex` and left out of the sitemap.

## Saved suppliers and business enquiries

- **Supplier Favourites & Collections** (module, off at first, paid plans by default): from a company page a
  signed-in company saves the company into its Favourites or another collection. Collections are shared with the
  team unless their maker made them private; each saved company has a status (researching, shortlisted, contacted,
  qualified, preferred, rejected), private notes and tags. Two to four can be compared side by side (facts only; no
  scores), and a collection can be exported as CSV, in which contact details follow each company's sharing choice.
  Saved companies never see any of it.
- **Business Enquiries** (module, off at first): a signed-in company sends an enquiry from another company's page
  (with an optional PDF or picture); both companies' people answer in a private thread under **Enquiries**, on any
  plan. Either side can close the thread, block the other company or report it. Administrators with the "messages"
  permission see a thread only after it is reported (**Admin > Reported enquiries**), and opening it is logged.
  A company starts at most 20 enquiries a day.

## Statistics and visitors

`/statistics` shows tenders by month, sector, province, buyer, kind and deadline; companies by kind of business
and verification; and visitors of the last 30 days by day, country, city and device. Every figure is counted;
a section appears once it has data and zeros are left out (the minimum is **Admin > Settings > stats_min**, 1 by
default). The figures can be downloaded as CSV from `/statistics.csv`. **Admin > Platform > Visitors** shows the
full detail (every city, pages opened, referring websites) for 7, 30, 90 or 365 days.

Visitors are counted by `App\Http\Middleware\CountVisit` on public pages only, after the page is sent. No cookie is
set and no IP address is stored: a visitor is a daily code that cannot be turned back into the address. Robots,
prefetches, signed-in administrators and browsers sending Do Not Track or Global Privacy Control are skipped.
Records older than 13 months are deleted every night (`visits:prune`). Cities with fewer than 3 visitors are left
off the public page.

### Visitor locations (country and city)

The place is looked up on the server, in the free DB-IP "IP to City Lite" database (CC BY 4.0; the statistics page
shows the required "IP Geolocation by DB-IP" line). Two parts are needed:

1. The reader library, once: `composer require maxmind-db/reader` (commit `composer.json` and `composer.lock`).
2. The database file: `php artisan geoip:update` downloads it to `storage/app/geoip/`. The deployment runs it in the
   background when the file is missing, and the scheduler renews it on the 5th of every month.

Until both are there, visits are still counted, with the place "Unknown". Behind Cloudflare, set
`GEOIP_TRUST_CLOUDFLARE=true` to use its country header as well. Country names come from PHP's `intl` extension,
with `config/countries.php` as a fallback.
