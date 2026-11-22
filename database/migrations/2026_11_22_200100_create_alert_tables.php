<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('disease_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $t->string('method', 24)->default('outbreak_signal'); // outbreak_signal | single_case
            $t->json('params')->nullable();
            $t->string('severity', 8)->default('auto'); // auto | high | medium | low
            $t->unsignedSmallInteger('min_count')->default(5);
            $t->unsignedSmallInteger('cooldown_hours')->default(168);
            $t->unsignedInteger('escalate_after_minutes')->nullable()->default(720);
            $t->json('audience_roles');
            $t->boolean('enabled')->default(true);
            $t->timestamps();
        });

        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $t->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $t->foreignId('disease_id')->constrained()->cascadeOnDelete();
            $t->date('period_start');
            $t->date('last_signal_week');
            $t->unsignedSmallInteger('signal_weeks')->default(1);
            $t->decimal('observed', 12, 2);
            $t->decimal('expected', 12, 2)->default(0);
            $t->decimal('upper_limit', 12, 2)->default(0);
            $t->string('severity', 8);
            $t->string('status', 14)->default('open'); // open | acknowledged | escalated | resolved
            $t->timestamp('triggered_at');
            $t->unsignedTinyInteger('escalation_level')->default(0);
            $t->timestamp('escalated_at')->nullable();
            $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('acknowledged_at')->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'severity']);
            $t->index(['org_unit_id', 'disease_id', 'status']);
        });

        Schema::create('alert_preferences', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $t->string('mode', 8)->default('auto'); // auto (high now, rest in digest) | digest | off
            $t->timestamp('last_digest_at')->nullable();
        });

        Schema::create('alert_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('channel', 8)->default('mail');
            $t->string('mode', 12); // instant | digest | escalation
            $t->string('status', 12)->default('sent'); // sent | queued | held | failed
            $t->string('reason', 120)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'mode', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_deliveries');
        Schema::dropIfExists('alert_preferences');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_rules');
    }
};
