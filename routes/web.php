<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CompanyMessageController;
use App\Http\Controllers\CompanyPdfController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\PostedTenderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TenderController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

/* ------------------------------------------------------------- the public website */

Route::get('/', [SiteController::class, 'home'])->name('site.home');

// tenders: the whole list, one sector, one province, one tender
Route::get('tenders', [SiteController::class, 'tenders'])->name('site.tenders');
Route::get('tenders/sector/{slug}', [SiteController::class, 'sector'])->name('site.sector');
Route::get('tenders/province/{slug}', [SiteController::class, 'province'])->name('site.province');
Route::get('tender/{slug}', [SiteController::class, 'tender'])->name('site.tenders.show');
Route::get('sectors', [SiteController::class, 'sectors'])->name('site.sectors');
Route::get('provinces', [SiteController::class, 'provinces'])->name('site.provinces');

// the free company directory
Route::middleware('module:directory')->group(function () {
    Route::get('companies', [SiteController::class, 'companies'])->name('site.companies');
    Route::get('companies/{slug}', [SiteController::class, 'company'])->name('site.companies.show');
    Route::get('companies/{slug}/profile', [CompanyPdfController::class, 'public'])->middleware(['module:company_pdf', 'throttle:20,1'])->name('site.companies.pdf');
    Route::get('companies/{slug}/projects/{project}', [SiteController::class, 'project'])->middleware('module:portfolios')->name('site.projects.show');
    Route::get('project-images/{id}', [SiteController::class, 'projectImage'])->whereNumber('id')->middleware('module:portfolios')->name('site.projects.image');
});
Route::get('companies/{slug}/cover', [SiteController::class, 'cover'])->name('site.companies.cover');
Route::get('companies/{slug}/logo', [SiteController::class, 'logo'])->name('site.companies.logo');

// products for wholesale (the module is switched on in the settings of the administrators)
Route::middleware('module:products')->group(function () {
    Route::get('products', [SiteController::class, 'products'])->name('site.products');
    Route::get('products/image/{id}', [SiteController::class, 'productImage'])->whereNumber('id')->name('site.products.image');
    Route::get('products/video/{id}', [SiteController::class, 'productVideo'])->whereNumber('id')->name('site.products.video');
    Route::get('products/{slug}', [SiteController::class, 'product'])->name('site.products.show');
    Route::get('products/{slug}/whatsapp', [SiteController::class, 'productContact'])->middleware('throttle:30,1')->name('site.products.contact');
});
Route::get('statistics', [SiteController::class, 'statistics'])->name('site.statistics');
// the page's script says it ran in a real browser (App\Support\Visitors::beacon); no CSRF token: it carries nothing
Route::post('v', [SiteController::class, 'beacon'])->middleware('throttle:30,1')->name('site.beacon');
Route::get('statistics.csv', [SiteController::class, 'statisticsCsv'])->middleware('throttle:20,1')->name('site.statistics.csv');

Route::get('pricing', [SiteController::class, 'pricing'])->name('site.pricing');
Route::get('how-it-works', [SiteController::class, 'how'])->name('site.how');
Route::get('questions', [SiteController::class, 'faq'])->name('site.faq');
Route::get('contact', [SiteController::class, 'contact'])->name('site.contact');
Route::post('contact', [SiteController::class, 'send'])->middleware('throttle:5,10')->name('site.contact.send');
Route::get('privacy', [SiteController::class, 'privacy'])->name('site.privacy');
Route::get('terms', [SiteController::class, 'terms'])->name('site.terms');
Route::get('sitemap.xml', [SiteController::class, 'sitemap'])->name('site.sitemap');
Route::get('robots.txt', [SiteController::class, 'robots'])->name('site.robots');
Route::get('llms.txt', [SiteController::class, 'llms'])->name('site.llms');

// the brand (Admin > Branding & Logo), the web app manifest and the offline page of the phone app
Route::get('brand/{asset}', [BrandingController::class, 'asset'])->where('asset', '[a-z0-9_-]+')->name('brand.asset');
Route::get('site.webmanifest', [BrandingController::class, 'manifest'])->name('site.manifest');
Route::get('offline', [BrandingController::class, 'offline'])->name('site.offline');
Route::get('sw.js', [BrandingController::class, 'serviceWorker'])->name('site.sw');

/* ------------------------------------------------------------------ sign in, sign up */

Route::middleware('guest:web')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:10,60')->name('register.store');
    Route::get('forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordController::class, 'email'])->middleware('throttle:6,10')->name('password.email');
    Route::get('reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordController::class, 'store'])->name('password.store');
});

/* ------------------------------------------------------------- a business (company) */

Route::middleware('auth:web')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('company.active')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // tenders as a company sees them (the public pages live under /tenders and /tender)
        Route::prefix('my')->group(function () {
            Route::get('tenders', [TenderController::class, 'index'])->name('tenders.index');
            Route::get('tenders/matching', [TenderController::class, 'matching'])->name('tenders.matching');
            Route::get('tenders/saved', [TenderController::class, 'saved'])->name('tenders.saved');
            Route::get('tenders/{id}', [TenderController::class, 'show'])->whereNumber('id')->name('tenders.show');
            Route::post('tenders/{id}/save', [TenderController::class, 'save'])->whereNumber('id')->name('tenders.save');
            Route::get('tenders/{id}/documents/{document}', [TenderController::class, 'document'])->whereNumber(['id', 'document'])->name('tenders.document');
        });

        // the tenders a buyer posts itself
        Route::prefix('my/posted')->name('posted.')->group(function () {
            Route::get('/', [PostedTenderController::class, 'index'])->name('index');
            Route::get('create', [PostedTenderController::class, 'create'])->name('create');
            Route::post('/', [PostedTenderController::class, 'store'])->name('store');
            Route::get('{id}/edit', [PostedTenderController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [PostedTenderController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [PostedTenderController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('{id}/extend', [PostedTenderController::class, 'extend'])->whereNumber('id')->name('extend');
            Route::post('{id}/documents', [PostedTenderController::class, 'addDocuments'])->whereNumber('id')->name('documents');
            Route::post('{id}/cancel', [PostedTenderController::class, 'cancel'])->whereNumber('id')->name('cancel');
            Route::post('{id}/award', [PostedTenderController::class, 'award'])->whereNumber('id')->name('award');
            Route::get('{id}/documents/{document}', [PostedTenderController::class, 'document'])->whereNumber(['id', 'document'])->name('document');
            Route::delete('{id}/documents/{document}', [PostedTenderController::class, 'destroyDocument'])->whereNumber(['id', 'document'])->name('document.destroy');
        });

        // the products a company offers for wholesale
        Route::prefix('my/products')->name('products.')->middleware('module:products')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('create', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('{id}/edit', [ProductController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [ProductController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [ProductController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::delete('{id}/images/{image}', [ProductController::class, 'destroyImage'])->whereNumber(['id', 'image'])->name('image.destroy');
            Route::get('{id}/sponsor', [ProductController::class, 'sponsor'])->whereNumber('id')->name('sponsor');
            Route::post('{id}/sponsor', [ProductController::class, 'storeSponsor'])->whereNumber('id')->name('sponsor.store');
        });

        // saved suppliers (Supplier Favourites & Collections): every member of the company
        Route::prefix('my/collections')->name('collections.')->middleware('module:collections')->group(function () {
            Route::get('/', [CollectionController::class, 'index'])->name('index');
            Route::post('/', [CollectionController::class, 'store'])->name('store');
            Route::get('{id}', [CollectionController::class, 'show'])->whereNumber('id')->name('show');
            Route::put('{id}', [CollectionController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [CollectionController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::get('{id}/compare', [CollectionController::class, 'compare'])->whereNumber('id')->name('compare');
            Route::get('{id}/export', [CollectionController::class, 'export'])->whereNumber('id')->middleware('throttle:10,1')->name('export');
            Route::put('{id}/items/{item}', [CollectionController::class, 'updateItem'])->whereNumber(['id', 'item'])->name('items.update');
            Route::delete('{id}/items/{item}', [CollectionController::class, 'removeItem'])->whereNumber(['id', 'item'])->name('items.destroy');
        });
        // enquiries between companies (Business Enquiries): every member reads and answers them
        Route::prefix('my/enquiries')->name('enquiries.')->middleware('module:enquiries')->group(function () {
            Route::get('/', [EnquiryController::class, 'index'])->name('index');
            Route::get('{id}', [EnquiryController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('{id}/reply', [EnquiryController::class, 'reply'])->whereNumber('id')->middleware('throttle:30,1')->name('reply');
            Route::post('{id}/status', [EnquiryController::class, 'status'])->whereNumber('id')->name('status');
            Route::post('{id}/block', [EnquiryController::class, 'block'])->whereNumber('id')->name('block');
            Route::post('{id}/report', [EnquiryController::class, 'report'])->whereNumber('id')->middleware('throttle:5,1')->name('report');
            Route::get('{id}/files/{message}', [EnquiryController::class, 'attachment'])->whereNumber(['id', 'message'])->name('file');
        });
        Route::post('companies/{slug}/enquiry', [EnquiryController::class, 'store'])->middleware(['module:enquiries', 'throttle:10,1'])->name('enquiries.store');
        Route::post('companies/{slug}/save', [CollectionController::class, 'save'])->middleware(['module:collections', 'throttle:60,1'])->name('collections.save');

        Route::middleware('owner')->group(function () {
            // the projects of the company (Company Project Portfolios)
            Route::prefix('my/projects')->name('projects.')->middleware('module:portfolios')->group(function () {
                Route::get('/', [ProjectController::class, 'index'])->name('index');
                Route::get('create', [ProjectController::class, 'create'])->name('create');
                Route::post('/', [ProjectController::class, 'store'])->name('store');
                Route::get('{id}/edit', [ProjectController::class, 'edit'])->whereNumber('id')->name('edit');
                Route::put('{id}', [ProjectController::class, 'update'])->whereNumber('id')->name('update');
                Route::delete('{id}', [ProjectController::class, 'destroy'])->whereNumber('id')->name('destroy');
                Route::get('{id}/files/{file}', [ProjectController::class, 'file'])->whereNumber(['id', 'file'])->name('file');
                Route::delete('{id}/files/{file}', [ProjectController::class, 'destroyFile'])->whereNumber(['id', 'file'])->name('file.destroy');
            });
            // the company profile as a PDF (Company Profile PDF Generator)
            Route::get('my/profile-pdf', [CompanyPdfController::class, 'show'])->middleware('module:company_pdf')->name('pdf.show');
            Route::get('my/profile-pdf/print', [CompanyPdfController::class, 'print'])->middleware(['module:company_pdf', 'throttle:30,1'])->name('pdf.print');
            crud_routes('team', TeamController::class, 'team');
            Route::get('company', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('company', [SettingsController::class, 'update'])->name('settings.update');
            Route::get('alerts', [AlertController::class, 'edit'])->name('alerts.edit');
            Route::put('alerts', [AlertController::class, 'update'])->name('alerts.update');
            Route::get('verification', [VerificationController::class, 'show'])->name('verification.show');
            Route::post('verification', [VerificationController::class, 'store'])->name('verification.store');
            Route::get('verification/document', [VerificationController::class, 'document'])->name('verification.document');
        });
    });

    // open even when the trial or the subscription has ended: this is where a business pays
    Route::get('subscription', [BillingController::class, 'show'])->name('billing.show');
    Route::post('subscription', [BillingController::class, 'store'])->middleware('owner')->name('billing.store');
    Route::get('subscription/receipt/{id}', [BillingController::class, 'receipt'])->whereNumber('id')->name('billing.receipt');
    Route::get('subscription/qr/{id}', [BillingController::class, 'qr'])->whereNumber('id')->name('billing.qr');

    Route::get('contact-admin', fn () => redirect()->route('messages.show'))->name('company-message.create');
    Route::get('messages', [DirectMessageController::class, 'show'])->name('messages.show');
    Route::post('messages', [DirectMessageController::class, 'store'])->middleware('throttle:30,1')->name('messages.store');
    Route::get('messages/updates', [DirectMessageController::class, 'updates'])->name('messages.updates');
    Route::get('messages/files/{message}/{file}', [DirectMessageController::class, 'attachment'])->whereNumber(['message', 'file'])->name('messages.attachment');
    Route::post('contact-admin', [CompanyMessageController::class, 'store'])->middleware('throttle:5,10')->name('company-message.store');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('notifications', [ProfileController::class, 'notices'])->name('notices.index');
    Route::get('notifications/{id}', [ProfileController::class, 'openNotice'])->whereNumber('id')->name('notices.open');
    Route::post('notifications/read', [ProfileController::class, 'readNotices'])->name('notices.read');
});

/* ------------------------------------------------- system administrators (/admin) */

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [Admin\LoginController::class, 'show'])->name('login');
        Route::post('login', [Admin\LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth:admin', 'admin.role'])->group(function () {
        Route::post('logout', [Admin\LoginController::class, 'destroy'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::prefix('messages')->name('messages.')->group(function () {
            Route::get('/', [DirectMessageController::class, 'inbox'])->name('index');
            Route::get('{recipient}', [DirectMessageController::class, 'show'])->whereNumber('recipient')->name('show');
            Route::post('{recipient}', [DirectMessageController::class, 'store'])->whereNumber('recipient')->middleware('throttle:30,1')->name('store');
            Route::get('{recipient}/updates', [DirectMessageController::class, 'updates'])->whereNumber('recipient')->name('updates');
            Route::get('{recipient}/files/{message}/{file}', [DirectMessageController::class, 'attachment'])->whereNumber(['recipient', 'message', 'file'])->name('attachment');
        });

        Route::get('companies', [Admin\CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/create', [Admin\CompanyController::class, 'create'])->name('companies.create');
        Route::post('companies', [Admin\CompanyController::class, 'store'])->name('companies.store');
        Route::get('companies/{id}/edit', [Admin\CompanyController::class, 'edit'])->whereNumber('id')->name('companies.edit');
        Route::put('companies/{id}', [Admin\CompanyController::class, 'update'])->whereNumber('id')->name('companies.update');
        Route::delete('companies/{id}', [Admin\CompanyController::class, 'destroy'])->whereNumber('id')->name('companies.destroy');

        Route::get('tenders', [Admin\TenderController::class, 'index'])->name('tenders.index');
        Route::get('tenders/create', [Admin\TenderController::class, 'create'])->name('tenders.create');
        Route::post('tenders', [Admin\TenderController::class, 'store'])->name('tenders.store');
        Route::get('tenders/{id}/edit', [Admin\TenderController::class, 'edit'])->whereNumber('id')->name('tenders.edit');
        Route::put('tenders/{id}', [Admin\TenderController::class, 'update'])->whereNumber('id')->name('tenders.update');
        Route::delete('tenders/{id}', [Admin\TenderController::class, 'destroy'])->whereNumber('id')->name('tenders.destroy');
        Route::post('tenders/{id}/approve', [Admin\TenderController::class, 'approve'])->whereNumber('id')->name('tenders.approve');
        Route::post('tenders/{id}/reject', [Admin\TenderController::class, 'reject'])->whereNumber('id')->name('tenders.reject');
        Route::get('tenders/{id}/documents/{document}', [Admin\TenderController::class, 'document'])->whereNumber(['id', 'document'])->name('tenders.document');
        Route::delete('tenders/{id}/documents/{document}', [Admin\TenderController::class, 'destroyDocument'])->whereNumber(['id', 'document'])->name('tenders.document.destroy');
        Route::get('products', [Admin\ProductController::class, 'index'])->name('products.index');
        Route::get('products/{id}', [Admin\ProductController::class, 'show'])->whereNumber('id')->name('products.show');
        Route::post('products/{id}/approve', [Admin\ProductController::class, 'approve'])->whereNumber('id')->name('products.approve');
        Route::post('products/{id}/reject', [Admin\ProductController::class, 'reject'])->whereNumber('id')->name('products.reject');
        Route::post('products/{id}/hide', [Admin\ProductController::class, 'hide'])->whereNumber('id')->name('products.hide');
        Route::delete('products/{id}', [Admin\ProductController::class, 'destroy'])->whereNumber('id')->name('products.destroy');
        Route::post('products/payments/{id}/approve', [Admin\ProductController::class, 'approvePayment'])->whereNumber('id')->name('products.payments.approve');
        Route::post('products/payments/{id}/reject', [Admin\ProductController::class, 'rejectPayment'])->whereNumber('id')->name('products.payments.reject');
        Route::get('products/payments/{id}/receipt', [Admin\ProductController::class, 'receipt'])->whereNumber('id')->name('products.payments.receipt');
        Route::get('notifications', [Admin\NoticeController::class, 'index'])->name('notices.index');
        Route::get('notifications/{id}', [Admin\NoticeController::class, 'open'])->whereNumber('id')->name('notices.open');
        Route::post('notifications/read', [Admin\NoticeController::class, 'read'])->name('notices.read');
        crud_routes('industries', Admin\IndustryController::class, 'industries');
        crud_routes('categories', Admin\CategoryController::class, 'categories');

        Route::get('growth', [Admin\GrowthController::class, 'index'])->name('growth.index');
        // the old address of the visitor figures
        Route::permanentRedirect('visitors', '/admin/growth')->name('visitors.index');
        Route::get('enquiries', [Admin\EnquiryController::class, 'index'])->name('enquiries.index');
        Route::get('enquiries/{id}', [Admin\EnquiryController::class, 'show'])->whereNumber('id')->name('enquiries.show');
        Route::post('enquiries/{id}/close', [Admin\EnquiryController::class, 'close'])->whereNumber('id')->name('enquiries.close');
        Route::get('projects', [Admin\ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{id}', [Admin\ProjectController::class, 'show'])->whereNumber('id')->name('projects.show');
        Route::post('projects/{id}/review', [Admin\ProjectController::class, 'review'])->whereNumber('id')->name('projects.review');
        Route::get('projects/{id}/files/{file}', [Admin\ProjectController::class, 'file'])->whereNumber(['id', 'file'])->name('projects.file');
        Route::get('verification', [Admin\VerificationController::class, 'index'])->name('verification.index');
        Route::post('verification/{id}/approve', [Admin\VerificationController::class, 'approve'])->whereNumber('id')->name('verification.approve');
        Route::post('verification/{id}/reject', [Admin\VerificationController::class, 'reject'])->whereNumber('id')->name('verification.reject');
        Route::get('verification/{id}/document', [Admin\VerificationController::class, 'document'])->whereNumber('id')->name('verification.document');

        crud_routes('plans', Admin\PlanController::class, 'plans');
        crud_routes('subscriptions', Admin\SubscriptionController::class, 'subscriptions');
        Route::get('subscriptions/{id}/reject', [Admin\SubscriptionController::class, 'reject'])->whereNumber('id')->name('subscriptions.reject');
        Route::post('subscriptions/{id}/reject', [Admin\SubscriptionController::class, 'storeReject'])->whereNumber('id')->name('subscriptions.reject.store');
        Route::get('subscriptions/{id}/receipt', [Admin\SubscriptionController::class, 'receipt'])->whereNumber('id')->name('subscriptions.receipt');
        crud_routes('accounts', Admin\AccountController::class, 'accounts');
        Route::get('accounts/{id}', [Admin\AccountController::class, 'show'])->whereNumber('id')->name('accounts.show');
        Route::get('accounts/{id}/qr', [Admin\AccountController::class, 'qr'])->whereNumber('id')->name('accounts.qr');
        Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [Admin\ProfileController::class, 'update'])->name('profile.update');
        crud_routes('admins', Admin\AdminController::class, 'admins');
        crud_routes('expenses', Admin\ExpenseController::class, 'expenses');
        Route::prefix('office')->name('office.')->group(function () {
            Route::get('/', [Admin\OfficeController::class, 'index'])->name('index');
            Route::get('create', [Admin\OfficeController::class, 'create'])->name('create');
            Route::post('/', [Admin\OfficeController::class, 'store'])->name('store');
            Route::get('approvals', [Admin\OfficeController::class, 'approvals'])->name('approvals');
            Route::post('{id}/review', [Admin\OfficeController::class, 'review'])->whereNumber('id')->name('review');
            Route::get('{id}/proof', [Admin\OfficeController::class, 'proof'])->whereNumber('id')->name('proof');
        });
        Route::get('subscriptions/{id}/print', [Admin\SubscriptionController::class, 'print'])->whereNumber('id')->name('subscriptions.print');
        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('logs', [Admin\LogController::class, 'index'])->name('logs.index');
        Route::get('modules', [Admin\ModuleController::class, 'index'])->name('modules.index');
        Route::put('modules/{module}', [Admin\ModuleController::class, 'update'])->where('module', '[a-z_]+')->name('modules.update');
        Route::get('branding', [Admin\BrandingController::class, 'edit'])->name('branding.edit');
        Route::put('branding', [Admin\BrandingController::class, 'update'])->name('branding.update');
        Route::delete('branding/{asset}', [Admin\BrandingController::class, 'destroy'])->where('asset', '[a-z_]+')->name('branding.destroy');
        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });
});
