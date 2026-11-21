<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('type', 16); // country | region | district | facility
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('path', 191)->index(); // materialized path of codes, e.g. UG/UG1/UG1001/F123
            $table->json('geometry')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['type', 'parent_id']);
        });

        Schema::create('facility_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_id')->unique()->constrained('org_units')->cascadeOnDelete();
            $table->string('facility_type', 24); // hospital | hc4 | hc3 | hc2 | clinic
            $table->string('ownership', 40)->nullable();
            $table->string('source_ref', 40)->nullable(); // OSM id in the Healthsites file
            $table->unsignedSmallInteger('beds')->nullable();
            $table->json('services')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_profiles');
        Schema::dropIfExists('org_units');
    }
};
