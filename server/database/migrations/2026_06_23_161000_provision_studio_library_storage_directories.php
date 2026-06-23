<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        $root = storage_path('app/library');
        $charts = $root.'/charts';
        $incoming = $root.'/incoming';

        if (! File::isDirectory($charts)) {
            File::makeDirectory($charts, 0777, true);
        }

        if (! File::isDirectory($incoming)) {
            File::makeDirectory($incoming, 0777, true);
        }
    }

    public function down(): void
    {
        // Shared production chart files must survive rollback.
    }
};
