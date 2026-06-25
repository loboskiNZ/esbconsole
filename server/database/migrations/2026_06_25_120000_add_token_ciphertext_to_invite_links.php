<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invite_links')) {
            return;
        }

        if (Schema::hasColumn('invite_links', 'token_ciphertext')) {
            return;
        }

        Schema::table('invite_links', function (Blueprint $table) {
            $table->text('token_ciphertext')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invite_links')) {
            return;
        }

        if (! Schema::hasColumn('invite_links', 'token_ciphertext')) {
            return;
        }

        Schema::table('invite_links', function (Blueprint $table) {
            $table->dropColumn('token_ciphertext');
        });
    }
};
