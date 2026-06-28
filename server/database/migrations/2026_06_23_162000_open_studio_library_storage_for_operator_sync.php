<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        $previousUmask = umask(0);

        foreach ([
            storage_path('app/library/charts'),
            storage_path('app/library/incoming'),
        ] as $directory) {
            if (File::isDirectory($directory) && is_writable($directory)) {
                @chmod($directory, 0777);
            }
        }

        umask($previousUmask);
    }

    public function down(): void
    {
        //
    }
};
