<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->restructureChartSharing();
        $this->extendSnippetsTable();
        $this->addCueSequenceOrder();
    }

    public function down(): void
    {
        Schema::table('cues', function (Blueprint $table) {
            $table->dropColumn('sequence_order');
        });

        DB::statement('DROP INDEX IF EXISTS snippets_active_sip_cue_unique');

        Schema::table('snippets', function (Blueprint $table) {
            $table->dropForeign(['source_snippet_id']);
            $table->dropForeign(['source_chart_id']);
            $table->dropColumn([
                'source_type',
                'source_snippet_id',
                'source_chart_id',
                'freshness_state',
                'is_active',
                'annotation_storage_reference',
                'markup_storage_reference',
                'rendered_storage_reference',
                'source_metadata',
                'chart_revision_at_creation',
            ]);
            $table->unique(['song_instrument_part_id', 'cue_id']);
        });

        Schema::table('song_instrument_parts', function (Blueprint $table) {
            $table->dropForeign(['chart_id']);
            $table->dropColumn('chart_id');
        });

        Schema::table('charts', function (Blueprint $table) {
            $table->foreignId('song_instrument_part_id')->nullable()->after('public_id')->constrained()->restrictOnDelete();
        });

        foreach (DB::table('charts')->get() as $chart) {
            $assignment = DB::table('song_instrument_parts')
                ->where('chart_id', $chart->id)
                ->first();

            if ($assignment !== null) {
                DB::table('charts')
                    ->where('id', $chart->id)
                    ->update(['song_instrument_part_id' => $assignment->id]);
            }
        }

        Schema::table('charts', function (Blueprint $table) {
            $table->dropForeign(['song_id']);
            $table->dropColumn('song_id');
        });
    }

    private function restructureChartSharing(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->foreignId('song_id')->nullable()->after('public_id')->constrained()->restrictOnDelete();
        });

        Schema::table('song_instrument_parts', function (Blueprint $table) {
            $table->foreignId('chart_id')->nullable()->after('instrument_part_id')->constrained()->restrictOnDelete();
        });

        foreach (DB::table('charts')->get() as $chart) {
            $songId = DB::table('song_instrument_parts')
                ->where('id', $chart->song_instrument_part_id)
                ->value('song_id');

            if ($songId !== null) {
                DB::table('charts')
                    ->where('id', $chart->id)
                    ->update(['song_id' => $songId]);
            }

            DB::table('song_instrument_parts')
                ->where('id', $chart->song_instrument_part_id)
                ->update(['chart_id' => $chart->id]);
        }

        Schema::table('charts', function (Blueprint $table) {
            $table->dropForeign(['song_instrument_part_id']);
            $table->dropColumn('song_instrument_part_id');
        });

        Schema::table('charts', function (Blueprint $table) {
            $table->foreignId('song_id')->nullable(false)->change();
        });
    }

    private function extendSnippetsTable(): void
    {
        Schema::table('snippets', function (Blueprint $table) {
            $table->dropUnique(['song_instrument_part_id', 'cue_id']);
        });

        Schema::table('snippets', function (Blueprint $table) {
            $table->string('source_type', 32)->default('chart_crop')->after('cue_id');
            $table->foreignId('source_snippet_id')->nullable()->after('source_type')->constrained('snippets')->nullOnDelete();
            $table->foreignId('source_chart_id')->nullable()->after('source_snippet_id')->constrained('charts')->nullOnDelete();
            $table->string('freshness_state', 32)->default('current')->after('source_chart_id');
            $table->boolean('is_active')->default(true)->after('freshness_state');
            $table->string('annotation_storage_reference')->nullable()->after('checksum');
            $table->string('markup_storage_reference')->nullable()->after('annotation_storage_reference');
            $table->string('rendered_storage_reference')->nullable()->after('markup_storage_reference');
            $table->json('source_metadata')->nullable()->after('rendered_storage_reference');
            $table->string('chart_revision_at_creation')->nullable()->after('source_metadata');
        });

        $activePredicate = DB::getDriverName() === 'pgsql' ? 'is_active IS TRUE' : 'is_active = 1';

        DB::statement(
            "CREATE UNIQUE INDEX snippets_active_sip_cue_unique ON snippets (song_instrument_part_id, cue_id) WHERE {$activePredicate}"
        );
    }

    private function addCueSequenceOrder(): void
    {
        Schema::table('cues', function (Blueprint $table) {
            $table->unsignedSmallInteger('sequence_order')->default(0)->after('cue_number');
            $table->index(['song_id', 'sequence_order']);
        });

        foreach (DB::table('cues')->orderBy('id')->get() as $cue) {
            DB::table('cues')
                ->where('id', $cue->id)
                ->update(['sequence_order' => (int) $cue->cue_number]);
        }
    }
};
