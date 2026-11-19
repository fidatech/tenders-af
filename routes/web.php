<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PostedTenderController;
use App\Http\Controllers\ProfileController;
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
Route::get('companies', [SiteController::class, 'companies'])->name('site.companies');
Route::get('companies/{slug}', [SiteController::class, 'company'])->name('site.companies.show');
Route::get('companies/{slug}/logo', [SiteController::class, 'logo'])->name('site.companies.logo');

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

        Route::middleware('owner')->group(function () {
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
        crud_routes('industries', Admin\IndustryController::class, 'industries');
        crud_routes('categories', Admin\CategoryController::class, 'categories');

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
        Route::get('subscriptions/{id}/print', [Admin\SubscriptionController::class, 'print'])->whereNumber('id')->name('subscriptions.print');
        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('logs', [Admin\LogController::class, 'index'])->name('logs.index');
        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });
});
