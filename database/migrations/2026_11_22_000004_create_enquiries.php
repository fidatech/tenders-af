<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Business Enquiries: a private thread between two companies. Only the people of those two companies
 * read it; administrators read a thread only when one side reports it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('subject', 150);
            $table->string('status', 10)->default('open'); // open, closed
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('from_read_at')->nullable(); // when the sending company last read it
            $table->timestamp('to_read_at')->nullable(); // when the receiving company last read it
            $table->foreignId('reported_by')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('report_reason', 500)->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
            $table->index(['to_company_id', 'last_message_at']);
            $table->index(['from_company_id', 'last_message_at']);
            $table->index('reported_at');
        });

        Schema::create('enquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete(); // the side that wrote it
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender_name');
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
            $table->index(['enquiry_id', 'id']);
        });

        // a company that does not want enquiries from another company any more
        Schema::create('company_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_company_id')->constrained('companies')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'blocked_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_blocks');
        Schema::dropIfExists('enquiry_messages');
        Schema::dropIfExists('enquiries');
    }
};
