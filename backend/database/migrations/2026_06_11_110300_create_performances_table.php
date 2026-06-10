<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->foreignId('show_id')->constrained()->restrictOnDelete();
            $table->string('venue');
            $table->date('performance_date');
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['band_id', 'status']);
            $table->index(['show_id', 'performance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};
