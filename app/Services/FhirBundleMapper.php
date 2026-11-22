<?php

namespace App\Services;

use App\Models\Disease;

/**
 * Maps a small FHIR R4 Bundle (Patient, Condition, Encounter), informed by the eCR case-reporting model,
 * to the internal notification payload. Only the elements the surveillance system needs are read.
 */
class FhirBundleMapper
{
    private const VERIFICATION = ['confirmed' => 'confirmed', 'provisional' => 'probable', 'differential' => 'probable', 'unconfirmed' => 'suspected', 'refuted' => 'discarded', 'entered-in-error' => 'discarded'];

    /** @return array<string,mixed> */
    public function map(array $bundle): array
    {
        $res = ['Patient' => [], 'Condition' => [], 'Encounter' => []];
        foreach ($bundle['entry'] ?? [] as $entry) {
            $r = $entry['resource'] ?? [];
            if (isset($r['resourceType'], $res[$r['resourceType']])) {
                $res[$r['resourceType']] = $r;
            }
        }
        $patient = $res['Patient'];
        $condition = $res['Condition'];
        $encounter = $res['Encounter'];

        $icd = null;
        foreach ($condition['code']['coding'] ?? [] as $c) {
            if (str_contains($c['system'] ?? '', 'icd-10') || str_contains($c['system'] ?? '', 'icd10')) {
                $icd = $c['code'] ?? null;
            }
        }
        $disease = $icd ? Disease::get()->first(fn (Disease $d) => str_starts_with(strtoupper($icd), strtoupper(explode('-', $d->icd10_code)[0]))) : null;
        $verification = $condition['verificationStatus']['coding'][0]['code'] ?? 'unconfirmed';
        $class = $encounter['class']['code'] ?? 'AMB';

        return array_filter([
            'disease' => $disease?->code,
            'facility_code' => $encounter['serviceProvider']['identifier']['value'] ?? ($encounter['serviceProvider']['reference'] ?? null),
            'sex' => match ($patient['gender'] ?? null) { 'female' => 'F', 'male' => 'M', default => 'U' },
            'birth_year' => isset($patient['birthDate']) ? (int) substr($patient['birthDate'], 0, 4) : null,
            'onset_date' => isset($condition['onsetDateTime']) ? substr($condition['onsetDateTime'], 0, 10) : null,
            'notification_date' => isset($condition['recordedDate']) ? substr($condition['recordedDate'], 0, 10) : null,
            'classification' => self::VERIFICATION[$verification] ?? 'suspected',
            'hospitalised' => in_array($class, ['IMP', 'ACUTE'], true),
        ], fn ($v) => $v !== null);
    }
}
