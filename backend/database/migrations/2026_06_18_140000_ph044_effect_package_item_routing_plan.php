<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('effect_package_items', function (Blueprint $table) {
            $table->string('routing_mode')->nullable()->after('slot_group_preference');
            $table->string('target_section')->nullable()->after('routing_mode');
            $table->string('return_destination')->nullable()->after('target_section');
            $table->decimal('default_return_level', 5, 2)->nullable()->after('return_destination');
        });
    }

    public function down(): void
    {
        Schema::table('effect_package_items', function (Blueprint $table) {
            $table->dropColumn([
                'routing_mode',
                'target_section',
                'return_destination',
                'default_return_level',
            ]);
        });
    }
};
