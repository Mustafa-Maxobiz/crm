@php
    $serviceRepository = app(\Webkul\Lead\Repositories\ServiceRepository::class);

    $selectedServices = isset($lead)
        ? $lead->services()->get(['services.id', 'services.name'])
        : collect();

    $serviceOptions = collect($serviceRepository->getDropdownOptions(true))
        ->map(fn ($option) => (object) [
            'id'      => $option['value'],
            'name'    => $option['label'],
            'is_show' => $option['is_show'] ?? true,
        ])
        ->merge($selectedServices->map(fn ($service) => (object) [
            'id'      => $service->id,
            'name'    => $service->name,
            'is_show' => true,
        ]))
        ->unique('id')
        ->values();

    $selectedServiceIds = isset($lead)
        ? $selectedServices->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
        : old('services', []);

    $canAddService = bouncer()->hasPermission('settings.lead.services_offered.create')
        || bouncer()->hasPermission(lead_permission('create'))
        || bouncer()->hasPermission(lead_permission('edit'))
        || bouncer()->hasPermission('leads.create')
        || bouncer()->hasPermission('leads.edit')
        || bouncer()->hasPermission('sdr_leads.create')
        || bouncer()->hasPermission('sdr_leads.edit')
        || bouncer()->hasPermission('lge_leads.create')
        || bouncer()->hasPermission('lge_leads.edit')
        || bouncer()->hasPermission('lead_clouser_leads.edit')
        || app(\Webkul\Lead\Services\SourceAccessService::class)->isSdrUser()
        || app(\Webkul\Lead\Services\SourceAccessService::class)->isLgeUser();

    $serviceUpdateUrl = isset($lead) ? lead_route('attributes.update', $lead->id) : null;
@endphp

<x-admin::form.control-group class="mb-2.5 w-full">
    <x-admin::form.control-group.label>
        @lang('admin::app.leads.index.datagrid.service-offered')
    </x-admin::form.control-group.label>

    <x-admin::attributes.edit.multiselect
        :attribute="(object) ['code' => 'services', 'name' => __('admin::app.leads.index.datagrid.service-offered'), 'lookup_type' => null]"
        :options="$serviceOptions"
        :value="$selectedServiceIds"
        validations=""
        :can-add-new="$canAddService"
        :store-url="lead_route('services_offered.store')"
        :update-url="$serviceUpdateUrl"
    />
</x-admin::form.control-group>
