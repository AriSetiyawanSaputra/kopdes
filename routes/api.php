<?php

use App\Http\Controllers\Api\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handle'])
    ->name('api.webhooks.midtrans');
