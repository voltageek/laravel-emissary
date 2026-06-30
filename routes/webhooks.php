<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

$prefix = config('emissary.webhook_path', 'webhooks');

Route::prefix($prefix)->group(function (): void {
    Route::match(['GET', 'POST'], 'whatsapp', [\Emissary\Http\WebhookController::class, 'whatsapp']);
    Route::post('telegram', [\Emissary\Http\WebhookController::class, 'telegram']);
    Route::post('web', [\Emissary\Http\WebhookController::class, 'web']);
});
