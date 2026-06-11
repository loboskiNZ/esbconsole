<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runtime_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('runtime_action_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->timestamps();

            $table->unique('runtime_action_plan_id');
            $table->index(['performance_id', 'status']);
        });

        Schema::create('runtime_dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('runtime_dispatch_id')->constrained()->restrictOnDelete();
            $table->foreignId('runtime_action_item_id')->constrained()->restrictOnDelete();
            $table->string('adapter_key');
            $table->string('action_type_code');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('payload')->nullable();
            $table->string('status');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('runtime_action_item_id');
            $table->index(['runtime_dispatch_id', 'sort_order', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runtime_dispatch_items');
        Schema::dropIfExists('runtime_dispatches');
    }
};
