<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->string('device_key');
            $table->string('name');
            $table->string('device_type');
            $table->boolean('enabled')->default(true);
            $table->string('connection_status');
            $table->json('configuration')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();

            $table->unique(['band_id', 'device_key']);
            $table->index(['band_id', 'device_type', 'enabled']);
        });

        Schema::create('integration_connection_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_device_id')->constrained()->restrictOnDelete();
            $table->string('profile_name');
            $table->string('protocol');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('path')->nullable();
            $table->json('options')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_validated_at')->nullable();
            $table->text('last_validation_message')->nullable();
            $table->timestamps();

            $table->unique(['integration_device_id', 'profile_name']);
            $table->index(['integration_device_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connection_profiles');
        Schema::dropIfExists('integration_devices');
    }
};
