@php
    $serviceOptions = collect(app(\Webkul\Lead\Repositories\ServiceRepository::class)->getDropdownOptions())
        ->map(fn ($option) => (object) [
            'id'   => $option['value'],
            'name' => $option['label'],
        ])
        ->values();

    $selectedServiceIds = isset($lead)
        ? $lead->services()->pluck('services.id')->map(fn ($id) => (int) $id)->values()->all()
        : old('services', []);

    $canAddService = bouncer()->hasPermission('settings.lead.services_offered.create')
        || bouncer()->hasPermission('leads.create')
        || bouncer()->hasPermission('leads.edit')
        || app(\Webkul\Lead\Services\SourceAccessService::class)->isSdrUser();
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
        :store-url="route('admin.leads.services_offered.store')"
    />
</x-admin::form.control-group>
