<?php

namespace App\Services;

use App\Models\Antigen;
use App\Models\Disease;
use App\Support\SampleRandom;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Expands weekly facility reports for case-reported diseases into synthetic case records so that the
 * number of cases per facility, disease and week always equals the aggregate. All people and cases are
 * flagged as synthetic. The distributions below are stated assumptions, not clinical truth.
 */
class SyntheticCaseGenerator
{
    /** age buckets [min, max, weight] */
    private const AGES = [
        'measles' => [[0, 0, 0.12], [1, 4, 0.5], [5, 14, 0.3], [15, 40, 0.08]],
        'cholera' => [[0, 4, 0.15], [5, 14, 0.2], [15, 44, 0.5], [45, 80, 0.15]],
        'typhoid' => [[0, 4, 0.05], [5, 14, 0.3], [15, 24, 0.3], [25, 44, 0.25], [45, 75, 0.1]],
        'ebola' => [[0, 14, 0.1], [15, 44, 0.7], [45, 70, 0.2]],
    ];
    /** [confirmed, probable] probabilities, remainder suspected */
    private const CLASSIFY = ['measles' => [0.35, 0.15], 'cholera' => [0.3, 0.1], 'typhoid' => [0.4, 0.1], 'ebola' => [0.5, 0.2]];
    private const HOSPITALISED = ['measles' => 0.25, 'cholera' => 0.5, 'typhoid' => 0.35, 'ebola' => 1.0];
    private const LAG = ['measles' => [1, 5], 'cholera' => [0, 3], 'typhoid' => [2, 8], 'ebola' => [2, 7]];
    private const TEST = ['measles' => ['serum', 'MEASLES-IGM'], 'cholera' => ['stool', 'V-CHOLERAE-CULTURE'], 'typhoid' => ['blood', 'BLOOD-CULTURE'], 'ebola' => ['blood', 'EBOV-PCR']];

    private SampleRandom $rng;

    public function generate(int $weeks = 26, int $seed = 20261122, ?callable $progress = null): int
    {
        $this->rng = new SampleRandom($seed);
        $diseases = Disease::where('case_based', true)->get()->keyBy('id');
        $mcv1 = Antigen::where('code', 'MCV1')->value('id');
        $latest = DB::table('facility_reports')->max('week_start');
        if (! $latest) {
            return 0;
        }
        $from = CarbonImmutable::parse($latest)->subWeeks($weeks - 1)->toDateString();
        $today = CarbonImmutable::today();

        $this->wipe();
        $districtOf = DB::table('org_units')->where('type', 'facility')->pluck('parent_id', 'id');

        $reports = DB::table('facility_reports')->whereIn('disease_id', $diseases->keys())->where('week_start', '>=', $from)->where('cases', '>', 0)
            ->orderBy('week_start')->orderBy('org_unit_id')->get(['org_unit_id', 'disease_id', 'week_start', 'cases', 'deaths']);

        $buffer = [];
        $total = 0;
        foreach ($reports as $r) {
            $d = $diseases[$r->disease_id];
            for ($i = 0; $i < $r->cases; $i++) {
                $buffer[] = $this->spec($d, $r, $i < $r->deaths, $today, $districtOf[$r->org_unit_id] ?? $r->org_unit_id, $mcv1);
            }
            if (count($buffer) >= 1500) {
                $total += $this->flush($buffer);
                if ($progress) {
                    $progress($total);
                }
            }
        }
        $total += $this->flush($buffer);

        return $total;
    }

    private function wipe(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['case_events', 'lab_results', 'diagnoses', 'encounters', 'vaccinations', 'cases', 'persons'] as $t) {
            DB::table($t)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function spec($disease, $r, bool $died, CarbonImmutable $today, int $districtId, ?int $mcv1): array
    {
        $code = $disease->code;
        $week = CarbonImmutable::parse($r->week_start);
        $notified = $week->addDays((int) floor($this->rng->uniform() * 7));
        if ($notified->greaterThan($today)) {
            $notified = $today;
        }
        [$lagMin, $lagMax] = self::LAG[$code];
        $onset = $notified->subDays($lagMin + (int) floor($this->rng->uniform() * ($lagMax - $lagMin + 1)));
        $age = $this->age($code);
        $sex = $this->rng->uniform() < 0.5 ? 'F' : 'M';
        [$pc, $pp] = self::CLASSIFY[$code];
        $u = $this->rng->uniform();
        $class = $u < $pc ? 'confirmed' : ($u < $pc + $pp ? 'probable' : 'suspected');
        $investigated = $class !== 'suspected' ? $notified->addDays(1 + (int) floor($this->rng->uniform() * 3)) : null;
        if ($investigated && $investigated->greaterThan($today)) {
            $investigated = null;
        }
        $old = $notified->diffInDays($today) > 28;
        $outcome = $died ? 'died' : ($old ? 'recovered' : 'unknown');
        $status = $investigated ? ($old ? 'closed' : 'investigating') : ($old ? 'closed' : 'reported');
        $hospitalised = $died || $this->rng->uniform() < self::HOSPITALISED[$code];
        $lab = null;
        if ($class === 'confirmed') {
            $lab = 'positive';
        } elseif (($class === 'probable' && $this->rng->uniform() < 0.4) || ($class === 'suspected' && $this->rng->uniform() < 0.1)) {
            $lab = 'pending';
        }
        $vacc = null;
        if ($code === 'measles' && $mcv1 && $age >= 1 && $this->rng->uniform() < 0.7) {
            $vacc = CarbonImmutable::create($notified->year - $age, 1, 1)->addDays(380 + (int) floor($this->rng->uniform() * 200));
            $vacc = $vacc->greaterThan($notified) ? $notified->subYear() : $vacc;
        }

        return compact('disease', 'r', 'notified', 'onset', 'age', 'sex', 'class', 'investigated', 'outcome', 'status', 'hospitalised', 'lab', 'vacc', 'districtId', 'mcv1');
    }

    private function age(string $code): int
    {
        $u = $this->rng->uniform();
        $acc = 0;
        foreach (self::AGES[$code] as [$min, $max, $w]) {
            $acc += $w;
            if ($u <= $acc) {
                return $min + (int) floor($this->rng->uniform() * ($max - $min + 1));
            }
        }

        return 20;
    }

    /** Inserts a batch of specs as persons, cases, encounters, diagnoses, labs, vaccinations and events. @return int cases written */
    private function flush(array &$specs): int
    {
        if (! $specs) {
            return 0;
        }
        $now = now();
        $persons = [];
        foreach ($specs as $i => $s) {
            $specs[$i]['uuid'] = (string) Str::uuid();
            $persons[] = ['pseudonym' => $specs[$i]['uuid'], 'sex' => $s['sex'], 'birth_year' => $s['notified']->year - $s['age'], 'residence_org_unit_id' => $s['districtId'], 'is_synthetic' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        $startId = (int) DB::table('persons')->max('id');
        DB::table('persons')->insert($persons);
        $ids = DB::table('persons')->where('id', '>', $startId)->pluck('id', 'pseudonym');

        $cases = [];
        foreach ($specs as $s) {
            $cases[] = [
                'person_id' => $ids[$s['uuid']], 'disease_id' => $s['disease']->id, 'reporting_org_unit_id' => $s['r']->org_unit_id, 'residence_org_unit_id' => $s['districtId'],
                'onset_date' => $s['onset']->toDateString(), 'notification_date' => $s['notified']->toDateString(), 'investigation_date' => $s['investigated']?->toDateString(),
                'classification' => $s['class'], 'outcome' => $s['outcome'], 'hospitalised' => $s['hospitalised'], 'icd10_code' => $s['disease']->icd10_code, 'status' => $s['status'],
                'epi_week' => $s['r']->week_start, 'is_synthetic' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $startCase = (int) DB::table('cases')->max('id');
        DB::table('cases')->insert($cases);
        $caseIds = DB::table('cases')->where('id', '>', $startCase)->orderBy('id')->pluck('id', 'person_id');

        $enc = $labs = $vax = $events = [];
        foreach ($specs as $s) {
            $pid = $ids[$s['uuid']];
            $cid = $caseIds[$pid];
            $enc[] = ['person_id' => $pid, 'case_id' => $cid, 'org_unit_id' => $s['r']->org_unit_id, 'encountered_at' => $s['notified']->setTime(8 + (int) floor($this->rng->uniform() * 8), 0), 'type' => $s['hospitalised'] ? 'IPD' : 'OPD', 'created_at' => $now, 'updated_at' => $now];
            if ($s['lab']) {
                [$specimen, $test] = self::TEST[$s['disease']->code];
                $labs[] = ['case_id' => $cid, 'specimen_type' => $specimen, 'test_code' => $test, 'collected_at' => $s['notified']->toDateString(), 'resulted_at' => $s['lab'] === 'positive' ? $s['notified']->addDays(2 + (int) floor($this->rng->uniform() * 4))->toDateString() : null, 'result' => $s['lab'], 'created_at' => $now, 'updated_at' => $now];
            }
            if ($s['vacc']) {
                $vax[] = ['person_id' => $pid, 'antigen_id' => $s['mcv1'], 'dose_number' => 1, 'administered_on' => $s['vacc']->toDateString(), 'org_unit_id' => $s['districtId']];
            }
            $events[] = ['case_id' => $cid, 'type' => 'notified', 'data' => json_encode(['facility_id' => $s['r']->org_unit_id]), 'user_id' => null, 'occurred_at' => $s['notified']->setTime(9, 0)];
            if ($s['class'] !== 'suspected') {
                $events[] = ['case_id' => $cid, 'type' => 'classified', 'data' => json_encode(['classification' => $s['class']]), 'user_id' => null, 'occurred_at' => ($s['investigated'] ?? $s['notified'])->setTime(11, 0)];
            }
            if ($s['outcome'] === 'died') {
                $events[] = ['case_id' => $cid, 'type' => 'outcome', 'data' => json_encode(['outcome' => 'died']), 'user_id' => null, 'occurred_at' => $s['notified']->addDays(2)->setTime(10, 0)];
            }
        }
        $startEnc = (int) DB::table('encounters')->max('id');
        DB::table('encounters')->insert($enc);
        $encIds = DB::table('encounters')->where('id', '>', $startEnc)->pluck('id', 'case_id');
        $dx = [];
        foreach ($specs as $s) {
            $dx[] = ['encounter_id' => $encIds[$caseIds[$ids[$s['uuid']]]], 'icd10_code' => $s['disease']->icd10_code, 'is_primary' => true];
        }
        foreach ([['diagnoses', $dx], ['lab_results', $labs], ['vaccinations', $vax], ['case_events', $events]] as [$table, $rows]) {
            foreach (array_chunk($rows, 2000) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        $n = count($specs);
        $specs = [];

        return $n;
    }
}
