<?php

// Cloud-only additive column on ticket_price_tiers.
// Each price tier can be a private offer on its own dates.
// Existing default_offer_price_tier_id is kept and copied onto that tier.
// PH072: no drop, no rewrite of amounts, names, or dates.
// down() does not remove the column.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_price_tiers', function (Blueprint $table): void {
            $table->boolean('private_offer')->default(false);
        });

        $selectedIds = DB::table('performance_ticketing_configurations')
            ->whereNotNull('default_offer_price_tier_id')
            ->pluck('default_offer_price_tier_id');

        if ($selectedIds->isNotEmpty()) {
            DB::table('ticket_price_tiers')
                ->whereIn('id', $selectedIds->all())
                ->update(['private_offer' => true]);
        }
    }

    public function down(): void
    {
        // Non-destructive — the private_offer column is retained per PH072.
    }
};
