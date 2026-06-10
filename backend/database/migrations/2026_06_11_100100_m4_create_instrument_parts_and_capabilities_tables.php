<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_parts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['band_id', 'active']);
        });

        Schema::create('capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('musician_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_part_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['musician_id', 'instrument_part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capabilities');
        Schema::dropIfExists('instrument_parts');
    }
};
