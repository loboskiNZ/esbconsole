<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            $table->string('genre', 100)->nullable()->after('mood_id');
            $table->string('style', 100)->nullable()->after('genre');
            $table->string('tempo_feel', 100)->nullable()->after('style');
            $table->unsignedTinyInteger('count_in')->nullable()->after('tempo_feel');
            $table->text('mood_intention')->nullable()->after('director_notes');
            $table->text('performance_feel')->nullable()->after('mood_intention');
            $table->text('arrangement_comments')->nullable()->after('performance_feel');
            $table->string('reference_url', 2048)->nullable()->after('arrangement_comments');
            $table->string('reference_title')->nullable()->after('reference_url');
            $table->text('reference_notes')->nullable()->after('reference_title');
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            $table->dropColumn([
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
            ]);
        });
    }
};
