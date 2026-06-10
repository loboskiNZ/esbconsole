<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('lifecycle_state')->default('draft');
            $table->timestamps();

            $table->index(['band_id', 'lifecycle_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
