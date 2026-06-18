<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Webhook entrante de Kommo
Route::post('/webhooks/kommo', [WebhookController::class, 'kommo']);

// Intercambio OAuth de Kommo (se llama una sola vez)
Route::post('/kommo/exchange', [WebhookController::class, 'kommoExchange']);