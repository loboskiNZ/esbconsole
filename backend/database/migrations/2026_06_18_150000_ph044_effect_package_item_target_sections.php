<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('effect_package_item_target_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('effect_package_item_id')->constrained()->cascadeOnDelete();
            $table->string('target_section');
            $table->timestamps();

            $table->unique(['effect_package_item_id', 'target_section']);
            $table->index('target_section');
        });

        $rows = DB::table('effect_package_items')
            ->whereNotNull('target_section')
            ->where('target_section', '!=', 'not_configured')
            ->get(['id', 'target_section']);

        $now = now();

        foreach ($rows as $row) {
            DB::table('effect_package_item_target_sections')->insertOrIgnore([
                'effect_package_item_id' => $row->id,
                'target_section' => $row->target_section,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('effect_package_item_target_sections');
    }
};
