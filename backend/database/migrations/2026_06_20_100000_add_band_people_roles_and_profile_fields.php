<?php

use App\Enums\BandRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('musicians', function (Blueprint $table) {
            $table->text('dietary_preferences')->nullable()->after('notes');
            $table->text('allergies')->nullable()->after('dietary_preferences');
            $table->text('accessibility_notes')->nullable()->after('allergies');
            $table->text('travel_notes')->nullable()->after('accessibility_notes');
            $table->text('emergency_contact_notes')->nullable()->after('travel_notes');
        });

        Schema::table('bands', function (Blueprint $table) {
            $table->foreignId('primary_director_musician_id')
                ->nullable()
                ->after('name')
                ->constrained('musicians')
                ->nullOnDelete();
        });

        Schema::create('musician_band_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('musician_id')->constrained()->cascadeOnDelete();
            $table->string('role', 64);
            $table->timestamps();

            $table->unique(['musician_id', 'role']);
            $table->index('role');
        });

        DB::table('musicians')->orderBy('id')->pluck('id')->each(function (int $musicianId) {
            DB::table('musician_band_roles')->insert([
                'musician_id' => $musicianId,
                'role' => BandRole::Musician->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('musician_band_roles');

        Schema::table('bands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_director_musician_id');
        });

        Schema::table('musicians', function (Blueprint $table) {
            $table->dropColumn([
                'dietary_preferences',
                'allergies',
                'accessibility_notes',
                'travel_notes',
                'emergency_contact_notes',
            ]);
        });
    }
};
