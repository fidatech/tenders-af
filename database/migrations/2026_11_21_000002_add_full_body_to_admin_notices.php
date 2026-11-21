<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notices', function (Blueprint $table) {
            $table->text('full_body')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admin_notices', function (Blueprint $table) {
            $table->dropColumn('full_body');
        });
    }
};
