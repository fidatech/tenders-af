<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notifications for administrators: something new waits for them (a licence, a tender, a payment, a message). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30)->default('info'); // company, verification, tender, product, payment, message
            $table->string('title');
            $table->string('body')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['admin_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notices');
    }
};
