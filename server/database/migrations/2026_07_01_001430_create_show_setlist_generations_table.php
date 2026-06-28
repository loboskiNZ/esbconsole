<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('show_setlist_generations')) {
            return;
        }

        Schema::create('show_setlist_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->restrictOnDelete();
            $table->string('storage_disk');
            $table->string('storage_reference');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->string('playlist_hash', 64)->nullable();
            $table->string('template_reference')->nullable();
            $table->timestamps();

            $table->index(['show_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        // Non-destructive — setlist generation records retained per PH072.
    }
};
