<?php

use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/whatsapp/{slug}', [WhatsAppWebhookController::class, 'handle'])
    ->name('api.whatsapp.webhook');
