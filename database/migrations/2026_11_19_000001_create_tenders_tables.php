<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ------------------------------------------------------------ the platform (admin side) */

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 10)->default('super'); // super, manager, viewer
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        // Every plan has one price in one currency. The same plan is offered twice: in AFN for
        // Afghan companies, and in USD for NGOs and international clients.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('interval', 10)->default('yearly'); // monthly, yearly, lifetime
            $table->unsignedInteger('max_users')->nullable(); // empty = no limit
            $table->boolean('full_access')->default(false); // full tender details and documents
            $table->boolean('instant_alerts')->default(false); // an email as soon as a matching tender is published
            $table->boolean('whatsapp_alerts')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // where the money of subscriptions is received: bank accounts, HesabPay, cash at the office ...
        Schema::create('platform_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('bank');
            $table->char('currency', 3)->default('USD');
            $table->string('number')->nullable(); // the account or wallet number to send the money to
            $table->string('qr_path')->nullable();
            $table->text('details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ------------------------------------------------------- what tenders are sorted by */

        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 40)->default('box');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('capital')->nullable();
            $table->timestamps();
        });

        /* ------------------------------------------------------------------------ companies */

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind', 15)->default('company'); // company (Afghan), ngo, international
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('country', 100)->nullable();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->text('about')->nullable();
            $table->string('license_no', 100)->nullable();
            $table->string('tin', 100)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('size', 20)->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_listed')->default(true); // shown in the public directory

            // checking that the company is real
            $table->string('verify_status', 10)->default('none'); // none, pending, verified, rejected
            $table->string('verify_doc_path')->nullable();
            $table->string('verify_note')->nullable();
            $table->timestamp('verify_requested_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            // tender alerts: which tenders the company hears about, and how
            $table->boolean('alert_email')->default(true);
            $table->boolean('alert_whatsapp')->default(false);
            $table->json('alert_types')->nullable(); // empty = every tender type
            $table->string('notify_email')->nullable();

            // subscription
            $table->string('timezone', 64)->default('Asia/Kabul');
            $table->char('currency', 3)->default('AFN'); // the currency the company pays in
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('active'); // trial, active, suspended
            $table->date('trial_ends_at')->nullable();
            $table->date('subscription_ends_at')->nullable(); // empty on an active company = no end
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['is_listed', 'verify_status']);
        });

        Schema::create('company_industry', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->primary(['company_id', 'industry_id']);
        });

        Schema::create('category_company', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['company_id', 'category_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('note')->nullable();
            $table->boolean('requested')->default(false); // sent by the company itself, not written by an administrator
            $table->date('sent_on')->nullable();
            $table->string('reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason', 40)->nullable(); // a key of config('tenders.reject_reasons')
            $table->string('reject_note')->nullable();
            $table->unsignedInteger('receipt_no')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 10)->default('member'); // owner, member
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        /* -------------------------------------------------------------------------- tenders */

        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->nullable(); // the buyer's own number
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type', 20)->default('rfq'); // a key of config('tenders.types')
            $table->string('buyer_name');
            $table->string('buyer_type', 15)->default('ngo'); // a key of config('tenders.buyer_types')
            $table->foreignId('industry_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete(); // empty = several provinces or the whole country
            $table->string('location')->nullable();

            // everybody reads the summary; the rest is for subscribers
            $table->text('summary');
            $table->longText('description')->nullable();
            $table->text('eligibility')->nullable();
            $table->string('submission_method', 20)->nullable(); // a key of config('tenders.submission_methods')
            $table->text('submission_details')->nullable();
            $table->text('contact')->nullable();

            $table->string('budget_type', 12)->default('undisclosed'); // exact, range, undisclosed
            $table->decimal('budget_min', 16, 2)->nullable();
            $table->decimal('budget_max', 16, 2)->nullable();
            $table->char('budget_currency', 3)->nullable();
            $table->string('size_band', 10)->nullable(); // small, medium, large, xlarge

            $table->date('published_on')->nullable();
            $table->date('questions_until')->nullable();
            $table->dateTime('deadline')->nullable(); // Kabul time
            $table->string('status', 12)->default('draft'); // draft, published, cancelled, awarded
            $table->string('awarded_to')->nullable();

            $table->string('source_name')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamp('alerted_at')->nullable(); // when the alerts went out
            $table->unsignedInteger('views')->default(0);
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'deadline']);
            $table->index(['status', 'published_on']);
        });

        Schema::create('tender_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('saved_tenders', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['company_id', 'tender_id']);
        });

        // one row for every alert sent, so a company never gets the same tender twice on a channel
        Schema::create('tender_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10); // email, whatsapp, digest
            $table->string('status', 10)->default('sent'); // sent, failed
            $table->timestamps();
            $table->unique(['company_id', 'tender_id', 'channel']);
        });

        /* ---------------------------------------------------------------------- housekeeping */

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('body')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_name')->nullable();
            $table->string('action', 60);
            $table->string('subject')->nullable();
            $table->text('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('platform_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->date('date');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index('date');
        });

        // one row for every automatic email to a company, so the same one never goes twice in a day
        Schema::create('company_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->date('on_date');
            $table->timestamps();
            $table->unique(['company_id', 'kind', 'on_date']);
        });
    }

    public function down(): void
    {
        foreach (['company_mails', 'platform_expenses', 'admin_logs', 'settings', 'notices', 'tender_alerts', 'saved_tenders', 'tender_documents', 'tenders', 'password_reset_tokens', 'users', 'subscriptions', 'category_company', 'company_industry', 'companies', 'provinces', 'categories', 'industries', 'platform_accounts', 'plans', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
