<?php

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

        Schema::create('effect_library_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('x32_algorithm_code', 8)->nullable();
            $table->unsignedTinyInteger('x32_algorithm_id')->nullable();
            $table->string('x32_slot_group');
            $table->string('category');
            $table->string('implementation_type');
            $table->unsignedTinyInteger('max_instances_per_package')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('x32_slot_group');
            $table->index('is_active');
            $table->index(['x32_algorithm_code', 'x32_slot_group']);
        });

        Schema::create('effect_library_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('effect_library_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('parameter_number');
            $table->string('parameter_name');
            $table->string('value_type');
            $table->string('default_value')->nullable();
            $table->string('min_value')->nullable();
            $table->string('max_value')->nullable();
            $table->string('unit')->nullable();
            $table->json('enum_values_json')->nullable();
            $table->text('scaling_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['effect_library_item_id', 'parameter_number']);
            $table->index(['effect_library_item_id', 'is_active']);
        });

        Schema::table('effect_packages', function (Blueprint $table) {
            $table->foreignId('effect_package_type_id')
                ->nullable()
                ->after('slug')
                ->constrained('effect_package_types')
                ->nullOnDelete();
        });

        Schema::table('effect_package_items', function (Blueprint $table) {
            $table->foreignId('effect_library_item_id')
                ->nullable()
                ->after('effect_definition_id')
                ->constrained('effect_library_items')
                ->nullOnDelete();
        });

        Schema::create('effect_package_item_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('effect_package_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_effect_library_parameter_id')
                ->nullable()
                ->constrained('effect_library_parameters')
                ->nullOnDelete();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('effect_package_item_parameters');
        Schema::table('effect_package_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('effect_library_item_id');
        });
        Schema::table('effect_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('effect_package_type_id');
        });
        Schema::dropIfExists('effect_library_parameters');
        Schema::dropIfExists('effect_library_items');
        Schema::dropIfExists('effect_package_types');
    }
};
