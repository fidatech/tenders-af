<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Real visitors and robots apart (App\Support\Visitors):
 *   human    a person: the page confirmed it ran in a real browser
 *   pending  not confirmed (yet): most often a script or an unknown robot
 *   bot      a known robot or crawler, with its kind and name
 * Visits counted before this change were all browsers and stay "human".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('kind', 8)->default('human')->after('device');
            $table->string('bot_type', 20)->nullable()->after('kind'); // search, ai, seo, social, monitor, script, other
            $table->string('bot_name', 40)->nullable()->after('bot_type'); // Google, Bing, OpenAI ...
            $table->index(['visited_on', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['visited_on', 'kind']);
            $table->dropColumn(['kind', 'bot_type', 'bot_name']);
        });
    }
};
