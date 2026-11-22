<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The history of Admin > Module Management: who switched which module on or off, or changed its
 * menus, notifications or access, and when. The switches themselves live in the settings table
 * (App\Support\Modules), like every other setting of the platform. Nothing existing is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_changes', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40)->index();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_name')->nullable();
            $table->json('changes'); // option => [before, after]
            $table->string('note', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_changes');
    }
};
