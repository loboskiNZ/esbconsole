<?php

// Package: CCMM-07 Actions
// Authority: PH059 A29–A32
// Authoring Plan: PH062
// Decision Reference: PH059 / PH062 230
// Notes: action_types catalogue seeded here (reference data). action_parameters CASCADE per PH059.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('action_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained()->restrictOnDelete();
            $table->foreignId('action_type_id')->constrained('action_types')->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['band_id', 'code']);
        });

        Schema::create('action_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_definition_id')->constrained('action_definitions')->cascadeOnDelete();
            $table->string('parameter_name');
            $table->text('parameter_value');
            $table->timestamps();

            $table->unique(['action_definition_id', 'parameter_name']);
        });

        Schema::create('cue_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cue_id')->constrained()->restrictOnDelete();
            $table->foreignId('action_definition_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['cue_id', 'action_definition_id']);
            $table->index(['cue_id', 'sort_order', 'id']);
        });

        $now = now();
        $types = [
            ['X32_SCENE', 'X32 Scene'],
            ['X32_SNIPPET', 'X32 Snippet'],
            ['X32_MUTE', 'X32 Mute'],
            ['X32_FADER', 'X32 Fader'],
            ['LIGHT_MODE', 'Light Mode'],
            ['LIGHT_SCENE', 'Light Scene'],
            ['MUSICIAN_MESSAGE', 'Musician Message'],
            ['MUSICIAN_CHART', 'Musician Chart'],
            ['VIDEO_CUE', 'Video Cue'],
            ['CUSTOM', 'Custom'],
        ];

        foreach ($types as [$code, $name]) {
            DB::table('action_types')->insert([
                'code' => $code,
                'name' => $name,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cue_actions');
        Schema::dropIfExists('action_parameters');
        Schema::dropIfExists('action_definitions');
        Schema::dropIfExists('action_types');
    }
};
