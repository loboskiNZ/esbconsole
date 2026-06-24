<?php

// Package: CCMM-12 X32 Console Configuration
// Authority: PH059 (superseded by PH061A), PH061A
// Authoring Plan: PH062
// Decision Reference: PH061A 221, PH062 228, operator merge effect_library
// Notes: effects catalogue merged from effect_library_* — no effect_library tables. Algorithm reference seed in F2.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('effect_package_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('display_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'display_order']);
        });

        Schema::create('effects', function (Blueprint $table) {
            $table->id();
            $table->string('effect_code', 8);
            $table->string('effect_name');
            $table->string('operator_name')->nullable();
            $table->text('operator_description')->nullable();
            $table->json('recommended_for_json')->nullable();
            $table->string('operator_category')->nullable();
            $table->string('difficulty')->nullable();
            $table->text('starter_notes')->nullable();
            $table->unsignedTinyInteger('x32_algorithm_id');
            $table->string('x32_slot_group');
            $table->string('category');
            $table->string('implementation_type')->default('fx_slot');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['effect_code', 'x32_slot_group']);
            $table->index(['x32_slot_group', 'x32_algorithm_id']);
            $table->index('is_active');
            $table->index('operator_category');
            $table->index('difficulty');
        });

        Schema::create('effect_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('effect_id')->constrained('effects')->cascadeOnDelete();
            $table->unsignedTinyInteger('parameter_number');
            $table->string('parameter_name');
            $table->string('value_type');
            $table->string('min_value')->nullable();
            $table->string('max_value')->nullable();
            $table->string('unit')->nullable();
            $table->json('enum_values_json')->nullable();
            $table->text('scaling_notes')->nullable();
            $table->string('default_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['effect_id', 'parameter_number']);
            $table->index(['effect_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('effect_parameters');
        Schema::dropIfExists('effects');
        Schema::dropIfExists('effect_package_types');
    }
};
