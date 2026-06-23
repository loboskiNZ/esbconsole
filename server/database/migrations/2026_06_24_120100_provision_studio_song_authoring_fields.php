<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            if (! Schema::hasColumn('songs', 'genre')) {
                $table->string('genre', 100)->nullable()->after('mood_id');
            }

            if (! Schema::hasColumn('songs', 'style')) {
                $table->string('style', 100)->nullable()->after('genre');
            }

            if (! Schema::hasColumn('songs', 'tempo_feel')) {
                $table->string('tempo_feel', 100)->nullable()->after('style');
            }

            if (! Schema::hasColumn('songs', 'count_in')) {
                $table->unsignedTinyInteger('count_in')->nullable()->after('tempo_feel');
            }

            if (! Schema::hasColumn('songs', 'mood_intention')) {
                $table->text('mood_intention')->nullable()->after('director_notes');
            }

            if (! Schema::hasColumn('songs', 'performance_feel')) {
                $table->text('performance_feel')->nullable()->after('mood_intention');
            }

            if (! Schema::hasColumn('songs', 'arrangement_comments')) {
                $table->text('arrangement_comments')->nullable()->after('performance_feel');
            }

            if (! Schema::hasColumn('songs', 'reference_url')) {
                $table->string('reference_url', 2048)->nullable()->after('arrangement_comments');
            }

            if (! Schema::hasColumn('songs', 'reference_title')) {
                $table->string('reference_title')->nullable()->after('reference_url');
            }

            if (! Schema::hasColumn('songs', 'reference_notes')) {
                $table->text('reference_notes')->nullable()->after('reference_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            foreach ([
                'genre',
                'style',
                'tempo_feel',
                'count_in',
                'mood_intention',
                'performance_feel',
                'arrangement_comments',
                'reference_url',
                'reference_title',
                'reference_notes',
            ] as $column) {
                if (Schema::hasColumn('songs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
