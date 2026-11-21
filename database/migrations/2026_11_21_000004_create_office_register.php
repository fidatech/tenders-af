<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->json('permissions')->nullable();
        });

        Schema::create('office_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->restrictOnDelete();
            $table->foreignId('platform_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('platform_expenses')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('status', 12)->default('pending');
            $table->boolean('employee_funds')->default(true);
            $table->char('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->date('period_from')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('reference', 150)->nullable();
            $table->string('description', 250)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('review_note', 250)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'platform_account_id', 'status', 'kind']);
            $table->index(['status', 'kind', 'date']);
        });

        DB::table('settings')->insert(['key' => 'office_register_lock', 'value' => '1']);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'office_register_lock')->delete();
        Schema::dropIfExists('office_entries');
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
