<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\LinkedInEntryController;

Route::prefix('linkedin-entries')->controller(LinkedInEntryController::class)->group(function () {
    Route::get('', 'index')->name('admin.linkedin_entries.index');

    Route::post('', 'store')->name('admin.linkedin_entries.store');

    Route::get('import/template', 'importTemplate')->name('admin.linkedin_entries.import_template');

    Route::post('import', 'import')->name('admin.linkedin_entries.import');

    Route::post('import/start', 'importStart')->name('admin.linkedin_entries.import_start');

    Route::post('import/process', 'importProcess')->name('admin.linkedin_entries.import_process');

    Route::post('import/retry', 'importRetry')->name('admin.linkedin_entries.import_retry');

    Route::get('accepted-import/template', 'acceptedImportTemplate')->name('admin.linkedin_entries.accepted_import_template');

    Route::post('accepted-import/start', 'acceptedImportStart')->name('admin.linkedin_entries.accepted_import_start');

    Route::post('accepted-import/process', 'acceptedImportProcess')->name('admin.linkedin_entries.accepted_import_process');

    Route::patch('{id}/status', 'updateStatus')->name('admin.linkedin_entries.update_status');
});
