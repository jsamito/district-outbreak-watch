<?php

namespace Database\Factories;

use App\Models\OrgUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgUnit>
 */
class OrgUnitFactory extends Factory
{
    protected $model = OrgUnit::class;

    public function definition(): array
    {
        $code = 'T'.fake()->unique()->numerify('######');

        return [
            'level' => 1,
            'type' => OrgUnit::COUNTRY,
            'name' => fake()->unique()->city(),
            'code' => $code,
            'path' => $code,
            'active' => true,
        ];
    }

    public function country(string $code = 'UG', string $name = 'Uganda'): static
    {
        return $this->state(['level' => 1, 'type' => OrgUnit::COUNTRY, 'code' => $code, 'path' => $code, 'name' => $name, 'parent_id' => null]);
    }

    public function childOf(OrgUnit $parent, string $type): static
    {
        $code = $parent->code.fake()->unique()->numerify('###');
        $level = $parent->level + 1;

        return $this->state([
            'parent_id' => $parent->id,
            'level' => $level,
            'type' => $type,
            'code' => $code,
            'path' => $parent->path.'/'.$code,
        ]);
    }
}
