<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ableton_show_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('storage_reference');
            $table->string('checksum');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('band_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ableton_show_files');
    }
};
