<?php

namespace App\Support;

use App\Models\Company;
use App\Models\ModuleChange;
use InvalidArgumentException;

/**
 * Which parts of the platform are switched on (config/modules.php, Admin > Module Management).
 *
 * The switches live in the settings table, read once per request through App\Support\Platform
 * and read again after a change. Switching a module off hides it and blocks its pages; it never
 * deletes what was saved in it.
 */
class Modules
{
    /** @return array<string, array> every module with its definition */
    public static function all(): array
    {
        $modules = [];
        foreach (config('modules', []) as $key => $definition) {
            $modules[$key] = $definition + [
                'key' => $key,
                'default' => false,
                'locked' => false,
                'depends' => [],
                'built' => true,
                'setting' => 'module_'.$key,
                'public_nav' => false,
                'dashboard_nav' => false,
                'notifications' => false,
            ];
        }

        return $modules;
    }

    public static function definition(string $key): array
    {
        $all = self::all();
        if (! isset($all[$key])) {
            throw new InvalidArgumentException("Unknown module [$key].");
        }

        return $all[$key];
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /** The module's own switch, without looking at the modules it depends on. */
    public static function switchedOn(string $key): bool
    {
        $module = self::definition($key);
        if (! $module['built']) {
            return false;
        }
        if ($module['locked']) {
            return true;
        }
        $saved = Platform::all()[$module['setting']] ?? null;

        return $saved === null || $saved === '' ? (bool) $module['default'] : filter_var($saved, FILTER_VALIDATE_BOOL);
    }

    /** Is the module working now: its own switch is on and so is every module it depends on. */
    public static function enabled(string $key, array $seen = []): bool
    {
        if (in_array($key, $seen, true) || ! self::switchedOn($key)) {
            return false;
        }
        foreach (self::definition($key)['depends'] as $parent) {
            if (! self::enabled($parent, [...$seen, $key])) {
                return false;
            }
        }

        return true;
    }

    /** The modules this one waits for that are off now (to explain why it is off). */
    public static function blockedBy(string $key): array
    {
        return array_values(array_filter(self::definition($key)['depends'], fn (string $parent) => ! self::enabled($parent)));
    }

    /** The modules that stop working when this one is switched off. */
    public static function dependents(string $key): array
    {
        $found = [];
        foreach (self::all() as $other => $module) {
            if (in_array($key, $module['depends'], true)) {
                $found[] = $other;
                foreach (self::dependents($other) as $deeper) {
                    $found[] = $deeper;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /** One setting of a module: public_nav, dashboard_nav, notifications or access. */
    public static function option(string $key, string $option): mixed
    {
        $module = self::definition($key);
        $saved = Platform::all()['module_'.$key.'_'.$option] ?? null;
        $default = $option === 'access' ? 'all' : ($module[$option] ?? false);
        if ($saved === null || $saved === '') {
            return $default;
        }

        return $option === 'access' ? ($saved === 'paid' ? 'paid' : 'all') : filter_var($saved, FILTER_VALIDATE_BOOL);
    }

    /** Show the module's links in the website's menus? */
    public static function inPublicNav(string $key): bool
    {
        return self::enabled($key) && (bool) self::option($key, 'public_nav');
    }

    /** Show the module's links in the menu of a company's panel? */
    public static function inDashboardNav(string $key): bool
    {
        return self::enabled($key) && (bool) self::option($key, 'dashboard_nav');
    }

    /** May the module send notifications (emails, bells)? */
    public static function notifies(string $key): bool
    {
        return self::enabled($key) && (bool) self::option($key, 'notifications');
    }

    /** May this company use the module? It must be on, and some modules are for paid plans only. */
    public static function allows(string $key, ?Company $company): bool
    {
        if (! self::enabled($key)) {
            return false;
        }
        if (self::option($key, 'access') !== 'paid') {
            return true;
        }

        return $company !== null && $company->isUsable() && $company->plan !== null && ! $company->plan->isFree();
    }

    /**
     * Save the switches and options of one module and write what changed to its history.
     *
     * @param  array{enabled?: bool, public_nav?: bool, dashboard_nav?: bool, notifications?: bool, access?: string}  $values
     * @return array<string, array{0: mixed, 1: mixed}> what changed: option => [before, after]
     */
    public static function save(string $key, array $values, ?string $note = null): array
    {
        $module = self::definition($key);
        $before = self::state($key);
        $settings = [];

        if (array_key_exists('enabled', $values) && $module['built'] && ! $module['locked']) {
            $settings[$module['setting']] = $values['enabled'] ? '1' : '0';
        }
        foreach (['public_nav', 'dashboard_nav', 'notifications'] as $option) {
            if (array_key_exists($option, $values)) {
                $settings['module_'.$key.'_'.$option] = $values[$option] ? '1' : '0';
            }
        }
        if (array_key_exists('access', $values)) {
            $settings['module_'.$key.'_access'] = $values['access'] === 'paid' ? 'paid' : 'all';
        }
        if ($settings) {
            Platform::set($settings);
        }

        $after = self::state($key);
        $changes = [];
        foreach ($after as $option => $value) {
            if ($before[$option] !== $value) {
                $changes[$option] = [$before[$option], $value];
            }
        }
        if ($changes) {
            $admin = auth('admin')->user();
            ModuleChange::create([
                'module' => $key,
                'admin_id' => $admin?->id,
                'admin_name' => $admin?->name,
                'changes' => $changes,
                'note' => $note ? mb_substr($note, 0, 500) : null,
                'ip' => request()?->ip(),
            ]);
        }

        return $changes;
    }

    /** The saved state of a module, as compared in its history. */
    public static function state(string $key): array
    {
        return [
            'enabled' => self::switchedOn($key),
            'public_nav' => (bool) self::option($key, 'public_nav'),
            'dashboard_nav' => (bool) self::option($key, 'dashboard_nav'),
            'notifications' => (bool) self::option($key, 'notifications'),
            'access' => self::option($key, 'access'),
        ];
    }
}
