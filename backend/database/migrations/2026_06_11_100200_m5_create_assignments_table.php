<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('musician_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_part_id')->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['musician_id', 'active']);
            $table->index(['instrument_part_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
