<?php

use App\Http\Controllers\Api\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('api.webhooks.midtrans');
