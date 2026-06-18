<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('effects', function (Blueprint $table) {
            $table->id();
            $table->string('effect_code', 8);
            $table->string('effect_name');
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

        Schema::table('effect_package_items', function (Blueprint $table) {
            $table->foreignId('effect_id')
                ->nullable()
                ->after('effect_definition_id')
                ->constrained('effects')
                ->nullOnDelete();
        });

        Schema::table('effect_package_item_parameters', function (Blueprint $table) {
            $table->foreignId('source_effect_parameter_id')
                ->nullable()
                ->after('effect_package_item_id')
                ->constrained('effect_parameters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('effect_package_item_parameters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_effect_parameter_id');
        });

        Schema::table('effect_package_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('effect_id');
        });

        Schema::dropIfExists('effect_parameters');
        Schema::dropIfExists('effects');
    }
};
