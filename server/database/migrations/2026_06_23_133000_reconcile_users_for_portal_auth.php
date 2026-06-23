<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->unique()->after('id');
            $table->foreignId('person_id')->nullable()->after('username')->constrained('people')->nullOnDelete();
            $table->foreignId('band_id')->nullable()->after('person_id')->constrained('bands')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('password');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN name DROP NOT NULL');

            $indexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'users'"))
                ->pluck('indexname');

            if ($indexes->contains('users_email_unique')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropUnique(['email']);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropForeign(['band_id']);
            $table->dropColumn(['username', 'person_id', 'band_id', 'is_active']);
        });
    }
};
