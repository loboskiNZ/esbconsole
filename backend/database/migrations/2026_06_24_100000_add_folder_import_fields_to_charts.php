<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('title');
            $table->string('mime_type', 127)->nullable()->after('checksum');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->foreignId('import_batch_id')->nullable()->after('notes')->constrained()->nullOnDelete();
        });

        Schema::table('charts', function (Blueprint $table) {
            $table->unique(['song_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->dropUnique(['song_id', 'checksum']);
            $table->dropForeign(['import_batch_id']);
            $table->dropColumn([
                'original_filename',
                'mime_type',
                'file_size',
                'import_batch_id',
            ]);
        });
    }
};
