<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bands')) {
            return;
        }

        Schema::table('bands', function (Blueprint $table): void {
            if (! Schema::hasColumn('bands', 'bio')) {
                $table->text('bio')->nullable()->after('name');
            }

            if (! Schema::hasColumn('bands', 'styles')) {
                $table->json('styles')->nullable()->after('bio');
            }

            if (! Schema::hasColumn('bands', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('styles');
            }

            if (! Schema::hasColumn('bands', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('logo_path');
            }
        });
    }

    public function down(): void
    {
        // Non-destructive — band profile columns retained per PH072.
    }
};
