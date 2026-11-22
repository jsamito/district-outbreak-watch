<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_quality_flags', function (Blueprint $t) {
            $t->string('flag_key', 64)->nullable()->unique()->after('id');
            $t->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $t->string('resolution_note', 300)->nullable()->after('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('data_quality_flags', function (Blueprint $t) {
            $t->dropConstrainedForeignId('resolved_by');
            $t->dropColumn(['resolution_note', 'flag_key']);
        });
    }
};
