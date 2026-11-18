<?php

use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/whatsapp/{slug}', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('api.whatsapp.webhook');
