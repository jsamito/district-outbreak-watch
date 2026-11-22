<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Models\Disease;
use App\Models\ImportBatch;
use App\Models\OrgUnit;
use App\Services\AggregateQuery;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function landing(AggregateQuery $q): View
    {
        return view('public.landing', $this->national($q));
    }

    public function situation(AggregateQuery $q): View
    {
        return view('public.situation', $this->national($q));
    }

    public function about(): View
    {
        return view('public.about', ['sources' => DataSource::orderBy('name')->get(), 'batches' => ImportBatch::with('source')->latest('finished_at')->limit(12)->get()]);
    }

    public function accessibility(): View
    {
        return view('public.accessibility');
    }

    public function privacy(): View
    {
        return view('public.privacy');
    }

    public function sitemap(): Response
    {
        $pages = [route('home'), route('situation'), route('about'), route('accessibility'), route('privacy')];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($pages as $p) {
            $xml .= '<url><loc>'.e($p).'</loc><lastmod>'.now()->toDateString().'</lastmod></url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $txt = "User-agent: *\nAllow: /\nAllow: /about-the-data\nAllow: /situation\nDisallow: /login\nDisallow: /dashboard\nDisallow: /trends\nDisallow: /map\nDisallow: /quality\nDisallow: /vaccination\nDisallow: /reporting\nDisallow: /data-quality\nDisallow: /attention\nDisallow: /forecasts\nDisallow: /alerts\nDisallow: /cases\nDisallow: /lab\nDisallow: /exports\nDisallow: /admin\nDisallow: /api\nDisallow: /fhir\nDisallow: /data\nDisallow: /live\nDisallow: /broadcasting\n\nSitemap: ".route('sitemap')."\n";

        return response($txt, 200, ['Content-Type' => 'text/plain']);
    }

    /** National totals only: no district, facility or case detail on public pages. */
    private function national(AggregateQuery $q): array
    {
        $country = OrgUnit::where('type', 'country')->first();
        $week = $q->latestWeek();
        $rows = [];
        if ($country && $week) {
            foreach (Disease::orderBy('priority')->orderBy('name')->get() as $d) {
                $s = $q->series($country, $d->id, $week->subWeek(), $week);
                $cur = $s[$week->toDateString()] ?? 0;
                $prev = $s[$week->subWeek()->toDateString()] ?? 0;
                $rows[] = (object) ['disease' => $d, 'cases' => (int) $cur, 'prev' => (int) $prev, 'delta' => $prev > 0 ? round(($cur - $prev) / $prev * 100) : null];
            }
        }

        return ['week' => $week, 'rows' => $rows, 'country' => $country];
    }
}
