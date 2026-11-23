<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One row per page view of the public website, for the visitor figures (App\Support\Visitors).
 * No IP address and no cookie is kept: a visitor is a code made from the day, the address and the
 * browser that cannot be turned back into them, and that changes every day. Rows older than
 * 13 months are deleted (visits:prune).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->date('visited_on');
            $table->char('visitor', 16); // a daily code, not reversible to an address
            $table->char('country', 2)->nullable(); // ISO 3166 code; empty when it could not be found
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('page', 255);
            $table->string('referrer', 100)->nullable(); // the other website the visitor came from
            $table->string('device', 8); // mobile, desktop
            $table->timestamp('created_at')->nullable();
            $table->index(['visited_on', 'visitor']);
            $table->index(['visited_on', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
