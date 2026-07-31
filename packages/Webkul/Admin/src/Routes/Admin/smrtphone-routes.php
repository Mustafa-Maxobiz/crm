<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\SmrtPhone\CallLogController;
use Webkul\SmrtPhone\Http\Controllers\WebhookController;

Route::prefix('smrtphone')->group(function () {
    Route::controller(WebhookController::class)->group(function () {
        Route::match(['get', 'post'], 'webhook', 'handle')
            ->name('admin.smrtphone.webhook')
            ->withoutMiddleware('user');
    });

    Route::controller(CallLogController::class)->group(function () {
        Route::get('', 'index')->name('admin.smrtphone.index');

        Route::get('view/{id}', 'view')->name('admin.smrtphone.view');

        Route::delete('edit/{id}', 'destroy')->name('admin.smrtphone.delete');

        Route::post('mass-destroy', 'massDestroy')->name('admin.smrtphone.mass_delete');
    });
});
