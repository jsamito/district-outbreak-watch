<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('password');
            $table->string('job_title', 80)->nullable()->after('name');
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('user_org_unit', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->primary(['user_id', 'org_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_org_unit');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['active', 'job_title', 'last_login_at']);
        });
    }
};
