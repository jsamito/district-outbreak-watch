<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /**
     * Sidebar definition. Items appear only when their route exists and the
     * user holds at least one of the listed permissions.
     *
     * @return array<int, array{heading?: string, label?: string, route?: string, icon?: string, any?: array<int,string>}>
     */
    protected static function definition(): array
    {
        return [
            ['heading' => 'Monitoring'],
            ['label' => 'Overview', 'route' => 'dashboard', 'icon' => 'fa-tachometer', 'any' => ['view aggregates', 'view lab worklist']],
            ['label' => 'Trends', 'route' => 'trends', 'icon' => 'fa-line-chart', 'any' => ['view aggregates']],
            ['label' => 'Map and comparison', 'route' => 'map', 'icon' => 'fa-map-o', 'any' => ['view aggregates']],
            ['label' => 'Quality control chart', 'route' => 'quality', 'icon' => 'fa-bar-chart', 'any' => ['view aggregates']],
            ['label' => 'Vaccination', 'route' => 'vaccination', 'icon' => 'fa-medkit', 'any' => ['view aggregates']],
            ['label' => 'Reporting', 'route' => 'reporting', 'icon' => 'fa-check-square-o', 'any' => ['view aggregates']],
            ['label' => 'Case analytics', 'route' => 'cases.analytics', 'icon' => 'fa-pie-chart', 'any' => ['view cases', 'view cases pseudonymised']],
            ['label' => 'Data quality', 'route' => 'quality-flags.index', 'icon' => 'fa-flag-o', 'any' => ['view aggregates']],
            ['label' => 'Forecasts', 'route' => 'forecasts', 'icon' => 'fa-area-chart', 'any' => ['view forecasts']],
            ['label' => 'Public attention', 'route' => 'attention', 'icon' => 'fa-globe', 'any' => ['view aggregates']],
            ['heading' => 'Action'],
            ['label' => 'Alerts', 'route' => 'alerts.index', 'icon' => 'fa-bell-o', 'any' => ['view alerts'],
                'badge' => fn (User $u) => ($n = \App\Models\Alert::visibleTo($u)->where('status', 'open')->count()) ? ['count' => $n, 'class' => '', 'label' => 'open alerts'] : null],
            ['label' => 'Cases', 'route' => 'cases.index', 'icon' => 'fa-user-o', 'any' => ['view cases', 'view cases pseudonymised']],
            ['label' => 'Notify a case', 'route' => 'cases.create', 'icon' => 'fa-plus-square-o', 'any' => ['edit cases']],
            ['label' => 'Lab worklist', 'route' => 'lab.index', 'icon' => 'fa-flask', 'any' => ['view lab worklist']],
            ['label' => 'Export data', 'route' => 'exports.index', 'icon' => 'fa-download', 'any' => ['export aggregates']],
            ['heading' => 'Administration'],
            ['label' => 'Alert rules', 'route' => 'alert-rules.index', 'icon' => 'fa-sliders', 'any' => ['manage alert rules']],
            ['label' => 'Users', 'route' => 'admin.users', 'icon' => 'fa-users', 'any' => ['manage users']],
            ['label' => 'API clients', 'route' => 'admin.api-clients', 'icon' => 'fa-plug', 'any' => ['manage api clients']],
            ['label' => 'Data sources', 'route' => 'admin.sources', 'icon' => 'fa-database', 'any' => ['manage data sources']],
            ['label' => 'Audit log', 'route' => 'admin.audit', 'icon' => 'fa-history', 'any' => ['view audit log', 'view audit summary']],
        ];
    }

    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $items = [];
        $pendingHeading = null;
        foreach (static::definition() as $entry) {
            if (isset($entry['heading'])) {
                $pendingHeading = $entry;

                continue;
            }
            if (! Route::has($entry['route'])) {
                continue;
            }
            if (! collect($entry['any'])->contains(fn ($p) => $user->can($p))) {
                continue;
            }
            if ($pendingHeading) {
                $items[] = $pendingHeading;
                $pendingHeading = null;
            }
            $items[] = $entry;
        }

        return $items;
    }
}
