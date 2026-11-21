<?php

namespace App\Services;

use App\Models\Indicator;
use Illuminate\Support\Facades\DB;

/**
 * Builds the stored indicator values (facility, district, region and country level)
 * from the weekly facility reports, reporting statuses and immunization doses.
 */
class IndicatorEngine
{
    /** @return array<string,int> rows written per indicator */
    public function rebuildAll(): array
    {
        return [
            Indicator::CASES => $this->rebuildCases(),
            Indicator::REPORTING => $this->rebuildReporting(),
            Indicator::VACCINATION => $this->rebuildVaccination(),
        ];
    }

    /** Recompute case counts for the given weeks (all weeks when null). */
    public function rebuildCases(?array $weeks = null): int
    {
        $id = Indicator::idFor(Indicator::CASES);
        $where = '';
        $bind = [];
        if ($weeks) {
            $where = ' AND fr.week_start IN ('.implode(',', array_fill(0, count($weeks), '?')).')';
            $bind = array_values($weeks);
            DB::table('indicator_values')->where('indicator_id', $id)->where('period_type', 'week')->whereIn('period_start', $weeks)->delete();
        } else {
            DB::table('indicator_values')->where('indicator_id', $id)->delete();
        }

        $levels = [
            'fr.org_unit_id' => '',
            'o1.parent_id' => 'JOIN org_units o1 ON o1.id = fr.org_unit_id',
            'o2.parent_id' => 'JOIN org_units o1 ON o1.id = fr.org_unit_id JOIN org_units o2 ON o2.id = o1.parent_id',
            'o3.parent_id' => 'JOIN org_units o1 ON o1.id = fr.org_unit_id JOIN org_units o2 ON o2.id = o1.parent_id JOIN org_units o3 ON o3.id = o2.parent_id',
        ];
        $total = 0;
        foreach ($levels as $unitExpr => $joins) {
            $total += DB::affectingStatement("
                INSERT INTO indicator_values (org_unit_id, indicator_id, dimension_id, period_type, period_start, numerator, denominator, value, computed_at)
                SELECT $unitExpr AS unit_id, ?, fr.disease_id, 'week', fr.week_start, SUM(fr.cases), NULL, SUM(fr.cases), NOW()
                FROM facility_reports fr $joins
                WHERE 1 = 1 $where
                GROUP BY unit_id, fr.disease_id, fr.week_start
            ", array_merge([$id], $bind));
        }

        return $total;
    }

    public function rebuildReporting(?array $weeks = null): int
    {
        $id = Indicator::idFor(Indicator::REPORTING);
        $where = '';
        $bind = [];
        if ($weeks) {
            $where = ' AND rs.week_start IN ('.implode(',', array_fill(0, count($weeks), '?')).')';
            $bind = array_values($weeks);
            DB::table('indicator_values')->where('indicator_id', $id)->where('period_type', 'week')->whereIn('period_start', $weeks)->delete();
        } else {
            DB::table('indicator_values')->where('indicator_id', $id)->delete();
        }

        $levels = [
            'rs.org_unit_id' => '',
            'o1.parent_id' => 'JOIN org_units o1 ON o1.id = rs.org_unit_id',
            'o2.parent_id' => 'JOIN org_units o1 ON o1.id = rs.org_unit_id JOIN org_units o2 ON o2.id = o1.parent_id',
            'o3.parent_id' => 'JOIN org_units o1 ON o1.id = rs.org_unit_id JOIN org_units o2 ON o2.id = o1.parent_id JOIN org_units o3 ON o3.id = o2.parent_id',
        ];
        $total = 0;
        foreach ($levels as $unitExpr => $joins) {
            $total += DB::affectingStatement("
                INSERT INTO indicator_values (org_unit_id, indicator_id, dimension_id, period_type, period_start, numerator, denominator, value, computed_at)
                SELECT $unitExpr AS unit_id, ?, 0, 'week', rs.week_start, SUM(rs.received), COUNT(*), 100 * SUM(rs.received) / COUNT(*), NOW()
                FROM reporting_statuses rs $joins
                WHERE 1 = 1 $where
                GROUP BY unit_id, rs.week_start
            ", array_merge([$id], $bind));
        }

        return $total;
    }

    public function rebuildVaccination(): int
    {
        $id = Indicator::idFor(Indicator::VACCINATION);
        DB::table('indicator_values')->where('indicator_id', $id)->delete();

        $levels = [
            'd.org_unit_id' => '',
            'o1.parent_id' => 'JOIN org_units o1 ON o1.id = d.org_unit_id',
            'o2.parent_id' => 'JOIN org_units o1 ON o1.id = d.org_unit_id JOIN org_units o2 ON o2.id = o1.parent_id',
        ];
        $total = 0;
        foreach ($levels as $unitExpr => $joins) {
            $total += DB::affectingStatement("
                INSERT INTO indicator_values (org_unit_id, indicator_id, dimension_id, period_type, period_start, numerator, denominator, value, computed_at)
                SELECT $unitExpr AS unit_id, ?, d.antigen_id, 'month', d.month, SUM(d.doses), SUM(d.target), 100 * SUM(d.doses) / NULLIF(SUM(d.target), 0), NOW()
                FROM immunization_doses d $joins
                GROUP BY unit_id, d.antigen_id, d.month
            ", [$id]);
        }

        return $total;
    }
}
