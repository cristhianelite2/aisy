<?php

use App\Http\Controllers\BusinessAdminController;
use App\Http\Controllers\BusinessLandingController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

// Onboarding & Platform Entry
Route::get('/', [OnboardingController::class, 'index'])->name('home');
Route::get('/nuevo', [OnboardingController::class, 'index'])->name('onboarding.index');
Route::post('/nuevo', [OnboardingController::class, 'store'])->name('onboarding.store');
Route::get('/nuevo/{slug}/progreso', [OnboardingController::class, 'progress'])->name('onboarding.progress');
Route::get('/nuevo/{slug}/stream', [OnboardingController::class, 'stream'])->name('onboarding.stream');

// Layer 2: Giro Admin Panel
Route::get('/{slug}/admin', [BusinessAdminController::class, 'dashboard'])->name('business.admin');
Route::post('/{slug}/admin/features/toggle', [BusinessAdminController::class, 'toggleFeature'])->name('business.admin.toggle');
Route::post('/{slug}/admin/assistant', [BusinessAdminController::class, 'assistantChat'])->name('business.admin.assistant');
Route::get('/{slug}/admin/whatsapp/qr', [BusinessAdminController::class, 'whatsappQr'])->name('business.admin.wa_qr');
Route::post('/{slug}/admin/dead-times', [BusinessAdminController::class, 'triggerDeadTimes'])->name('business.admin.dead_times');
Route::post('/{slug}/admin/catalog', [BusinessAdminController::class, 'addCatalogItem'])->name('business.admin.catalog');
Route::post('/{slug}/admin/operations/{id}/status', [BusinessAdminController::class, 'updateOperationStatus'])->name('business.admin.op_status');

// Layer 1: Custom Business Landing Page
Route::get('/{slug}', [BusinessLandingController::class, 'show'])->name('business.landing');
Route::post('/{slug}/event', [BusinessLandingController::class, 'trackEvent'])->name('business.landing.event');
Route::post('/{slug}/action', [BusinessLandingController::class, 'submitAction'])->name('business.landing.action');
