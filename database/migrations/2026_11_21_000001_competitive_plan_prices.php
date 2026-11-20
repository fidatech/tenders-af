<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New prices, below the other tender sites of Afghanistan, and WhatsApp alerts from Basic up.
 * A price is only changed while it is still the first default, so a price the admin set stays.
 */
return new class extends Migration
{
    // name => [users, [interval, currency, old price, new price], ...]
    private const PRICES = [
        'Basic' => [1, [['monthly', 'AFN', 800, 600], ['yearly', 'AFN', 7700, 5900], ['monthly', 'USD', 12, 9], ['yearly', 'USD', 115, 89]]],
        'Pro' => [3, [['monthly', 'AFN', 2000, 1000], ['yearly', 'AFN', 19200, 9900], ['monthly', 'USD', 29, 15], ['yearly', 'USD', 278, 149]]],
        'Business' => [10, [['monthly', 'AFN', 5000, 2000], ['yearly', 'AFN', 48000, 19900], ['monthly', 'USD', 69, 29], ['yearly', 'USD', 662, 299]]],
    ];

    public function up(): void
    {
        foreach (self::PRICES as $name => [$users, $rows]) {
            foreach ($rows as [$interval, $currency, $old, $new]) {
                DB::table('plans')->where(compact('name', 'interval', 'currency'))->where('price', $old)->update(['price' => $new, 'updated_at' => now()]);
            }
        }
        DB::table('plans')->where('name', 'Basic')->update(['whatsapp_alerts' => true, 'description' => 'Every tender in full, with email and WhatsApp alerts, for one person.']);
        DB::table('plans')->where('name', 'Pro')->where('description', 'Email and WhatsApp alerts for a small team.')->update(['description' => 'For a small team: three people get the tenders and the alerts.']);
    }

    public function down(): void
    {
        foreach (self::PRICES as $name => [$users, $rows]) {
            foreach ($rows as [$interval, $currency, $old, $new]) {
                DB::table('plans')->where(compact('name', 'interval', 'currency'))->where('price', $new)->update(['price' => $old]);
            }
        }
        DB::table('plans')->where('name', 'Basic')->update(['whatsapp_alerts' => false]);
    }
};
