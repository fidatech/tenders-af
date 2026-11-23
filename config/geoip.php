<?php

/*
 * Finding the country and city of a visitor (App\Support\GeoIp), for the visitor figures.
 *
 * The lookup reads a local database file with the maxmind-db/reader library
 * (composer require maxmind-db/reader). The file is the free "IP to City Lite" database of DB-IP
 * (https://db-ip.com, licence CC BY 4.0, which asks for the line "IP Geolocation by DB-IP" where the
 * data is shown). `php artisan geoip:update` downloads it; the scheduler does so every month.
 * Nothing is sent to another company: the lookup happens on the server.
 */
return [
    'database' => env('GEOIP_DATABASE', storage_path('app/geoip/dbip-city-lite.mmdb')),

    // {month} is replaced by the month of the file, like 2026-11
    'download_url' => env('GEOIP_DOWNLOAD_URL', 'https://download.db-ip.com/free/dbip-city-lite-{month}.mmdb.gz'),

    // When the site is behind Cloudflare, its CF-IPCountry header gives the country even without the
    // database file. Leave it off otherwise: anyone could send that header.
    'trust_cloudflare' => (bool) env('GEOIP_TRUST_CLOUDFLARE', false),

    'attribution' => ['IP Geolocation by DB-IP', 'https://db-ip.com'],
];
