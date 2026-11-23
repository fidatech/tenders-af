<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Supplier Favourites & Collections: a company keeps lists of other companies it is looking at.
 * Everything here is private to the company that saved it; the saved companies never see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The first run of this migration on MySQL stopped half way (an index name over MySQL's 64-character
        // limit) after creating these two tables, before anyone could use them. Remove what it left so it can
        // run again. Nothing else ever writes to these tables before this migration has finished.
        if (! DB::table('migrations')->where('migration', '2026_11_22_000003_create_supplier_collections')->exists()) {
            Schema::dropIfExists('supplier_collection_items');
            Schema::dropIfExists('supplier_collections');
        }

        Schema::create('supplier_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete(); // the company that keeps the list
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who made it
            $table->string('name', 100);
            $table->string('description', 300)->nullable();
            $table->boolean('is_private')->default(false); // true: only the person who made it sees it
            $table->boolean('is_favourites')->default(false); // the one default list per company
            $table->timestamps();
            $table->index(['company_id', 'is_favourites']);
        });

        Schema::create('supplier_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete(); // the saved supplier
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('researching');
            $table->text('note')->nullable(); // private to the company that saved it
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->unique(['supplier_collection_id', 'company_id'], 'sci_collection_company_unique'); // MySQL allows 64 characters
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_collection_items');
        Schema::dropIfExists('supplier_collections');
    }
};
