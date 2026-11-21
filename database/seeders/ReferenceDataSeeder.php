<?php

namespace Database\Seeders;

use App\Models\Antigen;
use App\Models\DataSource;
use App\Models\Disease;
use App\Models\Indicator;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $diseases = [
            ['code' => 'malaria', 'name' => 'Malaria', 'icd10_code' => 'B50-B54', 'priority' => 1, 'case_based' => false, 'immediate_notifiable' => false,
                'case_definition' => 'Fever with a positive rapid diagnostic test or microscopy for malaria parasites.'],
            ['code' => 'measles', 'name' => 'Measles', 'icd10_code' => 'B05', 'priority' => 1, 'case_based' => true, 'immediate_notifiable' => false,
                'case_definition' => 'Fever and maculopapular rash with cough, coryza or conjunctivitis; or any person a clinician suspects has measles.'],
            ['code' => 'cholera', 'name' => 'Cholera', 'icd10_code' => 'A00', 'priority' => 1, 'case_based' => true, 'immediate_notifiable' => false,
                'case_definition' => 'Acute watery diarrhoea with or without vomiting in a person aged 2 years or older, or any person with a positive Vibrio cholerae culture.'],
            ['code' => 'typhoid', 'name' => 'Typhoid fever', 'icd10_code' => 'A01.0', 'priority' => 2, 'case_based' => true, 'immediate_notifiable' => false,
                'case_definition' => 'Fever for 3 or more days with headache, abdominal pain or constipation or diarrhoea; confirmed by blood culture.'],
            ['code' => 'ebola', 'name' => 'Ebola virus disease', 'icd10_code' => 'A98.4', 'priority' => 1, 'case_based' => true, 'immediate_notifiable' => true,
                'case_definition' => 'Acute fever with bleeding, or contact with a confirmed case, or unexplained death after fever. Any suspected case is notified immediately.'],
        ];
        foreach ($diseases as $d) {
            Disease::updateOrCreate(['code' => $d['code']], $d);
        }

        foreach ([
            ['code' => 'BCG', 'name' => 'BCG', 'doses_required' => 1, 'target_percent' => 90],
            ['code' => 'PENTA3', 'name' => 'Pentavalent (DPT-HepB-Hib) dose 3', 'doses_required' => 3, 'target_percent' => 90],
            ['code' => 'OPV3', 'name' => 'Oral polio vaccine dose 3', 'doses_required' => 3, 'target_percent' => 90],
            ['code' => 'MCV1', 'name' => 'Measles-containing vaccine dose 1', 'doses_required' => 1, 'target_percent' => 95],
        ] as $a) {
            Antigen::updateOrCreate(['code' => $a['code']], $a);
        }

        foreach ([
            ['code' => Indicator::CASES, 'name' => 'Cases', 'kind' => 'count', 'unit' => 'cases per week', 'direction' => 'lower_is_better',
                'definition' => 'Suspected, probable and confirmed cases reported by sentinel facilities, per disease, area and ISO week (Monday start). Discarded cases are excluded.'],
            ['code' => Indicator::REPORTING, 'name' => 'Reporting completeness', 'kind' => 'proportion', 'unit' => '%', 'direction' => 'higher_is_better', 'target' => 80,
                'definition' => 'Weekly reports received from sentinel facilities divided by reports expected. Timely means received by the Wednesday after the week ends.'],
            ['code' => Indicator::VACCINATION, 'name' => 'Vaccination coverage', 'kind' => 'proportion', 'unit' => '%', 'direction' => 'higher_is_better', 'target' => 90,
                'definition' => 'Doses given in the period divided by (population under 1 year x months in period / 12) x 100, per antigen and area. Values above 100% are flagged as a data-quality issue.'],
        ] as $i) {
            Indicator::updateOrCreate(['code' => $i['code']], $i);
        }

        $accessed = '2026-11-22';
        foreach ([
            ['code' => 'hdx-cod-ab-uga', 'name' => 'Uganda subnational administrative boundaries (COD-AB)', 'url' => 'https://data.humdata.org/dataset/cod-ab-uga',
                'licence' => 'CC BY-IGO', 'citation' => 'OCHA / Uganda Bureau of Statistics via the Humanitarian Data Exchange; dataset updated 24 Sep 2026.', 'synthetic' => false],
            ['code' => 'hdx-cod-ps-uga', 'name' => 'Uganda subnational population statistics (COD-PS)', 'url' => 'https://data.humdata.org/dataset/cod-ps-uga',
                'licence' => 'CC BY-IGO', 'citation' => 'Uganda Bureau of Statistics projections supported by UNFPA, P-coded by OCHA, via HDX. District totals and age groups; base year 2022.',
                'terms_note' => 'One projected year per district. Other years are extrapolated with a flat 3% growth rate and flagged as projected. Under-1 population is approximated as one fifth of the 0-4 age group.', 'synthetic' => false],
            ['code' => 'healthsites-uga', 'name' => 'Uganda Healthsites (OpenStreetMap-derived facility locations)', 'url' => 'https://data.humdata.org/dataset/uganda-healthsites',
                'licence' => 'ODbL', 'citation' => 'Open Healthsite Consulting via HDX.',
                'terms_note' => 'Facility types are inferred from names and OpenStreetMap tags and are not the official health-centre levels.', 'synthetic' => false],
            ['code' => 'who-gho', 'name' => 'WHO Global Health Observatory: WUENIC immunization coverage estimates', 'url' => 'https://ghoapi.azureedge.net/api',
                'licence' => 'WHO terms apply (reuse terms to be confirmed before redistribution)', 'citation' => 'World Health Organization, Global Health Observatory OData API; indicators WHS8_110, WHS4_100, WHS4_543.', 'synthetic' => false],
            ['code' => 'wikimedia-pageviews', 'name' => 'Wikipedia page views (public attention)', 'url' => 'https://wikimedia.org/api/rest_v1/',
                'licence' => 'Public API (reuse terms to be confirmed)', 'citation' => 'Wikimedia Foundation, Pageviews REST API, English Wikipedia, user traffic.',
                'terms_note' => 'Global English page views, not specific to Uganda.', 'synthetic' => false],
            ['code' => 'sample-weekly-reports', 'name' => 'Sample weekly facility reports and immunization doses', 'url' => null,
                'licence' => 'Generated for this project', 'citation' => 'Generated by php artisan surveillance:load-sample-data from real district populations, with seasonality and outbreak scenarios. Not real surveillance data.',
                'terms_note' => 'No open weekly facility-level disease or district immunization series exists for Uganda. Replace with live reports from the national surveillance system when available.', 'synthetic' => true],
        ] as $s) {
            DataSource::updateOrCreate(['code' => $s['code']], $s + ['accessed_at' => $accessed]);
        }
    }
}
