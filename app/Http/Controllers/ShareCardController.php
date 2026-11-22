<?php

namespace App\Http\Controllers;

use App\Models\Disease;
use App\Models\OrgUnit;
use App\Services\AggregateQuery;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Shareable summary card: national totals only. */
class ShareCardController extends Controller
{
    public function page(AggregateQuery $q): View
    {
        return view('public.share', ['week' => $q->latestWeek()]);
    }

    public function png(AggregateQuery $q): Response
    {
        $country = OrgUnit::where('type', 'country')->first();
        $week = $q->latestWeek();
        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $bold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $img = imagecreatetruecolor(1200, 630);
        $bg = imagecolorallocate($img, 27, 36, 48);
        $white = imagecolorallocate($img, 255, 255, 255);
        $muted = imagecolorallocate($img, 190, 200, 212);
        $accent = imagecolorallocate($img, 255, 191, 71);
        imagefilledrectangle($img, 0, 0, 1200, 630, $bg);
        imagefilledrectangle($img, 0, 0, 14, 630, $accent);
        $text = function (string $t, int $size, int $x, int $y, $color, bool $b = false) use ($img, $font, $bold) {
            if (is_file($font) && function_exists('imagettftext')) {
                imagettftext($img, $size, 0, $x, $y, $color, $b ? $bold : $font, $t);
            } else {
                imagestring($img, 5, $x, $y - 14, $t, $color);
            }
        };
        $text('District Outbreak Watch', 40, 60, 90, $white, true);
        $text('National situation, week starting '.($week?->format('d M Y') ?? 'n/a'), 24, 60, 140, $muted);
        $y = 220;
        if ($country && $week) {
            foreach (Disease::orderBy('priority')->orderBy('name')->get() as $d) {
                $cases = (int) ($q->series($country, $d->id, $week, $week)[$week->toDateString()] ?? 0);
                $text($d->name, 28, 60, $y, $white);
                $text(number_format($cases).' cases', 28, 760, $y, $accent, true);
                $y += 62;
            }
        }
        $text('National totals from sentinel facilities'.(config('surveillance.synthetic_data') ? ' (sample data)' : '').'. No case-level data.', 18, 60, 595, $muted);
        ob_start();
        imagepng($img);
        $data = ob_get_clean();

        return response($data, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=900']);
    }
}
