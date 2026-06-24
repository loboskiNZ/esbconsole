<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            if (! Schema::hasColumn('people', 'profile_photo_display_path')) {
                $table->string('profile_photo_display_path')->nullable()->after('profile_photo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('profile_photo_display_path');
        });
    }
};
