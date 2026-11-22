<?php

return [
    // Shown as a data notice while the system holds synthetic test data. Set to false for production data.
    'synthetic_data' => env('SURVEILLANCE_SYNTHETIC_DATA', true),

    // Production hardening: redirect HTTP to HTTPS and send HSTS. Enabled automatically outside local development.
    'force_https' => env('SURVEILLANCE_FORCE_HTTPS', env('APP_ENV') === 'production'),
    'hsts' => env('SURVEILLANCE_HSTS', true),

    // Live dashboard updates over WebSockets (needs `php artisan reverb:start`). When off, pages poll a small JSON summary instead.
    'realtime' => env('SURVEILLANCE_REALTIME', false),
    'reverb' => ['host' => env('REVERB_HOST'), 'port' => (int) env('REVERB_PORT', 8080), 'scheme' => env('REVERB_SCHEME', 'http')],

    'country' => ['code' => 'UG', 'name' => 'Uganda'],

    // Open population data is one projected year per district; other years use a flat growth rate.
    'population_base_year' => 2022,
    'population_growth' => 0.03,
    'under1_share_of_under5' => 0.2,

    'history_weeks' => 104,
    'vaccination_months' => 60,
    'small_number_floor' => 5,

    // Sentinel facilities per district for weekly reporting.
    'sentinel_per_district' => 6,

    /*
     * Weekly sample-data model. Rates are weekly cases per 1,000 people in a facility's catchment.
     * seasonality: amplitude and peak month of a sinusoidal annual cycle. overdispersion: gamma shape (smaller = noisier).
     */
    'diseases' => [
        'malaria' => ['rate' => 2.2, 'amplitude' => 0.25, 'peak_month' => 5, 'overdispersion' => 20, 'cfr' => 0.0006],
        'measles' => ['rate' => 0.012, 'amplitude' => 0.5, 'peak_month' => 2, 'overdispersion' => 3, 'cfr' => 0.02],
        'cholera' => ['rate' => 0.003, 'amplitude' => 0.3, 'peak_month' => 11, 'overdispersion' => 2, 'cfr' => 0.015],
        'typhoid' => ['rate' => 0.05, 'amplitude' => 0.2, 'peak_month' => 10, 'overdispersion' => 6, 'cfr' => 0.004],
        'ebola' => ['rate' => 0.0, 'amplitude' => 0, 'peak_month' => 1, 'overdispersion' => 1, 'cfr' => 0.4],
    ],

    /*
     * Outbreak scenarios loaded with the sample data: disease, district name, weeks before the last
     * complete week when it starts, duration in weeks, peak multiplier on the baseline rate, absolute extra
     * cases per sentinel facility at the peak (used for diseases with a near-zero baseline).
     */
    'scenarios' => [
        ['disease' => 'cholera', 'district' => 'Kasese', 'start_weeks_ago' => 7, 'duration' => 9, 'multiplier' => 30, 'extra_peak' => 2.5],
        ['disease' => 'measles', 'district' => 'Moroto', 'start_weeks_ago' => 9, 'duration' => 10, 'multiplier' => 25, 'extra_peak' => 1.2],
        ['disease' => 'malaria', 'district' => 'Nakapiripirit', 'start_weeks_ago' => 6, 'duration' => 8, 'multiplier' => 2.6, 'extra_peak' => 0],
        ['disease' => 'typhoid', 'district' => 'Kampala', 'start_weeks_ago' => 5, 'duration' => 6, 'multiplier' => 6, 'extra_peak' => 0.8],
        ['disease' => 'ebola', 'district' => 'Mubende', 'start_weeks_ago' => 1, 'duration' => 1, 'multiplier' => 1, 'extra_peak' => 0.01, 'single_case' => true],
    ],
];
