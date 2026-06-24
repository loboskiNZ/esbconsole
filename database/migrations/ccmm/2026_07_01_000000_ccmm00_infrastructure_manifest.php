<?php

// Package: CCMM-00 Infrastructure
// Authority: PH059 CCMM
// Authoring Plan: PH062
// Decision Reference: PH062 226, 234
// Notes: Laravel cache/jobs/sessions remain in server/database/migrations/0001_* per operator decision. No ESB DDL in this package.

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // CCMM-00 is a documentation manifest. Apply server 0001_* before CCMM-01.
    }

    public function down(): void
    {
        //
    }
};
