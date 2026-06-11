<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runtime_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->string('source');
            $table->string('event_type');
            $table->string('runtime_identity');
            $table->string('song_code');
            $table->string('cue_number');
            $table->string('status');
            $table->timestamp('received_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['performance_id', 'status']);
            $table->index(['performance_id', 'runtime_identity']);
        });

        Schema::create('runtime_action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('runtime_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->foreignId('cue_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('runtime_identity');
            $table->string('status');
            $table->timestamps();

            $table->unique('runtime_event_id');
            $table->index(['performance_id', 'status']);
        });

        Schema::create('runtime_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('runtime_action_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('action_definition_id')->constrained()->restrictOnDelete();
            $table->string('action_type_code');
            $table->string('action_definition_code');
            $table->string('action_definition_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('parameters')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index(['runtime_action_plan_id', 'sort_order', 'id']);
        });

        Schema::create('runtime_audit_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('runtime_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('runtime_action_plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('runtime_action_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('stage');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['runtime_event_id', 'stage']);
            $table->index(['runtime_action_plan_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runtime_audit_records');
        Schema::dropIfExists('runtime_action_items');
        Schema::dropIfExists('runtime_action_plans');
        Schema::dropIfExists('runtime_events');
    }
};
