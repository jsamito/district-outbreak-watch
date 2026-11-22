<?php

use App\Models\OrgUnit;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// Live dashboard updates: only people whose area covers the unit may listen.
Broadcast::channel('area.{id}', function ($user, $id) {
    $unit = OrgUnit::find($id);

    return $unit && $user->active && $user->can('view aggregates') && $user->canAccessOrgUnit($unit);
});
