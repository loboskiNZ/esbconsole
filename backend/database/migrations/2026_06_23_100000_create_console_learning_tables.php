<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('console_learning_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->foreignId('show_id')->constrained()->restrictOnDelete();
            $table->foreignId('integration_device_id')->constrained()->restrictOnDelete();
            $table->string('requested_scene_number', 8);
            $table->string('learning_status', 32);
            $table->json('learned_summary_json')->nullable();
            $table->json('raw_snapshot_json')->nullable();
            $table->json('warnings_json')->nullable();
            $table->json('errors_json')->nullable();
            $table->timestamp('learned_at')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();

            $table->index(['show_id', 'learning_status']);
            $table->index(['band_id', 'created_at']);
        });

        Schema::create('show_console_baselines', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->foreignId('show_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_snapshot_id')->constrained('console_learning_snapshots')->restrictOnDelete();
            $table->string('baseline_name');
            $table->string('console_type', 16);
            $table->json('baseline_json');
            $table->boolean('active')->default(true);
            $table->timestamp('saved_at');
            $table->timestamps();

            $table->index(['show_id', 'active']);
            $table->index(['band_id', 'show_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_console_baselines');
        Schema::dropIfExists('console_learning_snapshots');
    }
};
