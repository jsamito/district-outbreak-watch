<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->foreignId('disease_id')->constrained()->cascadeOnDelete();
            $t->string('method', 20);
            $t->json('params')->nullable();
            $t->date('trained_through');
            $t->unsignedTinyInteger('horizon_weeks');
            $t->json('backtest')->nullable();
            $t->timestamps();
            $t->unique(['org_unit_id', 'disease_id']);
        });

        Schema::create('forecast_points', function (Blueprint $t) {
            $t->id();
            $t->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $t->date('period_start');
            $t->decimal('point', 12, 2);
            $t->decimal('lower80', 12, 2);
            $t->decimal('upper80', 12, 2);
            $t->decimal('lower95', 12, 2);
            $t->decimal('upper95', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_points');
        Schema::dropIfExists('forecast_runs');
    }
};
