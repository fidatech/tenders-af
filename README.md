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

**Before the first install**

- DNS: at the registrar of the domain, point tenders.af to the server (its nameservers, or an A record
  to its IP address).
- WHM: create the cPanel account for tenders.af, give it shell access (Manage Shell Access > Normal
  Shell), and make sure EasyApache has PHP 8.3 or newer with the extensions `mbstring`, `pdo_mysql`,
  `fileinfo`, `openssl`, `ctype`, `tokenizer`, `xml`, `curl`. Set that PHP for the domain in MultiPHP Manager.
- cPanel: create a MySQL database and user (all privileges), a mailbox such as `no-reply@tenders.af`,
  and run AutoSSL for the domain.

**First install**, signed in over SSH as the cPanel user (replace `ea-php83` with the version you set):

```
cd ~
P=/opt/cpanel/ea-php83/root/usr/bin/php
git clone -b main https://github.com/fidatech/tenders-af.git tenders-af
cd tenders-af
$P $(which composer) install --no-dev --optimize-autoloader     # no Composer on the server? see below
cp .env.example .env            # then fill it in: see below
$P artisan key:generate
$P artisan migrate --force --seed
$P artisan app:create-admin
$P artisan config:cache && $P artisan route:cache && $P artisan view:cache

# the web server shows ~/public_html: make it the public folder of the project
cd ~
mv public_html public_html.old
ln -s tenders-af/public public_html
```

**Or inside `public_html` itself** (no separate folder): clone the project into `~/public_html` and add
these lines to `~/public_html/.htaccess`, below anything cPanel wrote there. Every request is then sent to
`public/`, so nothing else in the folder (`.env`, `vendor`, `storage`) can be opened from the web. The file
is not part of the repository, so cPanel can keep its own lines in it and deploys leave it alone.

```
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/\.well-known/
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

No Composer on the server: `cd ~/tenders-af && curl -sS https://getcomposer.org/installer | $P`, then use
`$P composer.phar install ...` in place of the Composer line (`deploy.sh` finds `composer.phar` by itself).

In `.env` on the server: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tenders.af`, the
`DB_*` lines of the database (cPanel names look like `USERNAME_tenders`), and the mailbox:
`MAIL_MAILER=smtp`, `MAIL_HOST=mail.tenders.af`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`,
`MAIL_USERNAME=no-reply@tenders.af`, `MAIL_PASSWORD=...`, `MAIL_FROM_ADDRESS=no-reply@tenders.af`.

Cron job (cPanel > Cron Jobs), every minute:

```
/opt/cpanel/ea-php83/root/usr/bin/php /home/USERNAME/tenders-af/artisan schedule:run >/dev/null 2>&1
```

**Updates.** `bash deploy.sh` in `~/tenders-af` brings the server up to date with the `main` branch: it
fetches the code, installs packages, runs new migrations and rebuilds the caches, using the newest
EasyApache PHP it finds. The workflow `.github/workflows/deploy.yml` runs it over SSH after every push to
`main`, once the five secrets named at the top of that file are added to the repository.

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
