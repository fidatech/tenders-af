<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two kinds of account: buyers, who post tenders (government offices, ministries, NGOs,
 * companies), and suppliers, who look for tenders to bid on. One account can be both.
 * A tender posted by a buyer waits for an administrator before it is published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_supplier')->default(true); // looks for tenders
            $table->boolean('is_buyer')->default(false); // posts tenders
        });

        Schema::table('plans', function (Blueprint $table) {
            // used once posting is no longer free for everybody (Settings > "Posting tenders is free")
            $table->boolean('can_post')->default(false);
        });

        Schema::table('tenders', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete(); // the buyer that posted it; empty = entered by the data team
            $table->timestamp('submitted_at')->nullable(); // when the buyer sent it for approval
            $table->string('reject_reason')->nullable(); // why an administrator sent it back
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'status']);
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['submitted_at', 'reject_reason']);
        });
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('can_post'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn(['is_supplier', 'is_buyer']));
    }
};
