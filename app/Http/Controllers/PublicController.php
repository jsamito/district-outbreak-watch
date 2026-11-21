<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicController extends Controller
{
    public function landing(): View
    {
        return view('public.landing');
    }
}
