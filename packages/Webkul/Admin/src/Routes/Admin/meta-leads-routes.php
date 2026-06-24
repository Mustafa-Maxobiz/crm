<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\MetaLead\MetaLeadController;
use Webkul\MetaLead\Http\Controllers\WebhookController;

Route::prefix('meta-leads')->group(function () {
    Route::controller(WebhookController::class)->group(function () {
        Route::match(['get', 'post'], 'webhook', 'handle')
            ->name('admin.meta_leads.webhook')
            ->withoutMiddleware('user');
    });

    Route::controller(MetaLeadController::class)->group(function () {
        Route::get('', 'index')->name('admin.meta_leads.index');

        Route::get('view/{id}', 'view')->name('admin.meta_leads.view');

        Route::put('edit/{id}/status', 'updateStatus')->name('admin.meta_leads.update_status');

        Route::put('edit/{id}/users', 'updateUsers')->name('admin.meta_leads.update_users');

        Route::delete('edit/{id}', 'destroy')->name('admin.meta_leads.delete');

        Route::post('mass-update', 'massUpdate')->name('admin.meta_leads.mass_update');

        Route::post('mass-destroy', 'massDestroy')->name('admin.meta_leads.mass_delete');
    });
});
