<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The richer company profile (business types, mini-website, contact sharing) and project portfolios.
 *
 * Safe for a live database: only new nullable columns and new tables. Existing companies keep showing
 * exactly what they showed before: contact sharing starts as "public" with the phone and website
 * approved, because those two were already on every public company page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('business_types')->nullable()->after('kind'); // manufacturer, importer, exporter ...
            $table->string('tagline', 160)->nullable()->after('about');
            $table->json('services')->nullable()->after('tagline'); // [{name, text}]
            $table->string('cover_path')->nullable()->after('logo_path');
            $table->string('page_template', 20)->nullable()->after('cover_path'); // corporate, construction ...
            $table->string('page_accent', 7)->nullable()->after('page_template'); // one of the approved colours
            // who may see the contact details: public, premium (paid subscribers) or private (enquiries only)
            $table->string('contact_visibility', 10)->default('public')->after('whatsapp');
            $table->json('contact_fields')->nullable()->after('contact_visibility'); // the details the company approved: phone, email, whatsapp, website
        });

        Schema::create('company_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->boolean('client_public')->default(false); // the company may name the client
            $table->string('location')->nullable();
            $table->string('country', 100)->nullable();
            $table->date('started_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->string('progress', 12)->default('completed'); // completed, ongoing
            $table->decimal('contract_value', 16, 2)->nullable();
            $table->char('contract_currency', 3)->nullable();
            $table->boolean('value_public')->default(false);
            $table->text('scope')->nullable();
            $table->text('responsibilities')->nullable();
            $table->string('status', 10)->default('draft'); // draft, published (by the company)
            $table->boolean('is_featured')->default(false);
            // what Tenders.af checked. A document upload alone never makes a project "reviewed".
            $table->string('review_status', 20)->default('submitted'); // submitted, pending, evidence_reviewed, client_confirmed, rejected
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status']);
            $table->index('review_status');
        });

        Schema::create('company_project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // image (public once the project is published), evidence (private)
            $table->string('path');
            $table->string('name')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('caption', 200)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['company_project_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_project_files');
        Schema::dropIfExists('company_projects');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['business_types', 'tagline', 'services', 'cover_path', 'page_template', 'page_accent', 'contact_visibility', 'contact_fields']);
        });
    }
};
