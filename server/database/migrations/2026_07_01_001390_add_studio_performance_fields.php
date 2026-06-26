<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('performances')) {
            Schema::table('performances', function (Blueprint $table): void {
                if (! Schema::hasColumn('performances', 'performance_type')) {
                    $table->string('performance_type')->default('rehearsal');
                }

                if (! Schema::hasColumn('performances', 'location_name')) {
                    $table->string('location_name')->nullable();
                }

                if (! Schema::hasColumn('performances', 'location_address')) {
                    $table->text('location_address')->nullable();
                }

                if (! Schema::hasColumn('performances', 'prep_time')) {
                    $table->time('prep_time')->nullable();
                }

                if (! Schema::hasColumn('performances', 'performance_time')) {
                    $table->time('performance_time')->nullable();
                }

                if (! Schema::hasColumn('performances', 'performance_duration_minutes')) {
                    $table->unsignedInteger('performance_duration_minutes')->nullable();
                }

                if (! Schema::hasColumn('performances', 'packup_time')) {
                    $table->time('packup_time')->nullable();
                }

                if (! Schema::hasColumn('performances', 'briefing_notes')) {
                    $table->text('briefing_notes')->nullable();
                }
            });
        }

        if (Schema::hasTable('performance_assignments')) {
            Schema::table('performance_assignments', function (Blueprint $table): void {
                if (! Schema::hasColumn('performance_assignments', 'availability_status')) {
                    $table->string('availability_status')->default('unknown');
                }

                if (! Schema::hasColumn('performance_assignments', 'availability_notes')) {
                    $table->text('availability_notes')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive — columns retained per PH072.
    }
};
