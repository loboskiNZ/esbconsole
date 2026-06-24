<?php

// Package: CCMM-12 X32 Console Configuration
// Authority: PH061A, PH044
// Authoring Plan: PH062
// Decision Reference: PH061A 221, PH062 228
// Notes: Musical FX packages and song assignments. effect_id links to merged effects catalogue.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('effect_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category');
            $table->string('target_section');
            $table->string('x32_algorithm_code', 8)->nullable();
            $table->unsignedTinyInteger('x32_algorithm_id')->nullable();
            $table->string('x32_slot_group');
            $table->string('effect_role');
            $table->string('implementation_type');
            $table->string('tempo_behavior');
            $table->string('active_song_safety');
            $table->json('default_parameters_json')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('target_section');
            $table->index('x32_slot_group');
            $table->index('is_active');
            $table->index(['x32_algorithm_code', 'x32_slot_group']);
        });

        Schema::create('effect_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('effect_package_type_id')->nullable()->constrained('effect_package_types')->nullOnDelete();
            $table->string('package_type');
            $table->string('target_section');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('package_type');
            $table->index('target_section');
            $table->index('is_active');
            $table->index('priority');
        });

        Schema::create('effect_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('effect_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('effect_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('effect_id')->nullable()->constrained('effects')->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedTinyInteger('preferred_slot_number')->nullable();
            $table->string('slot_group_preference')->nullable();
            $table->string('routing_mode')->nullable();
            $table->string('target_section')->nullable();
            $table->string('return_destination')->nullable();
            $table->decimal('default_return_level', 5, 2)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('parameter_overrides_json')->nullable();
            $table->json('timing_rules_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['effect_package_id', 'effect_definition_id']);
            $table->index(['effect_package_id', 'priority']);
        });

        Schema::create('effect_package_item_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('effect_package_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_effect_parameter_id')->nullable()->constrained('effect_parameters')->nullOnDelete();
            $table->unsignedTinyInteger('parameter_number');
            $table->string('parameter_name');
            $table->string('value_type');
            $table->string('value')->nullable();
            $table->string('min_value')->nullable();
            $table->string('max_value')->nullable();
            $table->string('unit')->nullable();
            $table->json('enum_values_json')->nullable();
            $table->text('scaling_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['effect_package_item_id', 'parameter_number']);
        });

        Schema::create('effect_package_item_target_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('effect_package_item_id')->constrained()->cascadeOnDelete();
            $table->string('target_section');
            $table->timestamps();

            $table->unique(['effect_package_item_id', 'target_section']);
            $table->index('target_section');
        });

        Schema::create('song_effect_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('effect_package_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('assignment_type');
            $table->boolean('enabled')->default(true);
            $table->string('fallback_console_recall_name')->nullable();
            $table->string('fallback_console_recall_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['song_id', 'effect_package_id']);
            $table->index(['song_id', 'priority']);
            $table->index('assignment_type');
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_effect_assignments');
        Schema::dropIfExists('effect_package_item_target_sections');
        Schema::dropIfExists('effect_package_item_parameters');
        Schema::dropIfExists('effect_package_items');
        Schema::dropIfExists('effect_packages');
        Schema::dropIfExists('effect_definitions');
    }
};
