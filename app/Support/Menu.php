<?php

namespace App\Support;

use App\Models\User;

/**
 * Builds the sidebar from config/menu.php: leaves out what only the owner may open
 * and marks the ONE entry that is the page being shown.
 */
class Menu
{
    public static function for(User $user): array
    {
        $menu = [];
        foreach (config('menu') as $item) {
            if (isset($item['children'])) {
                $item['children'] = array_values(array_filter($item['children'], fn (array $child) => self::allowed($user, $child)));
                if ($item['children']) {
                    $menu[] = $item;
                }
            } elseif (isset($item['heading']) || self::allowed($user, $item)) {
                $menu[] = $item;
            }
        }

        // drop headings that have nothing under them
        $clean = [];
        foreach ($menu as $index => $item) {
            $next = $menu[$index + 1] ?? null;
            if (isset($item['heading']) && ($next === null || isset($next['heading']))) {
                continue;
            }
            $clean[] = $item + ['open' => false, 'current' => false];
        }

        return self::markCurrent($clean);
    }

    /** The number shown beside an entry that asks for one ('badge' => 'approvals'): things waiting, or 0. */
    public static function badge(array $item): int
    {
        return match ($item['badge'] ?? null) {
            'messages' => \App\Models\DirectMessage::where('user_id', auth('web')->id())->where('from_admin', true)->whereNull('read_at')->count(),
            'matching' => ($company = company()) && $company->is_supplier ? \App\Models\Tender::open()->matching($company)->count() : 0,
            default => 0,
        };
    }

    public static function url(array $item): string
    {
        // 'url' is a page outside the system (it opens in a new tab)
        return $item['url'] ?? route($item['route'], $item['params'] ?? []);
    }

    /**
     * Exactly one entry is the current page. Entries that share a route differ by their
     * parameters (type, view, group ...), so every such parameter must be the same as in
     * the address: "Sale Invoices" (type=invoice) is not current on "Delivery Notes"
     * (type=invoice, view=delivery). When no entry fits exactly (a detail page, for
     * example) only the module is opened and nothing is highlighted.
     */
    private static function markCurrent(array $menu): array
    {
        // for every route: the parameter names the menu uses to tell its entries apart.
        // Other things in the address (a search word, a filter) do not matter.
        $keys = [];
        foreach ($menu as $item) {
            foreach ($item['children'] ?? [$item] as $entry) {
                foreach (isset($entry['route']) ? array_keys($entry['params'] ?? []) : [] as $key) {
                    $keys[$entry['route']][$key] = true;
                }
            }
        }
        $address = fn (string $key) => (string) (request()->route($key) ?? request()->query($key) ?? '');

        $exact = null;
        $loose = null;
        foreach ($menu as $i => $item) {
            foreach ($item['children'] ?? [null] as $j => $child) {
                $entry = $child ?? $item;
                if (! isset($entry['route']) || ! request()->routeIs($entry['active'] ?? $entry['route'])) {
                    continue;
                }
                $loose ??= [$i, $j];
                $same = true;
                foreach (array_keys($keys[$entry['route']] ?? []) as $key) {
                    if ((string) ($entry['params'][$key] ?? '') !== $address($key)) {
                        $same = false;
                        break;
                    }
                }
                if ($same) {
                    $exact = [$i, $j];
                    break 2;
                }
            }
        }

        if ($exact) {
            [$i, $j] = $exact;
            $menu[$i]['open'] = true;
            if (isset($menu[$i]['children'])) {
                $menu[$i]['children'][$j]['current'] = true;
            } else {
                $menu[$i]['current'] = true;
            }
        } elseif ($loose) {
            $menu[$loose[0]]['open'] = true;
        }

        return $menu;
    }

    private static function allowed(User $user, array $item): bool
    {
        if (! empty($item['setting']) && ! Platform::bool($item['setting'])) {
            return false;
        }
        if (! empty($item['module']) && ! Modules::inDashboardNav($item['module'])) {
            return false;
        }
        $company = $user->company;
        if (! empty($item['supplier']) && ! $company?->is_supplier) {
            return false;
        }
        if (! empty($item['buyer']) && ! $company?->is_buyer) {
            return false;
        }

        return empty($item['owner']) || $user->isOwner();
    }
}
