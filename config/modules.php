<?php

/*
 * The modules of Tenders.af, switched on and off under Admin > Module Management (App\Support\Modules).
 *
 *   name, description   shown to the administrators
 *   default             the state before an administrator saves anything
 *   locked              true: cannot be switched off (the core of the site)
 *   depends             modules that must be on as well; when one of them is off this module is off too
 *                       (its own switch is kept, so it comes back when the other module is switched on)
 *   setting             where the switch is stored in the settings table. Products keeps the key it has
 *                       always had, so a site that already uses it keeps working.
 *   built               false: planned, not built yet. Listed honestly, but there is nothing to switch on.
 *   public_nav, dashboard_nav   whether the module offers links in those menus (each can be hidden)
 *   notifications       whether the module sends notifications (each can be silenced)
 *   access              who may use it when it is on: "all" companies, or "paid" plans only
 */
return [
    'tenders' => [
        'name' => 'Tender Management',
        'description' => 'Tenders, sectors, provinces, alerts and the tenders that buyers post. The core of the site.',
        'default' => true,
        'locked' => true,
        'built' => true,
        'public_nav' => true,
        'dashboard_nav' => true,
        'notifications' => true,
    ],
    'directory' => [
        'name' => 'Company Directory',
        'description' => 'The public list of companies with search and filters, and a page for every company.',
        'default' => true,
        'built' => true,
        'public_nav' => true,
        'dashboard_nav' => false,
        'notifications' => false,
    ],
    'mini_websites' => [
        'name' => 'Company Mini-Websites',
        'description' => 'The richer company page: cover picture, services, offices, galleries and the choice of template and colour.',
        'default' => true,
        'depends' => ['directory'],
        'built' => true,
        'public_nav' => false,
        'dashboard_nav' => true,
        'notifications' => false,
    ],
    'portfolios' => [
        'name' => 'Company Project Portfolios',
        'description' => 'Projects a company has carried out, with pictures, on its public page and in its own panel.',
        'default' => true,
        'depends' => ['directory'],
        'built' => true,
        'public_nav' => false,
        'dashboard_nav' => true,
        'notifications' => true,
    ],
    'products' => [
        'name' => 'Product Marketplace',
        'description' => 'Products for wholesale with pictures and a video, approved by an administrator; paid sponsoring.',
        'default' => false,
        'setting' => 'products_enabled',
        'built' => true,
        'public_nav' => true,
        'dashboard_nav' => true,
        'notifications' => true,
    ],
    'manufacturers' => [
        'name' => 'Manufacturer Directory',
        'description' => 'Finding manufacturers in the directory by business type.',
        'default' => false,
        'depends' => ['directory'],
        'built' => true,
        'public_nav' => true,
        'dashboard_nav' => false,
        'notifications' => false,
    ],
    'trade' => [
        'name' => 'Importer/Exporter Directory',
        'description' => 'Finding importers, exporters and wholesalers in the directory by business type.',
        'default' => false,
        'depends' => ['directory'],
        'built' => true,
        'public_nav' => true,
        'dashboard_nav' => false,
        'notifications' => false,
    ],
    'company_pdf' => [
        'name' => 'Company Profile PDF Generator',
        'description' => 'A printable company profile made from what the company entered, with a QR code to its page.',
        'default' => true,
        'depends' => ['directory'],
        'built' => true,
        'public_nav' => false,
        'dashboard_nav' => true,
        'notifications' => false,
    ],
    'rfqs' => [
        'name' => 'Buyer RFQs',
        'description' => 'Buyers ask for quotations; suppliers answer with prices and lead times.',
        'default' => false,
        'depends' => ['directory'],
        'built' => false,
    ],
    'connections' => [
        'name' => 'Supplier Contacts & Connections',
        'description' => 'Restricted business contacts opened to paid subscribers, with contact logs.',
        'default' => false,
        'depends' => ['directory'],
        'built' => false,
    ],
    'collections' => [
        'name' => 'Supplier Favourites & Collections',
        'description' => 'Saved companies in private collections with notes, tags and statuses.',
        'default' => false,
        'depends' => ['directory'],
        'built' => false,
    ],
    'catalogue_pdf' => [
        'name' => 'Product Catalogue PDF Generator',
        'description' => 'A printable catalogue of a company\'s products.',
        'default' => false,
        'depends' => ['products'],
        'built' => false,
    ],
    'international' => [
        'name' => 'International Marketplace Features',
        'description' => 'Companies and buyers from other countries, other currencies and international supplier matching.',
        'default' => false,
        'depends' => ['directory'],
        'built' => false,
    ],
];
