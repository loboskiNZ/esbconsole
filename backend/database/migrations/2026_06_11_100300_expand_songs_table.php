<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->char('song_code', 3)->nullable()->after('band_id');
            $table->unsignedSmallInteger('bpm')->nullable()->after('name');
            $table->text('description')->nullable()->after('bpm');
            $table->text('notes')->nullable()->after('description');
            $table->string('status')->default('draft')->after('notes');
        });

        $bandIds = DB::table('songs')->select('band_id')->distinct()->pluck('band_id');
        foreach ($bandIds as $bandId) {
            $songs = DB::table('songs')->where('band_id', $bandId)->orderBy('id')->get();
            foreach ($songs as $index => $song) {
                DB::table('songs')->where('id', $song->id)->update([
                    'song_code' => str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'status' => $song->lifecycle_state ?? 'draft',
                ]);
            }
        }

        Schema::table('songs', function (Blueprint $table) {
            $table->dropIndex(['band_id', 'lifecycle_state']);
            $table->dropColumn('lifecycle_state');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE songs ALTER COLUMN song_code SET NOT NULL');
        }

        Schema::table('songs', function (Blueprint $table) {
            $table->unique(['band_id', 'song_code']);
            $table->index(['band_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropUnique(['band_id', 'song_code']);
            $table->dropIndex(['band_id', 'status']);
            $table->string('lifecycle_state')->default('draft')->after('name');
        });

        foreach (DB::table('songs')->get() as $song) {
            DB::table('songs')->where('id', $song->id)->update([
                'lifecycle_state' => $song->status ?? 'draft',
            ]);
        }

        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['song_code', 'bpm', 'description', 'notes', 'status']);
            $table->index(['band_id', 'lifecycle_state']);
        });
    }
};
