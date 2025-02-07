<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhook', [WhatsAppWebhookController::class, 'receiveWebhook']);
