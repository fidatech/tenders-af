<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Industry;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * What a fresh installation starts with: the plans (in AFN and in USD), the 34 provinces,
 * the sectors with their categories, and HesabPay as a first way to pay. Everything can be
 * changed under /admin. Running it again adds only what is missing.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->plans();
        $this->provinces();
        $this->sectors();
        $this->accounts();
    }

    /** Free for everybody; Basic, Pro and Business by the month or the year (the year costs about 20% less). */
    private function plans(): void
    {
        Plan::firstOrCreate(['name' => 'Free', 'interval' => 'lifetime'], [
            'price' => 0, 'currency' => 'AFN', 'max_users' => 1, 'sort' => 1,
            'full_access' => false, 'instant_alerts' => false, 'whatsapp_alerts' => false,
            'description' => 'A company profile in the directory and the summary of every tender.',
        ]);

        // name => [users, WhatsApp, description, [AFN a month, AFN a year, USD a month, USD a year]]
        $paid = [
            'Basic' => [1, false, 'Full tenders and email alerts for one person.', [800, 7700, 12, 115]],
            'Pro' => [3, true, 'Email and WhatsApp alerts for a small team.', [2000, 19200, 29, 278]],
            'Business' => [10, true, 'For companies that bid every week.', [5000, 48000, 69, 662]],
        ];
        $sort = 2;
        foreach ($paid as $name => [$users, $whatsapp, $description, $prices]) {
            foreach ([['AFN', 'monthly', $prices[0]], ['AFN', 'yearly', $prices[1]], ['USD', 'monthly', $prices[2]], ['USD', 'yearly', $prices[3]]] as [$currency, $interval, $price]) {
                Plan::firstOrCreate(['name' => $name, 'interval' => $interval, 'currency' => $currency], [
                    'price' => $price, 'max_users' => $users, 'sort' => $sort++,
                    'full_access' => true, 'instant_alerts' => true, 'whatsapp_alerts' => $whatsapp,
                    'description' => $description,
                ]);
            }
        }
    }

    private function provinces(): void
    {
        foreach (require resource_path('data/provinces.php') as $province) {
            Province::firstOrCreate(['slug' => $province['slug']], ['name' => $province['name'], 'capital' => $province['capital']]);
        }
    }

    private function sectors(): void
    {
        // name => [icon, categories]
        $sectors = [
            'Construction' => ['helmet-safety', ['Buildings', 'Roads and bridges', 'Water and irrigation works', 'Renovation and repair', 'Construction materials']],
            'Health and medicine' => ['kit-medical', ['Medicine', 'Medical equipment', 'Medical consumables', 'Laboratory supplies']],
            'ICT and telecom' => ['laptop', ['Computers and IT equipment', 'Software and websites', 'Internet services', 'Telecom equipment']],
            'Agriculture and livestock' => ['seedling', ['Seeds and fertiliser', 'Agricultural tools and machinery', 'Livestock and veterinary', 'Greenhouses and irrigation']],
            'Food and relief items' => ['utensils', ['Food items', 'Non-food item kits', 'Winter kits', 'Hygiene kits']],
            'Transport and logistics' => ['truck', ['Vehicle rental', 'Vehicles and spare parts', 'Freight and cargo', 'Fuel']],
            'Energy' => ['bolt', ['Solar systems', 'Generators', 'Electrical works']],
            'Consultancy and training' => ['comments', ['Research and surveys', 'Monitoring and evaluation', 'Training and capacity building', 'Audit and accounting']],
            'Office supplies and printing' => ['print', ['Stationery', 'Furniture', 'Printing and visibility']],
            'Education' => ['graduation-cap', ['School supplies', 'Textbooks and learning materials']],
            'Water and sanitation' => ['droplet', ['Wells and boreholes', 'Water supply networks', 'Latrines and sanitation']],
            'Security and cleaning services' => ['shield', ['Security services', 'Cleaning services']],
            'Other' => ['box', []],
        ];
        $sort = 1;
        foreach ($sectors as $name => [$icon, $categories]) {
            $industry = Industry::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'icon' => $icon, 'sort' => $sort++]);
            foreach ($categories as $category) {
                Category::firstOrCreate(['slug' => Str::slug($category)], ['industry_id' => $industry->id, 'name' => $category]);
            }
        }
    }

    /**
     * HesabPay in both currencies, switched off until its number is written under
     * /admin > Payment methods. Bank accounts and other ways to pay are added there too.
     */
    private function accounts(): void
    {
        foreach (['AFN', 'USD'] as $currency) {
            PlatformAccount::firstOrCreate(['name' => 'HesabPay ('.$currency.')'], [
                'type' => 'mobile',
                'currency' => $currency,
                'is_active' => false,
                'details' => "Send the amount to the HesabPay number above, or scan the QR code.\nThen take a screenshot of the payment and add it below.",
            ]);
        }
    }
}
