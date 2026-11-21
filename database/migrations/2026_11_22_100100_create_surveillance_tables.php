<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name');
            $t->string('url')->nullable();
            $t->string('licence')->nullable();
            $t->text('terms_note')->nullable();
            $t->date('accessed_at')->nullable();
            $t->text('citation')->nullable();
            $t->boolean('synthetic')->default(false);
            $t->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('data_source_id')->nullable()->constrained()->nullOnDelete();
            $t->string('command', 80);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unsignedInteger('rows_in')->default(0);
            $t->unsignedInteger('rows_rejected')->default(0);
            $t->string('status', 16)->default('running');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('diseases', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('icd10_code', 20);
            $t->unsignedTinyInteger('priority')->default(2);
            $t->text('case_definition')->nullable();
            $t->boolean('immediate_notifiable')->default(false);
            $t->boolean('case_based')->default(true);
            $t->timestamps();
        });

        Schema::create('antigens', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->unsignedTinyInteger('doses_required')->default(1);
            $t->decimal('target_percent', 5, 1)->default(90);
            $t->timestamps();
        });

        Schema::create('indicators', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name');
            $t->string('kind', 16); // count | rate | proportion | index
            $t->string('unit', 40)->nullable();
            $t->string('direction', 16)->default('lower_is_better');
            $t->decimal('target', 8, 2)->nullable();
            $t->unsignedSmallInteger('small_number_floor')->default(5);
            $t->text('definition')->nullable();
            $t->timestamps();
        });

        Schema::create('populations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('total');
            $t->unsignedInteger('under1');
            $t->unsignedInteger('under5');
            $t->unsignedInteger('women_15_49')->default(0);
            $t->foreignId('data_source_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('projected')->default(false);
            $t->unique(['org_unit_id', 'year']);
        });

        Schema::create('facility_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->foreignId('disease_id')->constrained()->cascadeOnDelete();
            $t->date('week_start');
            $t->unsignedInteger('cases');
            $t->unsignedInteger('deaths')->default(0);
            $t->string('origin', 12)->default('reported'); // reported | synthetic
            $t->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $t->unique(['org_unit_id', 'disease_id', 'week_start'], 'fr_unique');
            $t->index(['week_start', 'disease_id']);
        });

        Schema::create('reporting_statuses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->date('week_start');
            $t->boolean('received')->default(false);
            $t->boolean('on_time')->default(false);
            $t->timestamp('received_at')->nullable();
            $t->unique(['org_unit_id', 'week_start']);
            $t->index('week_start');
        });

        Schema::create('indicator_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('dimension_id')->default(0); // disease for cases, antigen for coverage, 0 = none
            $t->string('period_type', 8); // week | month | year
            $t->date('period_start');
            $t->decimal('numerator', 14, 2)->default(0);
            $t->decimal('denominator', 14, 2)->nullable();
            $t->decimal('value', 14, 4)->nullable();
            $t->timestamp('computed_at')->nullable();
            $t->unique(['org_unit_id', 'indicator_id', 'dimension_id', 'period_type', 'period_start'], 'iv_unique');
            $t->index(['indicator_id', 'dimension_id', 'period_type', 'period_start'], 'iv_lookup');
        });

        Schema::create('immunization_doses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->foreignId('antigen_id')->constrained()->cascadeOnDelete();
            $t->date('month');
            $t->unsignedInteger('doses');
            $t->unsignedInteger('target');
            $t->string('origin', 12)->default('reported');
            $t->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $t->unique(['org_unit_id', 'antigen_id', 'month'], 'imm_unique');
        });

        Schema::create('reference_estimates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('antigen_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->decimal('coverage_percent', 5, 1);
            $t->foreignId('data_source_id')->nullable()->constrained()->nullOnDelete();
            $t->unique(['antigen_id', 'year']);
        });

        Schema::create('external_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('disease_id')->constrained()->cascadeOnDelete();
            $t->string('source', 40);
            $t->string('metric', 40);
            $t->date('period_start');
            $t->unsignedBigInteger('value');
            $t->string('geography_scope', 20)->default('global');
            $t->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $t->unique(['disease_id', 'source', 'metric', 'period_start'], 'em_unique');
        });

        Schema::create('data_quality_flags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->string('type', 30);
            $t->string('severity', 10)->default('info');
            $t->date('period_start')->nullable();
            $t->string('detail')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['org_unit_id', 'type']);
        });

        Schema::create('annotations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->unsignedBigInteger('disease_id')->nullable();
            $t->date('period_start');
            $t->string('text', 500);
            $t->timestamps();
        });

        Schema::create('saved_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->string('route', 80);
            $t->json('filters');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['saved_views', 'annotations', 'data_quality_flags', 'external_metrics', 'reference_estimates', 'immunization_doses', 'indicator_values', 'reporting_statuses', 'facility_reports', 'populations', 'indicators', 'antigens', 'diseases', 'import_batches', 'data_sources'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
