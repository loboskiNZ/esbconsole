<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invite_link_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invite_link_id')->constrained('invite_links')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['invite_link_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_link_acceptances');
    }
};
