<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'roots' => $user->orgUnits()->orderBy('level')->orderBy('name')->get(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
        ]);
    }
}
