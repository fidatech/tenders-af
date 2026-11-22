<?php

namespace App\Support;

use App\Models\DirectMessage;
use App\Models\Notice;
use App\Models\User;

/**
 * The bottom bar and the menu sheet shown on phones (resources/views/partials/mobile-nav.blade.php).
 * Only modules that are on, and pages the person may open, are offered.
 *
 * A tab:   ['label', 'url', 'icon', 'active' => bool, 'badge' => int]
 * A sheet: ['heading' => [entries...]], an entry: ['label', 'url', 'icon', 'badge' => int, 'form' => id]
 */
class MobileNav
{
    /** The public website. */
    public static function site(): array
    {
        $user = auth('web')->user();
        $tabs = [
            ['label' => 'Home', 'url' => route('site.home'), 'icon' => 'home', 'active' => request()->routeIs('site.home')],
            ['label' => 'Tenders', 'url' => route('site.tenders'), 'icon' => 'tenders', 'active' => request()->routeIs('site.tenders', 'site.sector', 'site.province', 'site.tenders.show', 'site.sectors', 'site.provinces')],
        ];
        if (Modules::inPublicNav('directory')) {
            $tabs[] = ['label' => 'Companies', 'url' => route('site.companies'), 'icon' => 'companies', 'active' => request()->routeIs('site.companies', 'site.companies.show')];
        }
        if (Modules::inPublicNav('products')) {
            $tabs[] = ['label' => 'Products', 'url' => route('site.products'), 'icon' => 'products', 'active' => request()->routeIs('site.products', 'site.products.show')];
        }
        $tabs[] = ['label' => $user ? 'Account' : 'Menu', 'url' => null, 'icon' => $user ? 'user' : 'menu', 'active' => false, 'badge' => $user ? self::unread($user) : 0];

        $explore = [
            ['label' => 'Tenders by sector', 'url' => route('site.sectors'), 'icon' => 'grid'],
            ['label' => 'Tenders by province', 'url' => route('site.provinces'), 'icon' => 'map'],
            ['label' => 'Statistics', 'url' => route('site.statistics'), 'icon' => 'chart'],
            ['label' => 'Pricing', 'url' => route('site.pricing'), 'icon' => 'card'],
        ];
        $help = [
            ['label' => 'How it works', 'url' => route('site.how'), 'icon' => 'info'],
            ['label' => 'Questions', 'url' => route('site.faq'), 'icon' => 'help'],
            ['label' => 'Write to us', 'url' => route('site.contact'), 'icon' => 'mail'],
        ];

        $sheet = $user ? ['Your account' => self::account($user)] : ['Your account' => self::guest()];

        return ['tabs' => $tabs, 'sheet' => $sheet + ['Explore' => $explore, 'Help' => $help]];
    }

    /** The panel of a company (layouts/app). */
    public static function panel(User $user): array
    {
        $company = $user->company;
        $tabs = [
            ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'home', 'active' => request()->routeIs('dashboard')],
            ['label' => 'Tenders', 'url' => route('tenders.index'), 'icon' => 'tenders', 'active' => request()->routeIs('tenders.*')],
        ];
        if ($company?->is_buyer && ! $company?->is_supplier) {
            $tabs[] = ['label' => 'Posted', 'url' => route('posted.index'), 'icon' => 'megaphone', 'active' => request()->routeIs('posted.*')];
        } elseif (Modules::inDashboardNav('products')) {
            $tabs[] = ['label' => 'Products', 'url' => route('products.index'), 'icon' => 'products', 'active' => request()->routeIs('products.*')];
        }
        $tabs[] = ['label' => 'Messages', 'url' => route('messages.show'), 'icon' => 'mail', 'active' => request()->routeIs('messages.*'),
            'badge' => DirectMessage::where('user_id', $user->id)->where('from_admin', true)->whereNull('read_at')->count()];
        $tabs[] = ['label' => 'Menu', 'url' => null, 'icon' => 'menu', 'active' => false];

        // the whole menu of the panel, by its headings
        $sheet = [];
        $heading = 'Your company';
        foreach (Menu::for($user) as $item) {
            if (isset($item['heading'])) {
                $heading = $item['heading'];

                continue;
            }
            foreach ($item['children'] ?? [$item] as $link) {
                $sheet[$heading][] = ['label' => $link['label'], 'url' => Menu::url($link), 'icon' => self::iconFor($link['icon'] ?? ''), 'badge' => Menu::badge($link), 'active' => ! empty($link['current'])];
            }
        }
        $sheet['Account'] = array_values(array_filter([
            ['label' => 'Notifications', 'url' => route('notices.index'), 'icon' => 'bell', 'badge' => self::unread($user)],
            ['label' => 'My profile', 'url' => route('profile.edit'), 'icon' => 'user'],
            ['label' => 'Change password', 'url' => route('profile.password'), 'icon' => 'key'],
            $company?->is_listed && Modules::enabled('directory') ? ['label' => 'Our public page', 'url' => route('site.companies.show', ['slug' => $company->slug]), 'icon' => 'eye'] : null,
            ['label' => 'Website home', 'url' => route('site.home'), 'icon' => 'globe'],
            ['label' => 'Sign out', 'url' => null, 'icon' => 'out', 'form' => 'logoutForm'],
        ]));

        return ['tabs' => $tabs, 'sheet' => $sheet];
    }

    private static function guest(): array
    {
        $signup = Platform::bool('registration');

        return array_values(array_filter([
            ['label' => 'Sign in', 'url' => route('login'), 'icon' => 'user'],
            $signup ? ['label' => 'Register free', 'url' => route('register'), 'icon' => 'plus', 'primary' => true] : null,
            ['label' => 'Post a tender', 'url' => $signup ? route('register', ['as' => 'buyer']) : route('site.contact'), 'icon' => 'megaphone'],
        ]));
    }

    private static function account(User $user): array
    {
        $company = $user->company;

        return array_values(array_filter([
            ['label' => 'My dashboard', 'url' => route('dashboard'), 'icon' => 'home', 'primary' => true],
            ['label' => 'Notifications', 'url' => route('notices.index'), 'icon' => 'bell', 'badge' => self::unread($user)],
            ['label' => 'Messages', 'url' => route('messages.show'), 'icon' => 'mail'],
            $company?->is_supplier ? ['label' => 'Saved tenders', 'url' => route('tenders.saved'), 'icon' => 'bookmark'] : null,
            $user->isOwner() ? ['label' => 'Company profile', 'url' => route('settings.edit'), 'icon' => 'companies'] : null,
            Modules::inDashboardNav('products') ? ['label' => 'My products', 'url' => route('products.index'), 'icon' => 'products'] : null,
            ['label' => 'Subscription', 'url' => route('billing.show'), 'icon' => 'card'],
            ['label' => 'Sign out', 'url' => null, 'icon' => 'out', 'form' => 'mnavLogout'],
        ]));
    }

    private static function unread(User $user): int
    {
        return Notice::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    /** The bar's own small icon set, from the Font Awesome names used in config/menu.php. */
    private static function iconFor(string $name): string
    {
        return match ($name) {
            'gauge-high', 'gauge' => 'home',
            'gavel' => 'tenders',
            'bell' => 'bell',
            'bookmark' => 'bookmark',
            'bullhorn' => 'megaphone',
            'plus' => 'plus',
            'boxes-stacked' => 'products',
            'building' => 'companies',
            'paper-plane' => 'send',
            'shield-halved' => 'shield',
            'user-group' => 'team',
            'credit-card' => 'card',
            'envelope' => 'mail',
            'briefcase', 'folder-open' => 'folder',
            'file-pdf' => 'file',
            default => 'dot',
        };
    }
}
