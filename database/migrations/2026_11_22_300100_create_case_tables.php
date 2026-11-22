<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->boolean('active')->default(true);
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });

        Schema::create('ingest_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_client_id')->nullable()->constrained()->nullOnDelete();
            $t->string('channel', 12); // api | form | import | simulator
            $t->string('idempotency_key', 80)->unique();
            $t->longText('payload'); // encrypted JSON
            $t->string('payload_hash', 64);
            $t->string('status', 12)->default('received'); // received | applied | rejected
            $t->string('error', 500)->nullable();
            $t->timestamp('received_at');
            $t->timestamp('processed_at')->nullable();
            $t->index(['status', 'received_at']);
        });

        Schema::create('persons', function (Blueprint $t) {
            $t->id();
            $t->uuid('pseudonym')->unique();
            $t->string('sex', 1); // M | F | U
            $t->unsignedSmallInteger('birth_year');
            $t->foreignId('residence_org_unit_id')->constrained('org_units');
            $t->boolean('is_synthetic')->default(true);
            $t->timestamps();
        });

        Schema::create('cases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $t->foreignId('disease_id')->constrained();
            $t->foreignId('reporting_org_unit_id')->constrained('org_units');
            $t->foreignId('residence_org_unit_id')->constrained('org_units');
            $t->date('onset_date');
            $t->date('notification_date');
            $t->date('investigation_date')->nullable();
            $t->string('classification', 12)->default('suspected'); // suspected | probable | confirmed | discarded
            $t->string('outcome', 10)->default('unknown'); // unknown | alive | recovered | died
            $t->boolean('hospitalised')->default(false);
            $t->string('icd10_code', 20);
            $t->string('status', 20)->default('reported'); // reported | investigating | closed
            $t->date('epi_week');
            $t->foreignId('ingest_event_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_synthetic')->default(true);
            $t->timestamps();
            $t->index(['disease_id', 'epi_week']);
            $t->index(['reporting_org_unit_id', 'epi_week']);
            $t->index(['residence_org_unit_id', 'epi_week']);
            $t->index(['status', 'classification']);
        });

        Schema::create('encounters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $t->foreignId('case_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('org_unit_id')->constrained('org_units');
            $t->dateTime('encountered_at');
            $t->string('type', 4)->default('OPD'); // OPD | IPD | ED
            $t->string('notes', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('diagnoses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $t->string('icd10_code', 20);
            $t->boolean('is_primary')->default(true);
        });

        Schema::create('lab_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('case_id')->constrained()->cascadeOnDelete();
            $t->string('specimen_type', 30);
            $t->string('test_code', 30);
            $t->date('collected_at');
            $t->date('resulted_at')->nullable();
            $t->string('result', 14)->default('pending'); // pending | positive | negative | indeterminate
            $t->foreignId('lab_org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $t->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['result', 'collected_at']);
        });

        Schema::create('vaccinations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $t->foreignId('antigen_id')->constrained();
            $t->unsignedTinyInteger('dose_number')->default(1);
            $t->date('administered_on');
            $t->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
        });

        Schema::create('case_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('case_id')->constrained()->cascadeOnDelete();
            $t->string('type', 24); // notified | classified | outcome | investigated | lab_result | note
            $t->json('data')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('occurred_at');
            $t->index(['case_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        foreach (['case_events', 'vaccinations', 'lab_results', 'diagnoses', 'encounters', 'cases', 'persons', 'ingest_events', 'api_clients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
