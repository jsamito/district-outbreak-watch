<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    public const CASES = 'cases';
    public const REPORTING = 'reporting_completeness';
    public const VACCINATION = 'vaccination_coverage';

    protected $guarded = [];

    public static function idFor(string $code): int
    {
        return (int) static::where('code', $code)->value('id');
    }
}
