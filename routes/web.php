<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessAdminController;
use App\Http\Controllers\BusinessLandingController;
use App\Http\Controllers\MasterAdminController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Master Super-Admin Console
Route::get('/maestro', [MasterAdminController::class, 'index'])->middleware('auth')->name('master.index');

// Onboarding & Platform Entry
Route::get('/', [OnboardingController::class, 'index'])->name('home');
Route::get('/nuevo', [OnboardingController::class, 'index'])->name('onboarding.index');
Route::post('/nuevo', [OnboardingController::class, 'store'])->middleware('throttle:15,1')->name('onboarding.store');
Route::get('/nuevo/{slug}/progreso', [OnboardingController::class, 'progress'])->name('onboarding.progress');
Route::get('/nuevo/{slug}/stream', [OnboardingController::class, 'stream'])->name('onboarding.stream');

// Layer 2: Giro Admin Panel (Authenticated + Owner Verified)
Route::middleware('auth')->group(function (): void {
    Route::get('/{slug}/admin', [BusinessAdminController::class, 'dashboard'])->name('business.admin');
    Route::post('/{slug}/admin/features/toggle', [BusinessAdminController::class, 'toggleFeature'])->name('business.admin.toggle');
    Route::post('/{slug}/admin/assistant', [BusinessAdminController::class, 'assistantChat'])->name('business.admin.assistant');
    Route::get('/{slug}/admin/whatsapp/qr', [BusinessAdminController::class, 'whatsappQr'])->name('business.admin.wa_qr');
    Route::post('/{slug}/admin/dead-times', [BusinessAdminController::class, 'triggerDeadTimes'])->name('business.admin.dead_times');
    Route::post('/{slug}/admin/catalog', [BusinessAdminController::class, 'addCatalogItem'])->name('business.admin.catalog');
    Route::post('/{slug}/admin/operations/{id}/status', [BusinessAdminController::class, 'updateOperationStatus'])->name('business.admin.op_status');
});

// Layer 1: Custom Business Landing Page (Public)
Route::get('/{slug}', [BusinessLandingController::class, 'show'])->name('business.landing');
Route::post('/{slug}/event', [BusinessLandingController::class, 'trackEvent'])->middleware('throttle:60,1')->name('business.landing.event');
Route::post('/{slug}/action', [BusinessLandingController::class, 'submitAction'])->middleware('throttle:20,1')->name('business.landing.action');
