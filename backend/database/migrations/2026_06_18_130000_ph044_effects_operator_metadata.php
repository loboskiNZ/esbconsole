<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('effects', function (Blueprint $table) {
            $table->string('operator_name')->nullable()->after('effect_name');
            $table->text('operator_description')->nullable()->after('operator_name');
            $table->json('recommended_for_json')->nullable()->after('operator_description');
            $table->string('operator_category')->nullable()->after('recommended_for_json');
            $table->string('difficulty')->nullable()->after('operator_category');
            $table->text('starter_notes')->nullable()->after('difficulty');

            $table->index('operator_category');
            $table->index('difficulty');
        });
    }

    public function down(): void
    {
        Schema::table('effects', function (Blueprint $table) {
            $table->dropIndex(['operator_category']);
            $table->dropIndex(['difficulty']);
            $table->dropColumn([
                'operator_name',
                'operator_description',
                'recommended_for_json',
                'operator_category',
                'difficulty',
                'starter_notes',
            ]);
        });
    }
};
