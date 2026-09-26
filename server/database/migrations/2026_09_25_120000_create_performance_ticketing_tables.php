<?php

// Cloud-only ticketing domain for Performance (the gig).
// Applied by ESB Studio migrations on the Cloud Database.
// Not a CCMM shared table. Live Stage must not load this migration.
// The public website reads and writes these tables. This migration inserts no gig, price, or capacity data.
// PH072: new tables only. Existing performances rows and columns are unchanged.
// down() does not drop tables, so a rollback cannot destroy audience or payment data.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_ticketing_configurations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('performance_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamp('sales_open_at')->nullable();
            $table->timestamp('sales_close_at')->nullable();
            $table->boolean('public_sales_enabled')->default(false);
            $table->boolean('walk_in_sales_enabled')->default(false);
            $table->boolean('interest_registration_enabled')->default(false);
            $table->boolean('private_offers_enabled')->default(false);
            $table->boolean('marketing_registration_enabled')->default(false);
            $table->unsignedInteger('offer_validity_minutes')->default(1440);
            $table->unsignedInteger('abandoned_checkout_reminder_minutes')->default(240);
            $table->unsignedInteger('complimentary_allocation')->default(0);
            $table->unsignedInteger('promotional_allocation')->default(0);
            $table->unsignedBigInteger('default_offer_price_tier_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_price_tiers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('performance_ticketing_configuration_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->integer('amount_minor');
            $table->char('currency', 3);
            $table->string('category')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['performance_ticketing_configuration_id', 'sort_order'], 'ticket_price_tiers_configuration_sort_index');
        });

        Schema::table('performance_ticketing_configurations', function (Blueprint $table): void {
            $table->foreign('default_offer_price_tier_id')
                ->references('id')
                ->on('ticket_price_tiers')
                ->nullOnDelete();
        });

        Schema::create('audience_registrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->string('first_name');
            $table->string('email');
            $table->timestamp('marketing_consent_at')->nullable();
            $table->string('source')->nullable();
            $table->string('campaign')->nullable();
            $table->timestamp('registered_at');
            $table->timestamps();

            $table->index(['performance_id', 'email']);
        });

        Schema::create('purchase_offers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->foreignId('audience_registration_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('ticket_price_tier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->integer('amount_minor');
            $table->char('currency', 3);
            $table->string('status');
            $table->timestamp('expires_at');
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('checkout_started_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('abandoned_reminder_sent_at')->nullable();
            $table->timestamp('expiry_reminder_sent_at')->nullable();
            $table->string('source')->nullable();
            $table->string('campaign')->nullable();
            $table->timestamps();

            $table->index(['performance_id', 'status']);
        });

        Schema::create('ticket_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_offer_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('audience_registration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('status');
            $table->string('payment_status');
            $table->integer('amount_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('quantity');
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->timestamps();

            $table->index(['performance_id', 'status']);
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('ticket_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_price_tier_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('amount_minor');
            $table->char('currency', 3);
            $table->string('status');
            $table->string('attendee_name')->nullable();
            $table->string('attendee_email')->nullable();
            $table->timestamps();

            $table->index(['performance_id', 'status']);
        });

        Schema::create('guest_list_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->string('guest_name');
            $table->string('email')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('category')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['performance_id', 'guest_name']);
        });

        Schema::create('promotional_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->string('label')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable();
            $table->string('status');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['performance_id', 'status']);
        });

        Schema::create('check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('guest_list_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('promotional_allocation_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('checked_in_at');
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('guest_list_entry_id');
            $table->index('promotional_allocation_id');
        });

        Schema::create('ticketing_audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['performance_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // Non-destructive — ticketing tables are retained per PH072.
    }
};
