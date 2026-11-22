<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A named set of filters on one screen. Dates are kept only when the user asks, so a view stays current. */
class SavedView extends Model
{
    public const MAX_PER_USER = 20;

    public const ROUTES = [
        'trends' => 'Trends', 'map' => 'Map and comparison', 'quality' => 'Quality control chart', 'vaccination' => 'Vaccination',
        'reporting' => 'Reporting', 'forecasts' => 'Forecasts', 'cases.analytics' => 'Case analytics',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    public function url(): string
    {
        return route($this->route, array_filter($this->filters ?? [], fn ($v) => $v !== null && $v !== ''));
    }
}
