<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_device_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->foreignId('integration_device_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['performance_id', 'integration_device_id']);
            $table->index(['performance_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_device_assignments');
    }
};
