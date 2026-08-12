<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Lead\ActivityController;
use Webkul\Admin\Http\Controllers\Lead\EmailController;
use Webkul\Admin\Http\Controllers\Lead\LeadController;
use Webkul\Admin\Http\Controllers\Lead\QuoteController;
use Webkul\Admin\Http\Controllers\Lead\TagController;

Route::controller(LeadController::class)->prefix('leads')->group(function () {
    Route::get('', 'index')->name('admin.leads.index');

    Route::get('create', 'create')->name('admin.leads.create');

    Route::post('create', 'store')->name('admin.leads.store');

    Route::post('create-by-ai', 'createByAI')->name('admin.leads.create_by_ai');

    Route::get('import/template', 'importTemplate')->name('admin.leads.import.template');

    Route::post('import', 'import')->name('admin.leads.import');

    Route::post('import/start', 'importStart')->name('admin.leads.import.start');

    Route::post('import/process', 'importProcess')->name('admin.leads.import.process');

    Route::post('import/retry', 'importRetry')->name('admin.leads.import.retry');

    Route::get('disqualified', 'disqualified')->name('admin.leads.disqualified');

    Route::get('view/{id}', 'view')->name('admin.leads.view');

    Route::get('edit/{id}', 'edit')->name('admin.leads.edit');

    Route::get('edit/{id}/form-data', 'formData')->name('admin.leads.form_data');

    Route::put('edit/{id}', 'update')->name('admin.leads.update');

    Route::put('attributes/edit/{id}', 'updateAttributes')->name('admin.leads.attributes.update');

    Route::post('services-offered', 'storeServiceOfferedOption')->name('admin.leads.services_offered.store');

    Route::put('stage/edit/{id}', 'updateStage')->name('admin.leads.stage.update');

    Route::post('disqualify/{id}', 'disqualify')->name('admin.leads.disqualify');

    Route::post('restore/{id}', 'restoreDisqualified')->name('admin.leads.restore_disqualified');

    Route::post('incorrect-info/{id}/reassign', 'reassignIncorrectInfo')->name('admin.leads.incorrect_info.reassign');

    Route::post('ended/{id}/reassign', 'reassignEndedLead')->name('admin.leads.ended.reassign');

    Route::get('search', 'search')->name('admin.leads.search');

    Route::delete('{id}', 'destroy')->name('admin.leads.delete');

    Route::post('mass-update', 'massUpdate')->name('admin.leads.mass_update');

    Route::post('mass-destroy', 'massDestroy')->name('admin.leads.mass_delete');

    Route::get('get/{pipeline_id?}', 'get')->name('admin.leads.get');

    Route::delete('product/{lead_id}', 'removeProduct')->name('admin.leads.product.remove');

    Route::put('product/{lead_id}', 'addProduct')->name('admin.leads.product.add');

    Route::post('followup/complete/{id}', 'followupComplete')->name('admin.leads.followup.complete');

    Route::post('duplicate/{id}', 'duplicateToCompanies')->name('admin.leads.duplicate_to_companies');

    Route::get('kanban/look-up', [LeadController::class, 'kanbanLookup'])->name('admin.leads.kanban.look_up');

    Route::controller(ActivityController::class)->prefix('{id}/activities')->group(function () {
        Route::get('', 'index')->name('admin.leads.activities.index');
    });

    Route::controller(TagController::class)->prefix('{id}/tags')->group(function () {
        Route::post('', 'attach')->name('admin.leads.tags.attach');

        Route::patch('', 'replace')->name('admin.leads.tags.replace');

        Route::delete('', 'detach')->name('admin.leads.tags.detach');
    });

    Route::controller(EmailController::class)->prefix('{id}/emails')->group(function () {
        Route::post('', 'store')->name('admin.leads.emails.store');

        Route::delete('', 'detach')->name('admin.leads.emails.detach');
    });

    Route::controller(QuoteController::class)->prefix('{id}/quotes')->group(function () {
        Route::delete('{quote_id?}', 'delete')->name('admin.leads.quotes.delete');
    });

    /**
     * Lead Clouser leads — assigned leads for closure users.
     */
    Route::prefix('lead-clouser')->group(function () {
        Route::get('', 'leadClouser')->name('admin.leads.lead_clouser');

        Route::get('view/{id}', 'view')->name('admin.leads.lead_clouser.view');

        Route::get('edit/{id}', 'edit')->name('admin.leads.lead_clouser.edit');

        Route::get('edit/{id}/form-data', 'formData')->name('admin.leads.lead_clouser.form_data');

        Route::put('edit/{id}', 'update')->name('admin.leads.lead_clouser.update');

        Route::put('attributes/edit/{id}', 'updateAttributes')->name('admin.leads.lead_clouser.attributes.update');

        Route::post('services-offered', 'storeServiceOfferedOption')->name('admin.leads.lead_clouser.services_offered.store');

        Route::put('stage/edit/{id}', 'updateStage')->name('admin.leads.lead_clouser.stage.update');

        Route::post('disqualify/{id}', 'disqualify')->name('admin.leads.lead_clouser.disqualify');

        Route::post('restore/{id}', 'restoreDisqualified')->name('admin.leads.lead_clouser.restore_disqualified');

        Route::get('get/{pipeline_id?}', 'get')->name('admin.leads.lead_clouser.get');

        Route::post('mass-update', 'massUpdate')->name('admin.leads.lead_clouser.mass_update');

        Route::delete('product/{lead_id}', 'removeProduct')->name('admin.leads.lead_clouser.product.remove');

        Route::put('product/{lead_id}', 'addProduct')->name('admin.leads.lead_clouser.product.add');

        Route::post('followup/complete/{id}', 'followupComplete')->name('admin.leads.lead_clouser.followup.complete');

        Route::post('duplicate/{id}', 'duplicateToCompanies')->name('admin.leads.lead_clouser.duplicate_to_companies');

        Route::get('kanban/look-up', [LeadController::class, 'kanbanLookup'])->name('admin.leads.lead_clouser.kanban.look_up');

        Route::controller(ActivityController::class)->prefix('{id}/activities')->group(function () {
            Route::get('', 'index')->name('admin.leads.lead_clouser.activities.index');
        });

        Route::controller(TagController::class)->prefix('{id}/tags')->group(function () {
            Route::post('', 'attach')->name('admin.leads.lead_clouser.tags.attach');

            Route::patch('', 'replace')->name('admin.leads.lead_clouser.tags.replace');

            Route::delete('', 'detach')->name('admin.leads.lead_clouser.tags.detach');
        });

        Route::controller(EmailController::class)->prefix('{id}/emails')->group(function () {
            Route::post('', 'store')->name('admin.leads.lead_clouser.emails.store');

            Route::delete('', 'detach')->name('admin.leads.lead_clouser.emails.detach');
        });

        Route::controller(QuoteController::class)->prefix('{id}/quotes')->group(function () {
            Route::delete('{quote_id?}', 'delete')->name('admin.leads.lead_clouser.quotes.delete');
        });
    });

    /**
     * SDR leads — parallel list/detail/action routes (separate ACL).
     */
    Route::prefix('sdr')->group(function () {
        Route::get('', 'sdr')->name('admin.leads.sdr');

        Route::get('create', 'create')->name('admin.leads.sdr.create');

        Route::post('create', 'store')->name('admin.leads.sdr.store');

        Route::post('create-by-ai', 'createByAI')->name('admin.leads.sdr.create_by_ai');

        Route::get('import/template', 'importTemplate')->name('admin.leads.sdr.import.template');

        Route::post('import', 'import')->name('admin.leads.sdr.import');

        Route::post('import/start', 'importStart')->name('admin.leads.sdr.import.start');

        Route::post('import/process', 'importProcess')->name('admin.leads.sdr.import.process');

        Route::post('import/retry', 'importRetry')->name('admin.leads.sdr.import.retry');

        Route::get('disqualified', 'disqualified')->name('admin.leads.sdr.disqualified');

        Route::get('view/{id}', 'view')->name('admin.leads.sdr.view');

        Route::get('edit/{id}', 'edit')->name('admin.leads.sdr.edit');

        Route::get('edit/{id}/form-data', 'formData')->name('admin.leads.sdr.form_data');

        Route::put('edit/{id}', 'update')->name('admin.leads.sdr.update');

        Route::put('attributes/edit/{id}', 'updateAttributes')->name('admin.leads.sdr.attributes.update');

        Route::post('services-offered', 'storeServiceOfferedOption')->name('admin.leads.sdr.services_offered.store');

        Route::put('stage/edit/{id}', 'updateStage')->name('admin.leads.sdr.stage.update');

        Route::post('disqualify/{id}', 'disqualify')->name('admin.leads.sdr.disqualify');

        Route::post('restore/{id}', 'restoreDisqualified')->name('admin.leads.sdr.restore_disqualified');

        Route::post('incorrect-info/{id}/reassign', 'reassignIncorrectInfo')->name('admin.leads.sdr.incorrect_info.reassign');

        Route::post('ended/{id}/reassign', 'reassignEndedLead')->name('admin.leads.sdr.ended.reassign');

        Route::get('search', 'search')->name('admin.leads.sdr.search');

        Route::delete('{id}', 'destroy')->name('admin.leads.sdr.delete');

        Route::post('mass-update', 'massUpdate')->name('admin.leads.sdr.mass_update');

        Route::post('mass-destroy', 'massDestroy')->name('admin.leads.sdr.mass_delete');

        Route::get('get/{pipeline_id?}', 'get')->name('admin.leads.sdr.get');

        Route::delete('product/{lead_id}', 'removeProduct')->name('admin.leads.sdr.product.remove');

        Route::put('product/{lead_id}', 'addProduct')->name('admin.leads.sdr.product.add');

        Route::post('followup/complete/{id}', 'followupComplete')->name('admin.leads.sdr.followup.complete');

        Route::post('duplicate/{id}', 'duplicateToCompanies')->name('admin.leads.sdr.duplicate_to_companies');

        Route::get('kanban/look-up', [LeadController::class, 'kanbanLookup'])->name('admin.leads.sdr.kanban.look_up');

        Route::controller(ActivityController::class)->prefix('{id}/activities')->group(function () {
            Route::get('', 'index')->name('admin.leads.sdr.activities.index');
        });

        Route::controller(TagController::class)->prefix('{id}/tags')->group(function () {
            Route::post('', 'attach')->name('admin.leads.sdr.tags.attach');

            Route::patch('', 'replace')->name('admin.leads.sdr.tags.replace');

            Route::delete('', 'detach')->name('admin.leads.sdr.tags.detach');
        });

        Route::controller(EmailController::class)->prefix('{id}/emails')->group(function () {
            Route::post('', 'store')->name('admin.leads.sdr.emails.store');

            Route::delete('', 'detach')->name('admin.leads.sdr.emails.detach');
        });

        Route::controller(QuoteController::class)->prefix('{id}/quotes')->group(function () {
            Route::delete('{quote_id?}', 'delete')->name('admin.leads.sdr.quotes.delete');
        });
    });

    /**
     * LGE leads — parallel list/detail/action routes (separate ACL).
     */
    Route::prefix('lge')->group(function () {
        Route::get('', 'lge')->name('admin.leads.lge');

        Route::get('create', 'create')->name('admin.leads.lge.create');

        Route::post('create', 'store')->name('admin.leads.lge.store');

        Route::post('create-by-ai', 'createByAI')->name('admin.leads.lge.create_by_ai');

        Route::get('source-link/check', 'checkLinkedInSourceLink')->name('admin.leads.lge.source_link.check');

        Route::get('import/template', 'importTemplate')->name('admin.leads.lge.import.template');

        Route::post('import', 'import')->name('admin.leads.lge.import');

        Route::post('import/start', 'importStart')->name('admin.leads.lge.import.start');

        Route::post('import/process', 'importProcess')->name('admin.leads.lge.import.process');

        Route::post('import/retry', 'importRetry')->name('admin.leads.lge.import.retry');

        Route::get('disqualified', 'disqualified')->name('admin.leads.lge.disqualified');

        Route::get('view/{id}', 'view')->name('admin.leads.lge.view');

        Route::get('edit/{id}', 'edit')->name('admin.leads.lge.edit');

        Route::get('edit/{id}/form-data', 'formData')->name('admin.leads.lge.form_data');

        Route::put('edit/{id}', 'update')->name('admin.leads.lge.update');

        Route::put('attributes/edit/{id}', 'updateAttributes')->name('admin.leads.lge.attributes.update');

        Route::post('services-offered', 'storeServiceOfferedOption')->name('admin.leads.lge.services_offered.store');

        Route::put('stage/edit/{id}', 'updateStage')->name('admin.leads.lge.stage.update');

        Route::post('disqualify/{id}', 'disqualify')->name('admin.leads.lge.disqualify');

        Route::post('restore/{id}', 'restoreDisqualified')->name('admin.leads.lge.restore_disqualified');

        Route::post('incorrect-info/{id}/reassign', 'reassignIncorrectInfo')->name('admin.leads.lge.incorrect_info.reassign');

        Route::post('ended/{id}/reassign', 'reassignEndedLead')->name('admin.leads.lge.ended.reassign');

        Route::get('search', 'search')->name('admin.leads.lge.search');

        Route::delete('{id}', 'destroy')->name('admin.leads.lge.delete');

        Route::post('mass-update', 'massUpdate')->name('admin.leads.lge.mass_update');

        Route::post('mass-destroy', 'massDestroy')->name('admin.leads.lge.mass_delete');

        Route::get('get/{pipeline_id?}', 'get')->name('admin.leads.lge.get');

        Route::delete('product/{lead_id}', 'removeProduct')->name('admin.leads.lge.product.remove');

        Route::put('product/{lead_id}', 'addProduct')->name('admin.leads.lge.product.add');

        Route::post('followup/complete/{id}', 'followupComplete')->name('admin.leads.lge.followup.complete');

        Route::post('duplicate/{id}', 'duplicateToCompanies')->name('admin.leads.lge.duplicate_to_companies');

        Route::get('kanban/look-up', [LeadController::class, 'kanbanLookup'])->name('admin.leads.lge.kanban.look_up');

        Route::controller(ActivityController::class)->prefix('{id}/activities')->group(function () {
            Route::get('', 'index')->name('admin.leads.lge.activities.index');
        });

        Route::controller(TagController::class)->prefix('{id}/tags')->group(function () {
            Route::post('', 'attach')->name('admin.leads.lge.tags.attach');

            Route::patch('', 'replace')->name('admin.leads.lge.tags.replace');

            Route::delete('', 'detach')->name('admin.leads.lge.tags.detach');
        });

        Route::controller(EmailController::class)->prefix('{id}/emails')->group(function () {
            Route::post('', 'store')->name('admin.leads.lge.emails.store');

            Route::delete('', 'detach')->name('admin.leads.lge.emails.detach');
        });

        Route::controller(QuoteController::class)->prefix('{id}/quotes')->group(function () {
            Route::delete('{quote_id?}', 'delete')->name('admin.leads.lge.quotes.delete');
        });
    });
});
