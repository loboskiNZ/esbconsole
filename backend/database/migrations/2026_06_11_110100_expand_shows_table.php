<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->foreignId('ableton_show_file_id')->nullable()->after('band_id')->constrained()->restrictOnDelete();
            $table->text('description')->nullable()->after('name');
        });

        $shows = DB::table('shows')->orderBy('id')->get();
        foreach ($shows as $show) {
            $fileId = DB::table('ableton_show_files')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'band_id' => $show->band_id,
                'name' => "Local Demo Ableton File — {$show->name}",
                'storage_reference' => 'local-demo/ableton/'.Str::slug($show->name).'.als',
                'checksum' => 'demo-checksum-'.substr(md5($show->name), 0, 12),
                'notes' => 'Local Demo Data — metadata only',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('shows')->where('id', $show->id)->update([
                'ableton_show_file_id' => $fileId,
            ]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE shows ALTER COLUMN ableton_show_file_id SET NOT NULL');
        }

        Schema::table('shows', function (Blueprint $table) {
            $table->unique('ableton_show_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropUnique(['ableton_show_file_id']);
            $table->dropConstrainedForeignId('ableton_show_file_id');
            $table->dropColumn('description');
        });
    }
};
