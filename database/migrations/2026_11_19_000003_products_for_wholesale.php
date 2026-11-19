<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products for wholesale: a factory or a producer shows what it makes, with pictures or a
 * short video, so that NGOs, UN agencies, government offices and traders can contact it.
 * A listing is free; being "sponsored" for a week or a month is paid. The whole module is
 * switched on by an administrator (Settings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete(); // where it is made or stocked
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('summary', 300);
            $table->text('description')->nullable();
            $table->string('unit', 50); // bag, ton, carton, piece ...
            $table->string('min_order', 100); // wholesale only: the smallest order, "200 bags"
            $table->string('capacity', 150)->nullable(); // "3,000 tons a month"
            $table->decimal('price_min', 16, 2)->nullable();
            $table->decimal('price_max', 16, 2)->nullable();
            $table->char('price_currency', 3)->nullable();
            $table->boolean('made_in_afghanistan')->default(true);
            $table->string('video_path')->nullable();
            $table->string('status', 20)->default('draft'); // draft, pending, published, rejected, hidden
            $table->string('reject_reason')->nullable();
            $table->date('sponsored_until')->nullable(); // shown first, with a "Sponsored" label, up to and including this day
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('contacts')->default(0); // times a visitor pressed a contact button
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'sponsored_until']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // one payment for sponsoring a product: the company pays, uploads the proof, an administrator confirms
        Schema::create('product_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 10); // week, month
            $table->unsignedSmallInteger('days');
            $table->decimal('amount', 16, 2);
            $table->char('currency', 3);
            $table->string('reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->string('note')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotions');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
